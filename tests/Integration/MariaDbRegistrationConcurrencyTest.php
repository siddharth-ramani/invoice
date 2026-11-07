<?php

namespace Tests\Integration;

use App\Models\Subscription;
use App\Models\User;
use App\Models\UserPreference;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BusinessFixture;
use Tests\TestCase;

class MariaDbRegistrationConcurrencyTest extends TestCase
{
    public function test_public_registration_handles_real_unique_email_races_without_partial_accounts(): void
    {
        $name = getenv('BIZPILOT_REGISTRATION_TEST_DATABASE');
        if (! $name || ! preg_match('/^bizpilot_m5_qa_\d{8}_[a-f0-9]{6}$/D', $name) || ! function_exists('pcntl_fork')) {
            $this->markTestSkipped('Requires an owned isolated MariaDB registration QA schema.');
        }
        $this->assertNotSame(config('database.connections.mysql.database'), $name);
        config(['database.default' => 'mysql', 'database.connections.mysql.database' => $name, 'database.connections.mysql.url' => null, 'bizpilot.platform_enabled' => true, 'bizpilot.business_modules_enabled' => true, 'bizpilot.payments_enabled' => true, 'logging.default' => 'null']);
        DB::purge('mysql');
        $this->assertSame($name, DB::selectOne('SELECT DATABASE() AS db')->db);
        $this->assertSame(0, User::count());
        $dir = sys_get_temp_dir().'/bizpilot-registration-mysql-views';
        if (! is_dir($dir)) {
            mkdir($dir, 0700, true);
        }config(['view.compiled' => $dir]);
        Storage::fake('local');
        Http::preventStrayRequests();
        foreach (['2026_11_02_000001_create_payment_ledger_tables', '2026_11_02_000002_create_business_modules_tables', '2026_11_02_000003_create_platform_services_tables'] as $m) {
            (require database_path('migrations/'.$m.'.php'))->up();
        }$f = BusinessFixture::seed();
        $before = $f['invoice']->fresh()->getAttributes();
        $payload = ['name' => 'Synthetic registration race', 'company_name' => 'Synthetic race company', 'email' => 'race.owner@example.test', 'password' => 'Synthetic-New-42', 'password_confirmation' => 'Synthetic-New-42', 'locale' => 'en', 'role' => 'admin'];
        $base = sys_get_temp_dir().'/bizpilot-register-race-'.bin2hex(random_bytes(5));
        $pids = [];
        DB::disconnect();
        foreach ([0, 1] as $i) {
            $pid = pcntl_fork();
            if ($pid === -1) {
                throw new \RuntimeException('Could not fork');
            }if ($pid === 0) {
                DB::purge('mysql');
                $paused = false;
                DB::listen(function ($q) use ($base, $i, &$paused) {
                    if (! $paused && str_contains(strtolower($q->sql), 'lower(email)')) {
                        $paused = true;
                        touch($base.'.ready'.$i);
                        for ($n = 0; $n < 300 && ! is_file($base.'.ready'.(1 - $i)); $n++) {
                            usleep(10000);
                        }
                    }
                });
                try {
                    $response = $this->from('/register')->post('/register', $payload);
                    $out = ['status' => $response->getStatusCode(), 'created' => auth()->check(), 'duplicate' => (bool) session('errors')?->has('email')];
                } catch (\Throwable $e) {
                    $out = ['exception' => get_class($e)];
                }file_put_contents($base.'.'.$i, json_encode($out));
                DB::disconnect();
                exit(0);
            }$pids[] = $pid;
        }
        foreach ($pids as $pid) {
            pcntl_waitpid($pid, $status);
            $this->assertSame(0, pcntl_wexitstatus($status));
        }DB::purge('mysql');
        $results = [];
        foreach ([0, 1] as $i) {
            $this->assertFileExists($base.'.'.$i);
            $results[] = json_decode(file_get_contents($base.'.'.$i), true);
            unlink($base.'.'.$i);
            if (is_file($base.'.ready'.$i)) {
                unlink($base.'.ready'.$i);
            }
        }
        $this->assertSame([302, 302], array_column($results, 'status'), json_encode($results));
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['created'])));
        $this->assertSame(1, count(array_filter($results, fn ($r) => $r['duplicate'])));
        $this->assertSame(1, User::where('email', $payload['email'])->count());
        $user = User::where('email', $payload['email'])->firstOrFail();
        $this->assertSame('vendor', $user->role);
        $this->assertNull($user->created_by);
        $this->assertSame(1, UserPreference::where('user_id', $user->id)->count());
        $this->assertSame(1, Subscription::count());
        $this->assertSame($before,$f['invoice']->fresh()->getAttributes());
        Http::assertNothingSent();
    }
}
