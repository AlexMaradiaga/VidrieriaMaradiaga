<?php

namespace Tests\Feature\Deployment;

use App\Infrastructure\Database\SqlServerConnector;
use App\Models\User;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class RenderRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        $_ENV['RENDER'] = 'true';
        parent::setUp();
        config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:',
            'database.connections.sqlite.url' => null, 'session.driver' => 'array',
            'cache.default' => 'array', 'app.key' => 'base64:'.base64_encode(str_repeat('a', 32))]);
        DB::purge('sqlite');
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->boolean('active')->default(true);
            $table->timestamp('last_login_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        unset($_ENV['RENDER']);
        TrustProxies::flushState();
        parent::tearDown();
    }

    public function test_sql_server_uses_the_connector_for_all_modules(): void
    {
        $this->assertInstanceOf(SqlServerConnector::class, app('db.connector.sqlsrv'));
    }

    public function test_proxy_guest_redirect_and_intended_url_use_https(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->get('http://erp.test/')
            ->assertRedirect('https://erp.test/login')
            ->assertSessionHas('url.intended', 'https://erp.test');
    }

    public function test_login_upgrades_a_legacy_http_intended_url_and_saves_last_login(): void
    {
        $user = User::create(['name' => 'Prueba', 'email' => 'test@example.test',
            'password' => Hash::make('Test-password-123'), 'active' => true]);
        $this->withSession(['url.intended' => 'http://erp.test/suppliers?page=2'])
            ->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
            ->withHeaders(['X-Forwarded-Proto' => 'https', 'X-Forwarded-Port' => '443'])
            ->post('http://erp.test/login', ['email' => $user->email, 'password' => 'Test-password-123'])
            ->assertRedirect('https://erp.test/suppliers?page=2');
        $this->assertNotNull($user->fresh()->last_login_at);
    }

    public function test_supplier_payload_is_accepted_and_duplicates_are_validation_errors(): void
    {
        Schema::create('inventory_suppliers', function (Blueprint $table): void {
            $table->id();
            $table->string('code')->unique();
            $table->string('legal_name');
            foreach (['trade_name', 'tax_id', 'contact_name', 'email', 'phone', 'address'] as $field) {
                $table->string($field)->nullable();
            }
            $table->integer('payment_terms_days');
            $table->boolean('active');
            $table->timestamps();
            $table->softDeletes();
        });
        (require database_path('migrations/2026_09_10_172431_create_permission_tables.php'))->up();
        Permission::create(['name' => 'suppliers.manage', 'guard_name' => 'web']);
        $user = User::create(['name' => 'Prueba', 'email' => 'test@example.test', 'password' => 'test']);
        $user->givePermissionTo('suppliers.manage');
        $payload = ['code' => '003 DIP', 'legal_name' => 'Proveedor de prueba', 'trade_name' => 'COMERCIAL',
            'tax_id' => '06010000000000', 'contact_name' => 'José', 'email' => 'proveedor@example.test',
            'phone' => '27820000', 'address' => 'Choluteca, Honduras', 'payment_terms_days' => 0];
        $this->actingAs($user)->from('/suppliers')->post('/suppliers', $payload)
            ->assertRedirect('/suppliers')->assertSessionHasNoErrors();
        $row = DB::table('inventory_suppliers')->first();
        $this->assertSame(0, $row->payment_terms_days);
        $this->assertNotNull($row->created_at);
        $this->post('/suppliers', $payload)->assertSessionHasErrors('code');
        $this->assertSame(1, DB::table('inventory_suppliers')->count());
    }
}
