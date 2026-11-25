<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\WaitlistController;
use App\Http\Middleware\EnsurePreviewAccess;
use App\Services\PreviewAccessService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PreviewAccessGateTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        $this->createTables();
        PreviewAccessService::clearCache();
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        if (!Schema::hasTable('waitlist_entries')) {
            Schema::create('waitlist_entries', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                $table->string('interest')->nullable();
                $table->string('source')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamps();
            });
        }
    }

    public function test_closed_preview_gate_blocks_catalogue_and_order_apis_while_allowing_exempt_webhooks_and_waitlist(): void
    {
        DB::table('business_settings')->updateOrInsert(
            ['key' => PreviewAccessService::SETTING_KEY],
            ['value' => '0']
        );
        PreviewAccessService::clearCache();

        $middleware = new EnsurePreviewAccess();
        $okNext = fn (Request $r) => response()->json(['ok' => true], 200);

        // 1. Public catalogue, customer order, auth, and trade endpoints are blocked (403) without token
        foreach ([
            ['GET', '/api/v1/items/latest'],
            ['GET', '/api/v1/stores/get-stores/all'],
            ['POST', '/api/v1/customer/order/place'],
            ['POST', '/api/v1/auth/sign-up'],
            ['POST', '/api/v1/auth/vendor/register'],
            ['GET', '/api/v1/trade/sourcing'],
        ] as [$method, $uri]) {
            $res = $middleware->handle(Request::create($uri, $method), $okNext);
            $this->assertSame(403, $res->getStatusCode(), "Expected {$method} {$uri} to require preview token.");
            $this->assertSame('preview_access_required', $res->getData(true)['code']);
        }

        // 2. Forged or expired token is rejected (403)
        $forgedReq = Request::create('/api/v1/items/latest', 'GET');
        $forgedReq->headers->set('X-Preview-Access', (time() + 3600) . '.' . str_repeat('a', 64));
        $this->assertSame(403, $middleware->handle($forgedReq, $okNext)->getStatusCode());

        $expiredService = app(PreviewAccessService::class);
        $expiredReq = Request::create('/api/v1/items/latest', 'GET');
        $expiredReq->headers->set('X-Preview-Access', '1000000000.' . str_repeat('b', 64));
        $this->assertSame(403, $middleware->handle($expiredReq, $okNext)->getStatusCode());

        // 3. Exempt bootstrap, waitlist, health, SMS webhook, and partner app routes pass through (200)
        foreach ([
            ['POST', '/api/v1/waitlist'],
            ['POST', '/api/v1/waitlist/verify-access'],
            ['GET', '/api/v1/config'],
            ['GET', '/api/v1/get-page-meta-data'],
            ['GET', '/api/v1/health'],
            ['POST', '/api/v1/sms/inbound/harvest'],
            ['GET', '/api/v1/vendor/profile'],
            ['GET', '/api/v1/delivery-man/profile'],
        ] as [$method, $uri]) {
            $res = $middleware->handle(Request::create($uri, $method), $okNext);
            $this->assertSame(200, $res->getStatusCode(), "Expected exempt route {$method} {$uri} to remain open.");
        }

        // 4. Legitimate owner unlock issues a valid HMAC token that unlocks API access via header or cookie
        $waitlistController = new WaitlistController();
        $expectedCode = (string) env('PLATFORM_ACCESS_CODE', '0242');
        $unlockRes = $waitlistController->verifyAccess(Request::create('/api/v1/waitlist/verify-access', 'POST', [
            'code' => $expectedCode,
        ]));
        $this->assertSame(200, $unlockRes->getStatusCode());
        $token = $unlockRes->getData(true)['preview_token'];
        $this->assertNotEmpty($token);

        $authedHeaderReq = Request::create('/api/v1/items/latest', 'GET');
        $authedHeaderReq->headers->set('X-Preview-Access', $token);
        $this->assertSame(200, $middleware->handle($authedHeaderReq, $okNext)->getStatusCode());

        $authedCookieReq = Request::create('/api/v1/customer/order/place', 'POST');
        $authedCookieReq->cookies->set(PreviewAccessService::COOKIE_NAME, $token);
        $this->assertSame(200, $middleware->handle($authedCookieReq, $okNext)->getStatusCode());
    }

    public function test_verify_token_endpoint_and_secret_rotation_revoke_tokens(): void
    {
        config(['app.preview_access_secret' => 'first-secret-for-tests']);
        $service = app(PreviewAccessService::class);
        $controller = new WaitlistController();

        $token = $service->issueToken(3600);
        $ok = $controller->verifyToken(Request::create('/api/v1/waitlist/verify-token', 'POST', ['token' => $token]));
        $this->assertSame(200, $ok->getStatusCode());
        $this->assertTrue($ok->getData(true)['valid']);
        $this->assertSame((int) explode('.', $token)[0], $ok->getData(true)['expires_at']);

        foreach ([null, '', 'garbage', (time() + 3600) . '.' . str_repeat('a', 64), '1000000000.' . str_repeat('b', 64)] as $bad) {
            $res = $controller->verifyToken(Request::create('/api/v1/waitlist/verify-token', 'POST', ['token' => $bad]));
            $this->assertSame(403, $res->getStatusCode());
            $this->assertFalse($res->getData(true)['valid']);
        }

        // Rotating the dedicated secret revokes previously issued tokens.
        config(['app.preview_access_secret' => 'rotated-secret-for-tests']);
        $this->assertFalse($service->verifyToken($token));
        $this->assertTrue($service->verifyToken($service->issueToken(3600)));
    }
}
