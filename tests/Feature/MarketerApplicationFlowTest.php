<?php

namespace Tests\Feature;

use App\Http\Controllers\Api\V1\MarketerApplicationController;
use App\Http\Middleware\MarketerAuthenticated;
use App\Mail\MarketerApplicationReceived;
use App\Models\Admin;
use App\Models\MarketerApplication;
use App\Models\MarketerPhoneVerification;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * A marketer's name and payout number must come from verification proof, not
 * from fields the applicant can edit. Manual entry is a deliberate fallback
 * that only opens after three failed checks, and it is enforced server-side.
 */
class MarketerApplicationFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->unsignedBigInteger('role_id')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('addon_settings')) {
            Schema::create('addon_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key_name')->nullable();
                $table->string('settings_type')->nullable();
                $table->string('mode')->nullable();
                $table->text('live_values')->nullable();
                $table->text('test_values')->nullable();
                $table->timestamps();
            });
        }

        // Run the real migrations so the schema under test cannot drift from
        // what deployment will actually create.
        foreach ([
            '2026_11_21_000006_create_marketer_applications_table.php',
            '2026_11_25_000001_add_marketer_phone_verification.php',
        ] as $file) {
            $migration = require database_path('migrations/' . $file);
            $migration->up();
        }
    }

    private function configureProviders(): void
    {
        // Batch inserts bind by position, so every row must declare the same
        // keys in the same order.
        $columns = ['settings_type', 'key_name', 'mode', 'live_values', 'test_values'];
        DB::table('addon_settings')->insert([
            array_combine($columns, [
                'payment_config',
                'paystack',
                'test',
                null,
                json_encode(['secret_key' => 'test-secret']),
            ]),
            array_combine($columns, [
                'sms_config',
                'intekhost',
                'live',
                json_encode([
                    'status' => 1,
                    'api_key' => 'sms-key',
                    'api_url' => 'https://sms.test/send',
                    'sender_id' => 226,
                ]),
                null,
            ]),
        ]);
    }

    private function fakeResolution(bool $success): void
    {
        Http::fake([
            '*api.paystack.co/bank/resolve*' => $success
                ? Http::response([
                    'status' => true,
                    'data' => ['account_name' => 'Kwame Mensah', 'account_number' => '0241234567'],
                ], 200)
                : Http::response(['status' => false, 'message' => 'Account could not be resolved'], 422),
            '*sms.test*' => Http::response(['status' => 'ok'], 200),
        ]);
    }

    private function applicationPayload(array $overrides = []): array
    {
        return array_merge([
            'email' => 'applicant@example.com',
            'region' => 'Greater Accra',
            'district' => 'Accra',
            'momo_network' => 'MTN',
        ], $overrides);
    }

    public function test_phone_verification_migration_is_reversible(): void
    {
        $this->assertTrue(Schema::hasTable('marketer_phone_verifications'));
        $this->assertTrue(Schema::hasColumn('marketer_applications', 'phone_verified'));
        $this->assertTrue(Schema::hasColumn('marketer_applications', 'phone_verification_id'));

        $migration = require database_path('migrations/2026_11_25_000001_add_marketer_phone_verification.php');
        $migration->down();

        $this->assertFalse(Schema::hasTable('marketer_phone_verifications'));
        $this->assertFalse(Schema::hasColumn('marketer_applications', 'phone_verified'));
    }

    public function test_verified_name_and_number_are_used_for_the_application(): void
    {
        Mail::fake();
        $this->configureProviders();
        $this->fakeResolution(true);

        $verification = $this->postJson('/api/v1/marketer-applications/verify-payout', [
            'account_number' => '0241234567',
            'momo_network' => 'MTN',
        ]);
        $verification->assertStatus(200)->assertJsonPath('verified', true);
        $token = $verification->json('token');
        $this->assertNotEmpty($token);

        $this->postJson('/api/v1/marketer-applications', $this->applicationPayload([
            'verification_token' => $token,
            'full_name' => 'Someone Else',
            'phone' => '0200000000',
        ]))->assertStatus(201);

        $this->assertDatabaseHas('marketer_applications', [
            'full_name' => 'Kwame Mensah',
            'phone' => '0241234567',
            'phone_verified' => 1,
            'momo_network' => 'MTN',
            'status' => 'pending',
        ]);

        Mail::assertSent(MarketerApplicationReceived::class, function ($mail) {
            return $mail->hasTo('applicant@example.com')
                && $mail->application->full_name === 'Kwame Mensah';
        });

        Http::assertSent(function (HttpRequest $request) {
            return str_contains($request->url(), 'sms.test')
                && str_contains($request['recipients'][0], '0241234567')
                && str_contains($request['message'], 'being worked on');
        });
    }

    public function test_three_failures_unlock_manual_name_entry(): void
    {
        Mail::fake();
        $this->configureProviders();
        $this->fakeResolution(false);

        $tokens = [];
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            $response = $this->postJson('/api/v1/marketer-applications/verify-payout', [
                'account_number' => '0241234567',
                'momo_network' => 'MTN',
            ]);
            $response->assertStatus(422)
                ->assertJsonPath('attempts', $attempt)
                ->assertJsonPath('manual_allowed', $attempt >= 3);
            $tokens[] = $response->json('token');
        }

        $this->assertNull($tokens[0]);
        $this->assertNull($tokens[1]);
        $this->assertNotEmpty($tokens[2]);
        $this->assertSame(3, MarketerPhoneVerification::first()->attempts);

        $this->postJson('/api/v1/marketer-applications', $this->applicationPayload([
            'verification_token' => $tokens[2],
            'full_name' => 'Manually Typed Name',
            'phone' => '0200000000',
        ]))->assertStatus(201);

        $application = MarketerApplication::first();
        $this->assertSame('Manually Typed Name', $application->full_name);
        $this->assertSame('0241234567', $application->phone);
        $this->assertFalse((bool) $application->phone_verified);
    }

    public function test_manual_entry_is_rejected_before_three_failures(): void
    {
        $this->configureProviders();
        $this->fakeResolution(false);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $this->postJson('/api/v1/marketer-applications/verify-payout', [
                'account_number' => '0241234567',
                'momo_network' => 'MTN',
            ])->assertStatus(422)->assertJsonPath('manual_allowed', false);
        }

        $verification = MarketerPhoneVerification::first();
        $controller = new MarketerApplicationController();
        $sign = new \ReflectionMethod($controller, 'signVerificationToken');
        $sign->setAccessible(true);
        $earlyManualToken = $sign->invoke($controller, $verification, 'manual');

        $this->postJson('/api/v1/marketer-applications', $this->applicationPayload([
            'verification_token' => $earlyManualToken,
            'full_name' => 'Too Early',
        ]))->assertStatus(422)->assertJsonPath('errors.0.code', 'verification');

        $this->assertSame(0, MarketerApplication::count());
    }

    public function test_payout_cannot_use_a_different_number_than_the_one_verified(): void
    {
        $admin = Admin::create([
            'email' => 'marketer@example.com',
            'password' => Hash::make('current-password'),
            'role_id' => 5,
        ]);

        MarketerApplication::create([
            'full_name' => 'Kwame Mensah',
            'email' => 'marketer@example.com',
            'phone' => '0241234567',
            'region' => 'Greater Accra',
            'status' => MarketerApplication::STATUS_APPROVED,
            'created_admin_id' => $admin->id,
        ]);

        $this->withoutMiddleware(MarketerAuthenticated::class)
            ->actingAs($admin, 'admin')
            ->from('/marketer/payouts/request')
            ->post('/marketer/payouts', [
                'network' => 'MTN',
                'account_name' => 'Kwame Mensah',
                'account_number' => '0200000000',
                'country' => 'GH',
                'current_password' => 'current-password',
            ])
            ->assertRedirect('/marketer/payouts/request')
            ->assertSessionHasErrors('account_number');
    }

    public function test_tampered_token_is_rejected(): void
    {
        $this->configureProviders();
        $this->fakeResolution(true);

        $token = $this->postJson('/api/v1/marketer-applications/verify-payout', [
            'account_number' => '0241234567',
            'momo_network' => 'MTN',
        ])->json('token');
        $this->assertNotEmpty($token);

        $parts = explode('.', $token);
        $parts[1][0] = $parts[1][0] === 'a' ? 'b' : 'a';
        $tampered = implode('.', $parts);

        $this->postJson('/api/v1/marketer-applications', $this->applicationPayload([
            'verification_token' => $tampered,
        ]))->assertStatus(422)->assertJsonPath('errors.0.code', 'verification');

        $this->assertSame(0, MarketerApplication::count());
    }
}
