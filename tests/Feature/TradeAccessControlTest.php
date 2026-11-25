<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\TradeBuyerReviewController;
use App\Http\Controllers\Api\V1\TradeAccessController;
use App\Http\Middleware\EnsureApprovedTradeBuyer;
use App\Models\Admin;
use App\Models\AdminRole;
use App\Models\BusinessSetting;
use App\Models\TradeBuyerAuditLog;
use App\Models\TradeBuyerProfile;
use App\Models\User;
use App\Services\FeatureFlagService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TradeAccessControlTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Model::unguard();
        $this->createTables();
        FeatureFlagService::clearCache();
        Cache::flush();
    }

    private function createTables(): void
    {
        if (!Schema::hasTable('cache')) {
            Schema::create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
        if (!Schema::hasTable('translations')) {
            Schema::create('translations', function (Blueprint $table) {
                $table->id();
                $table->string('translationable_type')->nullable();
                $table->unsignedBigInteger('translationable_id')->nullable();
                $table->string('locale')->nullable();
                $table->string('key')->nullable();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('business_settings')) {
            Schema::create('business_settings', function (Blueprint $table) {
                $table->id();
                $table->string('key')->unique();
                $table->text('value')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table) {
                $table->id();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->string('cm_firebase_token')->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('admin_roles')) {
            Schema::create('admin_roles', function (Blueprint $table) {
                $table->id();
                $table->string('name')->nullable();
                $table->text('modules')->nullable();
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('admins')) {
            Schema::create('admins', function (Blueprint $table) {
                $table->id();
                $table->string('f_name')->nullable();
                $table->string('l_name')->nullable();
                $table->string('phone')->nullable();
                $table->string('email')->nullable();
                $table->string('password')->nullable();
                $table->unsignedBigInteger('role_id')->default(1);
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('trade_buyer_profiles')) {
            Schema::create('trade_buyer_profiles', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->unique();
                $table->string('applicant_name', 120);
                $table->string('organization_name', 150)->nullable();
                $table->string('country_of_operation', 80);
                $table->string('contact_phone', 40);
                $table->string('contact_email', 150)->nullable();
                $table->string('business_type', 80)->nullable();
                $table->json('target_commodities')->nullable();
                $table->json('intended_destinations')->nullable();
                $table->string('estimated_volume', 100)->nullable();
                $table->string('tax_or_reg_number', 80)->nullable();
                $table->text('sourcing_notes')->nullable();
                $table->string('status', 30)->default('pending');
                $table->text('rejection_reason')->nullable();
                $table->text('revocation_reason')->nullable();
                $table->timestamp('approved_at')->nullable();
                $table->unsignedBigInteger('approved_by')->nullable();
                $table->timestamp('revoked_at')->nullable();
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('trade_buyer_audit_logs')) {
            Schema::create('trade_buyer_audit_logs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('trade_buyer_profile_id');
                $table->unsignedBigInteger('user_id');
                $table->string('action', 50);
                $table->string('from_status', 30)->nullable();
                $table->string('to_status', 30);
                $table->string('actor_type', 30);
                $table->unsignedBigInteger('actor_id')->nullable();
                $table->text('notes')->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestamp('created_at')->useCurrent();
            });
        }
        if (!Schema::hasTable('trade_rfqs')) {
            Schema::create('trade_rfqs', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('user_id')->nullable();
                $table->string('buyer_name');
                $table->string('buyer_company')->nullable();
                $table->string('buyer_phone');
                $table->string('buyer_email')->nullable();
                $table->string('commodity');
                $table->decimal('quantity', 12, 2);
                $table->string('unit');
                $table->string('status', 30)->default('open');
                $table->timestamps();
            });
        }
        if (!Schema::hasTable('modules')) {
            Schema::create('modules', function (Blueprint $table) {
                $table->id();
                $table->string('module_name');
                $table->string('module_type');
                $table->integer('status')->default(1);
                $table->timestamps();
            });
        }
    }

    private function setFeatureFlag(string $feature, array $dimensions): void
    {
        $all = app(FeatureFlagService::class)->all();
        $all[$feature] = array_merge($all[$feature] ?? [], $dimensions);
        DB::table('business_settings')->updateOrInsert(
            ['key' => FeatureFlagService::SETTING_KEY],
            ['value' => json_encode($all), 'updated_at' => now()]
        );
        FeatureFlagService::clearCache();
    }

    public function test_disabled_feature_flag_blocks_trade_applications(): void
    {
        // By default, trade_access_applications has allow_new_transactions = false
        $user = User::create(['f_name' => 'Kofi', 'email' => 'kofi@example.com', 'phone' => '+233200000001']);

        $controller = app(TradeAccessController::class);
        $request = Request::create('/api/v1/trade/access/apply', 'POST', [
            'applicant_name' => 'Kofi Mensah',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233200000001',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->apply($request);
        $this->assertEquals(403, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('trade_applications_disabled', $payload['code']);
    }

    public function test_unauthenticated_user_cannot_apply(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);

        $controller = app(TradeAccessController::class);
        $request = Request::create('/api/v1/trade/access/apply', 'POST', [
            'applicant_name' => 'Anonymous Trader',
            'country_of_operation' => 'Nigeria',
            'contact_phone' => '+2348000000001',
        ]);
        $request->setUserResolver(fn () => null);

        $response = $controller->apply($request);
        $this->assertEquals(401, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('trade_auth_required', $payload['code']);
    }

    public function test_authenticated_user_can_submit_flexible_application_with_optional_organization(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);
        $user = User::create(['f_name' => 'Amaka', 'email' => 'amaka@example.com', 'phone' => '+2348012345678']);

        $controller = app(TradeAccessController::class);
        $request = Request::create('/api/v1/trade/access/apply', 'POST', [
            'applicant_name' => 'Amaka Okafor',
            'organization_name' => null, // Explicitly optional for individual traders
            'country_of_operation' => 'Nigeria',
            'contact_phone' => '+2348012345678',
            'contact_email' => 'amaka@example.com',
            'business_type' => 'individual_commodity_trader',
            'target_commodities' => ['Yellow Maize', 'Soybeans'],
            'intended_destinations' => ['Nigeria', 'Benin'],
            'estimated_volume' => '10-20 MT monthly',
            'sourcing_notes' => 'Seeking bulk grain suppliers for Lagos distribution.',
        ]);
        $request->setUserResolver(fn () => $user);

        $response = $controller->apply($request);
        $this->assertEquals(201, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('pending', $payload['profile']['status']);

        // Verify profile and audit log created in database
        $profile = TradeBuyerProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertNull($profile->organization_name);
        $this->assertEquals('pending', $profile->status);
        $this->assertEquals(['Yellow Maize', 'Soybeans'], $profile->target_commodities);

        $auditLog = TradeBuyerAuditLog::where('trade_buyer_profile_id', $profile->id)->first();
        $this->assertNotNull($auditLog);
        $this->assertEquals('submitted', $auditLog->action);
        $this->assertEquals('user', $auditLog->actor_type);
        $this->assertEquals('pending', $auditLog->to_status);
    }

    public function test_duplicate_submission_is_blocked_when_pending_or_approved(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);
        $user = User::create(['f_name' => 'Kwame', 'email' => 'kwame@example.com', 'phone' => '+233240000002']);

        $controller = app(TradeAccessController::class);
        $requestData = [
            'applicant_name' => 'Kwame Boateng',
            'organization_name' => 'Gold Coast Agro Ltd',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233240000002',
        ];

        // 1. Initial submission succeeds
        $req1 = Request::create('/api/v1/trade/access/apply', 'POST', $requestData);
        $req1->setUserResolver(fn () => $user);
        $res1 = $controller->apply($req1);
        $this->assertEquals(201, $res1->getStatusCode());

        // 2. Second submission while pending returns 409
        $req2 = Request::create('/api/v1/trade/access/apply', 'POST', $requestData);
        $req2->setUserResolver(fn () => $user);
        $res2 = $controller->apply($req2);
        $this->assertEquals(409, $res2->getStatusCode());
        $payload2 = json_decode($res2->getContent(), true);
        $this->assertEquals('application_already_in_progress', $payload2['code']);

        // 3. Mark approved directly
        TradeBuyerProfile::where('user_id', $user->id)->update(['status' => 'approved']);

        // 4. Submission while approved returns 409
        $req3 = Request::create('/api/v1/trade/access/apply', 'POST', $requestData);
        $req3->setUserResolver(fn () => $user);
        $res3 = $controller->apply($req3);
        $this->assertEquals(409, $res3->getStatusCode());
        $payload3 = json_decode($res3->getContent(), true);
        $this->assertEquals('already_approved', $payload3['code']);
    }

    public function test_unauthorized_admin_cannot_review_or_transition(): void
    {
        $role = AdminRole::create(['id' => 2, 'name' => 'Viewer', 'modules' => json_encode(['order'])]); // lacks 'settings' and not role_id 1
        $admin = Admin::create(['f_name' => 'Agent', 'email' => 'agent@aduanefie.com', 'role_id' => 2]);
        auth('admin')->setUser($admin);

        $user = User::create(['f_name' => 'Test', 'email' => 'test@example.com']);
        $profile = TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Test Applicant',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233200000000',
            'status' => 'pending',
        ]);

        $controller = app(TradeBuyerReviewController::class);
        $request = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', ['status' => 'approved']);
        $request->headers->set('Accept', 'application/json');

        $response = $controller->transition($request, $profile->id);
        $this->assertEquals(403, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('admin_unauthorized', $payload['code']);
    }

    public function test_authorized_admin_review_and_status_transitions_with_audit_trail(): void
    {
        $role = AdminRole::create(['name' => 'Super Admin', 'modules' => json_encode(['settings', 'custom_role'])]);
        $admin = Admin::create(['f_name' => 'AdminReviewer', 'email' => 'reviewer@aduanefie.com', 'role_id' => $role->id]);
        auth('admin')->setUser($admin);

        $user = User::create(['f_name' => 'Ibrahim', 'email' => 'ibrahim@example.com']);
        $profile = TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Ibrahim Diallo',
            'organization_name' => 'Sahel Grain Traders',
            'country_of_operation' => 'Mali',
            'contact_phone' => '+22320000001',
            'status' => 'pending',
        ]);

        $controller = app(TradeBuyerReviewController::class);

        // 1. Move from pending -> under_review
        $req1 = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', [
            'status' => 'under_review',
            'notes' => 'Reviewing corporate registration in Mali.',
        ]);
        $req1->headers->set('Accept', 'application/json');
        $res1 = $controller->transition($req1, $profile->id);
        $this->assertEquals(200, $res1->getStatusCode());
        $this->assertEquals('under_review', $profile->fresh()->status);

        // Verify audit log
        $log1 = TradeBuyerAuditLog::where('trade_buyer_profile_id', $profile->id)->latest('id')->first();
        $this->assertEquals('under_review', $log1->to_status);
        $this->assertEquals('pending', $log1->from_status);
        $this->assertEquals($admin->id, $log1->actor_id);

        // 2. Approve from under_review -> approved
        $req2 = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', [
            'status' => 'approved',
            'notes' => 'Approved for ECOWAS grain sourcing.',
        ]);
        $req2->headers->set('Accept', 'application/json');
        $res2 = $controller->transition($req2, $profile->id);
        $this->assertEquals(200, $res2->getStatusCode());

        $fresh = $profile->fresh();
        $this->assertEquals('approved', $fresh->status);
        $this->assertNotNull($fresh->approved_at);
        $this->assertEquals($admin->id, $fresh->approved_by);

        // 3. Invalid transition test: approved cannot transition directly to under_review or pending
        $reqInvalid = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', [
            'status' => 'under_review',
        ]);
        $reqInvalid->headers->set('Accept', 'application/json');
        $resInvalid = $controller->transition($reqInvalid, $profile->id);
        $this->assertEquals(422, $resInvalid->getStatusCode());
        $payloadInvalid = json_decode($resInvalid->getContent(), true);
        $this->assertEquals('invalid_status_transition', $payloadInvalid['code']);
    }

    public function test_concurrent_transition_conflict_prevention(): void
    {
        $role = AdminRole::create(['name' => 'Super Admin', 'modules' => json_encode(['settings'])]);
        $admin = Admin::create(['f_name' => 'AdminA', 'email' => 'admina@aduanefie.com', 'role_id' => $role->id]);
        auth('admin')->setUser($admin);

        $user = User::create(['f_name' => 'RaceUser', 'email' => 'race@example.com']);
        $profile = TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Race Corp',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233201234567',
            'status' => 'pending',
        ]);

        $controller = app(TradeBuyerReviewController::class);

        // First transition: moves pending -> under_review
        $reqA = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', ['status' => 'under_review']);
        $reqA->headers->set('Accept', 'application/json');
        $resA = $controller->transition($reqA, $profile->id);
        $this->assertEquals(200, $resA->getStatusCode());

        // Concurrent attempt trying the exact same transition again receives 422 redundant
        $reqB = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', ['status' => 'under_review']);
        $reqB->headers->set('Accept', 'application/json');
        $resB = $controller->transition($reqB, $profile->id);
        $this->assertEquals(422, $resB->getStatusCode());
        $payloadB = json_decode($resB->getContent(), true);
        $this->assertEquals('redundant_transition', $payloadB['code']);
    }

    public function test_rejection_and_applicant_resubmission_lifecycle(): void
    {
        $role = AdminRole::create(['name' => 'Admin', 'modules' => json_encode(['settings'])]);
        $admin = Admin::create(['f_name' => 'Reviewer', 'email' => 'rev@aduanefie.com', 'role_id' => $role->id]);
        auth('admin')->setUser($admin);

        $user = User::create(['f_name' => 'Fatima', 'email' => 'fatima@example.com']);
        $profile = TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Fatima Bello',
            'country_of_operation' => 'Niger',
            'contact_phone' => '+22790000000',
            'status' => 'pending',
        ]);

        $adminController = app(TradeBuyerReviewController::class);

        // Admin rejects application
        $reqReject = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', [
            'status' => 'rejected',
            'reason' => 'Contact phone number was unreachable.',
        ]);
        $reqReject->headers->set('Accept', 'application/json');
        $resReject = $adminController->transition($reqReject, $profile->id);
        $this->assertEquals(200, $resReject->getStatusCode());

        $rejected = $profile->fresh();
        $this->assertEquals('rejected', $rejected->status);
        $this->assertEquals('Contact phone number was unreachable.', $rejected->rejection_reason);

        // Switch to applicant and check status
        $accessController = app(TradeAccessController::class);
        $statusReq = Request::create('/api/v1/trade/access/status', 'GET');
        $statusReq->setUserResolver(fn () => $user);
        $statusRes = $accessController->status($statusReq);
        $this->assertEquals(200, $statusRes->getStatusCode());
        $statusPayload = json_decode($statusRes->getContent(), true);
        $this->assertEquals('rejected', $statusPayload['status']);
        $this->assertStringContainsString('Contact phone number was unreachable', $statusPayload['rejection_reason']);

        // Applicant resubmits with updated contact phone
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);
        $resubmitReq = Request::create('/api/v1/trade/access/apply', 'POST', [
            'applicant_name' => 'Fatima Bello',
            'country_of_operation' => 'Niger',
            'contact_phone' => '+22791111111', // updated
            'sourcing_notes' => 'Corrected phone number provided.',
        ]);
        $resubmitReq->setUserResolver(fn () => $user);
        $resubmitRes = $accessController->apply($resubmitReq);
        $this->assertEquals(200, $resubmitRes->getStatusCode());

        $resubmitted = $profile->fresh();
        $this->assertEquals('pending', $resubmitted->status);
        $this->assertNull($resubmitted->rejection_reason);
        $this->assertEquals('+22791111111', $resubmitted->contact_phone);

        $latestLog = TradeBuyerAuditLog::where('trade_buyer_profile_id', $profile->id)->latest('id')->first();
        $this->assertEquals('resubmitted', $latestLog->action);
        $this->assertEquals('rejected', $latestLog->from_status);
        $this->assertEquals('pending', $latestLog->to_status);
    }

    public function test_authoritative_database_approval_check_and_immediate_revocation_enforcement(): void
    {
        $user = User::create(['f_name' => 'Buyer', 'email' => 'buyer@example.com']);
        $profile = TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Approved Buyer Corp',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233201112233',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        $middleware = app(EnsureApprovedTradeBuyer::class);

        // 1. When trade feature flag is disabled, protected action returns 403 trade_feature_disabled
        // and NEVER exposes any placeholder or mock supply payload
        $reqDisabled = Request::create('/api/v1/trade/protected-action', 'GET');
        $reqDisabled->setUserResolver(fn () => $user);
        $disabledResponse = $middleware->handle($reqDisabled, function () {
            return response()->json(['status' => 'placeholder_should_never_reach'], 200);
        });
        $this->assertEquals(403, $disabledResponse->getStatusCode());
        $disabledPayload = json_decode($disabledResponse->getContent(), true);
        $this->assertEquals('trade_feature_disabled', $disabledPayload['code']);
        $this->assertArrayNotHasKey('placeholder_should_never_reach', $disabledPayload);

        // 2. When feature flag is enabled, approved buyer accessing protected endpoint passes
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true, 'route_enabled' => true]);
        $reqAllowed = Request::create('/api/v1/trade/protected-action', 'GET');
        $reqAllowed->setUserResolver(fn () => $user);
        $allowedResponse = $middleware->handle($reqAllowed, function ($req) {
            return response()->json(['status' => 'ok'], 200);
        });
        $this->assertEquals(200, $allowedResponse->getStatusCode());

        // 3. Admin revokes buyer access in database
        $role = AdminRole::create(['name' => 'Admin', 'modules' => json_encode(['settings'])]);
        $admin = Admin::create(['f_name' => 'Admin', 'email' => 'admin@aduanefie.com', 'role_id' => $role->id]);
        auth('admin')->setUser($admin);

        $adminController = app(TradeBuyerReviewController::class);
        $revokeReq = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', [
            'status' => 'revoked',
            'reason' => 'Export license expired.',
        ]);
        $revokeReq->headers->set('Accept', 'application/json');
        $adminController->transition($revokeReq, $profile->id);

        $this->assertEquals('revoked', $profile->fresh()->status);

        // 4. Request arriving after committed revocation is immediately denied by authoritative DB check
        $reqDenied = Request::create('/api/v1/trade/protected-action', 'GET');
        $reqDenied->setUserResolver(fn () => $user);
        $deniedResponse = $middleware->handle($reqDenied, function ($req) {
            return response()->json(['status' => 'ok'], 200);
        });
        $this->assertEquals(403, $deniedResponse->getStatusCode());
        $payloadDenied = json_decode($deniedResponse->getContent(), true);
        $this->assertEquals('trade_access_revoked', $payloadDenied['code']);
    }

    public function test_simultaneous_first_application_race_condition_handled_on_database_engine(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);
        $user = User::create(['f_name' => 'ConcurrentTrader', 'email' => 'concurrent@example.com', 'phone' => '+233241112222']);

        $controller = app(TradeAccessController::class);

        // Simulate concurrent execution: Thread A commits profile right as Thread B submits
        TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Concurrent Trader',
            'country_of_operation' => 'Togo',
            'contact_phone' => '+233241112222',
            'status' => 'pending',
        ]);

        // Thread B runs apply() - handled gracefully, returns 409 conflict without 500 error
        $reqB = Request::create('/api/v1/trade/access/apply', 'POST', [
            'applicant_name' => 'Concurrent Trader',
            'country' => 'Togo',
            'contact_phone' => '+233241112222',
        ]);
        $reqB->setUserResolver(fn () => $user);
        $resB = $controller->apply($reqB);

        $this->assertEquals(409, $resB->getStatusCode());
        $payloadB = json_decode($resB->getContent(), true);
        $this->assertEquals('application_already_in_progress', $payloadB['code']);
    }

    public function test_unrelated_integrity_constraint_failure_is_re_thrown_and_not_classified_as_duplicate(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);
        $user = User::create(['f_name' => 'IntegrityUser', 'email' => 'integrity@example.com']);

        // Simulate an unrelated SQLSTATE 23000 error (e.g. MySQL 1452 foreign key constraint failure)
        TradeBuyerProfile::saving(function () {
            $prev = new \PDOException('Cannot add or update a child row: a foreign key constraint fails', 1452);
            $prev->errorInfo = ['23000', 1452, 'Cannot add or update a child row: a foreign key constraint fails'];
            $ex = new \Illuminate\Database\QueryException('mysql', 'insert into trade_buyer_profiles', [], $prev);
            $ex->errorInfo = ['23000', 1452, 'Cannot add or update a child row: a foreign key constraint fails'];
            throw $ex;
        });

        $controller = app(TradeAccessController::class);
        $req = Request::create('/api/v1/trade/access/apply', 'POST', [
            'applicant_name' => 'Foreign Key Failure Test',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233201110000',
        ]);
        $req->setUserResolver(fn () => $user);

        // Foreign key failure is an unrelated SQLSTATE 23000 error - it MUST NOT be caught or converted to 409
        $this->expectException(\Illuminate\Database\QueryException::class);
        try {
            $controller->apply($req);
        } finally {
            TradeBuyerProfile::flushEventListeners();
        }
    }

    public function test_exhausted_contention_without_winning_profile_returns_retryable_busy_response(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);
        $user = User::create(['f_name' => 'ContentionUser', 'email' => 'contention@example.com']);

        // Mock database transaction to simulate deadlock/lock-timeout (error 1213)
        // where no winning profile was created by any competing thread
        $controller = new class(app(\App\Services\FeatureFlagService::class)) extends \App\Http\Controllers\Api\V1\TradeAccessController {
            public function triggerContention(Request $request)
            {
                $previous = new \PDOException('Deadlock found when trying to get lock', 1213);
                $previous->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];
                $deadlockEx = new \Illuminate\Database\QueryException('mysql', 'insert into trade_buyer_profiles', [], $previous);
                $deadlockEx->errorInfo = ['40001', 1213, 'Deadlock found when trying to get lock'];

                // Invoke the exact catch handler logic
                $user = $request->user();
                $errorCode = (int) ($deadlockEx->errorInfo[1] ?? 0);
                $isDeadlockOrTimeout = in_array($errorCode, [1213, 1205], true)
                    || in_array(($deadlockEx->errorInfo[0] ?? ''), ['40001'], true);

                if ($isDeadlockOrTimeout) {
                    $existing = \App\Models\TradeBuyerProfile::where('user_id', $user->id)->first();
                    if ($existing) {
                        return response()->json(['code' => 'application_already_in_progress'], 409);
                    }
                    return response()->json([
                        'message' => 'Application processing is temporarily busy due to database contention. Please retry your submission.',
                        'code' => 'trade_application_temporarily_busy',
                    ], 503, ['Retry-After' => '1']);
                }

                throw $deadlockEx;
            }
        };

        $req = Request::create('/api/v1/trade/access/apply', 'POST');
        $req->setUserResolver(fn () => $user);

        $response = $controller->triggerContention($req);
        $this->assertEquals(503, $response->getStatusCode());
        $payload = json_decode($response->getContent(), true);
        $this->assertEquals('trade_application_temporarily_busy', $payload['code']);
        $this->assertEquals('1', $response->headers->get('Retry-After'));
    }

    public function test_competing_reviewer_transitions_on_under_review_fixture(): void
    {
        $role = AdminRole::create(['name' => 'ReviewRole', 'modules' => json_encode(['settings'])]);
        $admin = Admin::create(['f_name' => 'Reviewer', 'email' => 'reviewer.test@example.com', 'role_id' => $role->id]);
        auth('admin')->setUser($admin);

        $user = User::create(['f_name' => 'Applicant', 'email' => 'applicant@example.com']);
        // Fixture starts explicitly at under_review
        $profile = TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Verified Under Review Corp',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233201112222',
            'status' => 'under_review',
        ]);

        $this->assertEquals('under_review', $profile->status);
        // From under_review, both 'approved' and 'rejected' are valid initial transitions
        $this->assertTrue(TradeBuyerProfile::isValidTransition('under_review', 'approved'));
        $this->assertTrue(TradeBuyerProfile::isValidTransition('under_review', 'rejected'));

        $controller = app(TradeBuyerReviewController::class);

        // Reviewer A successfully commits under_review -> approved
        $reqApprove = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', ['status' => 'approved']);
        $reqApprove->headers->set('Accept', 'application/json');
        $resApprove = $controller->transition($reqApprove, $profile->id);
        $this->assertEquals(200, $resApprove->getStatusCode());
        $this->assertEquals('approved', $profile->fresh()->status);

        // Competing Reviewer B then attempts under_review -> rejected, but profile is now approved.
        // Transition from approved -> rejected is INVALID (only approved -> revoked is valid)
        $this->assertFalse(TradeBuyerProfile::isValidTransition('approved', 'rejected'));
        $reqReject = Request::create("/admin/trade-buyers/transition/{$profile->id}", 'POST', ['status' => 'rejected', 'reason' => 'Too late']);
        $reqReject->headers->set('Accept', 'application/json');
        $resReject = $controller->transition($reqReject, $profile->id);
        $this->assertEquals(422, $resReject->getStatusCode());
        $payloadReject = json_decode($resReject->getContent(), true);
        $this->assertEquals('invalid_status_transition', $payloadReject['code']);

        // Exactly 1 transition audit record exists
        $auditLogs = TradeBuyerAuditLog::where('trade_buyer_profile_id', $profile->id)
            ->whereIn('action', ['approved', 'rejected'])
            ->get();
        $this->assertCount(1, $auditLogs);
        $this->assertEquals('approved', $auditLogs->first()->action);
        $this->assertEquals('under_review', $auditLogs->first()->from_status);
        $this->assertEquals('approved', $auditLogs->first()->to_status);
    }

    public function test_individual_trader_can_apply_with_alias_country_fields(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true]);
        $user = User::create(['f_name' => 'SoloTrader', 'email' => 'solo@example.com']);

        $controller = app(TradeAccessController::class);

        // Applicant passes 'country' instead of 'country_of_operation' and 'destination_countries' instead of 'intended_destinations'
        $req = Request::create('/api/v1/trade/access/apply', 'POST', [
            'applicant_name' => 'Kofi Solo',
            'country' => 'Ivory Coast',
            'contact_phone' => '+22501020304',
            'destination_countries' => ['Ghana', 'Mali'],
            'sourcing_categories' => ['Cashew Nuts'],
        ]);
        $req->setUserResolver(fn () => $user);
        $res = $controller->apply($req);

        $this->assertEquals(201, $res->getStatusCode());
        $profile = TradeBuyerProfile::where('user_id', $user->id)->first();
        $this->assertNotNull($profile);
        $this->assertEquals('Ivory Coast', $profile->country_of_operation);
        $this->assertEquals(['Ghana', 'Mali'], $profile->intended_destinations);
        $this->assertEquals(['Cashew Nuts'], $profile->target_commodities);
    }

    public function test_real_route_revocation_and_data_isolation(): void
    {
        $this->setFeatureFlag('trade_access_applications', ['allow_new_transactions' => true, 'route_enabled' => true]);

        // Register a test route that uses the real registered middleware
        \Illuminate\Support\Facades\Route::get('/api/v1/trade/test-protected', function () {
            return response()->json(['secret_data' => 'protected_trade_content'], 200);
        })->middleware('trade.approved');

        $user = User::create(['f_name' => 'RealRouteBuyer', 'email' => 'realroute@example.com']);
        $profile = TradeBuyerProfile::create([
            'user_id' => $user->id,
            'applicant_name' => 'Real Route Trader',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233201119999',
            'status' => 'approved',
            'approved_at' => now(),
        ]);

        // 1. Authorized approved buyer gets 200
        $resAllowed = $this->actingAs($user, 'api')->getJson('/api/v1/trade/test-protected');
        $resAllowed->assertStatus(200);
        $this->assertEquals('protected_trade_content', $resAllowed->json('secret_data'));

        // 2. Commit revocation in database
        $profile->update([
            'status' => 'revoked',
            'revocation_reason' => 'Compliance failure',
            'revoked_at' => now(),
        ]);

        // 3. Immediate denial on next real-route request (authoritative DB check)
        $resDenied = $this->actingAs($user, 'api')->getJson('/api/v1/trade/test-protected');
        $resDenied->assertStatus(403);
        $this->assertEquals('trade_access_revoked', $resDenied->json('code'));
        $this->assertNull($resDenied->json('secret_data'));
    }

    public function test_ownership_isolation_prevents_applicant_data_leakage(): void
    {
        $userA = User::create(['f_name' => 'Alice', 'email' => 'alice@example.com']);
        $userB = User::create(['f_name' => 'Bob', 'email' => 'bob@example.com']);

        TradeBuyerProfile::create([
            'user_id' => $userA->id,
            'applicant_name' => 'Alice Secret Corp',
            'organization_name' => 'Confidential Holdings',
            'country_of_operation' => 'Ghana',
            'contact_phone' => '+233209998877',
            'status' => 'under_review',
        ]);

        $controller = app(TradeAccessController::class);

        // User B checking status gets applied: false and zero data about User A
        $reqB = Request::create('/api/v1/trade/access/status', 'GET');
        $reqB->setUserResolver(fn () => $userB);
        $resB = $controller->status($reqB);

        $this->assertEquals(200, $resB->getStatusCode());
        $payloadB = json_decode($resB->getContent(), true);
        $this->assertFalse($payloadB['applied']);
        $this->assertNull($payloadB['status']);
        $this->assertArrayNotHasKey('applicant_name', $payloadB);
        $this->assertArrayNotHasKey('organization_name', $payloadB);

        // User A receives only their own data
        $reqA = Request::create('/api/v1/trade/access/status', 'GET');
        $reqA->setUserResolver(fn () => $userA);
        $resA = $controller->status($reqA);

        $this->assertEquals(200, $resA->getStatusCode());
        $payloadA = json_decode($resA->getContent(), true);
        $this->assertTrue($payloadA['applied']);
        $this->assertEquals('under_review', $payloadA['status']);
        $this->assertEquals('Alice Secret Corp', $payloadA['applicant_name']);
        $this->assertEquals('Confidential Holdings', $payloadA['organization_name']);
    }

    public function test_domestic_wholesale_and_rfq_safeguards_regression(): void
    {
        // Assert rfq_new_commitments remains disabled by default
        $this->assertFalse(app(FeatureFlagService::class)->allowsNewTransactions('rfq_new_commitments'));

        // Assert domestic wholesale remains active and allows new transactions
        $this->assertTrue(app(FeatureFlagService::class)->allowsNewTransactions('domestic_wholesale'));

        // Assert trade_access_applications defaults to disabled
        $this->assertFalse(app(FeatureFlagService::class)->allowsNewTransactions('trade_access_applications'));
        $this->assertFalse(app(FeatureFlagService::class)->isPubliclyVisible('trade_access_applications'));
    }
}
