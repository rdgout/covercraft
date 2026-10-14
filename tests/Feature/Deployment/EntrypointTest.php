<?php

namespace Tests\Feature\Deployment;

use Illuminate\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class EntrypointTest extends TestCase
{
    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/covercraft-entrypoint-'.bin2hex(random_bytes(8));
        mkdir($this->directory);
        $php = <<<'SH'
#!/bin/sh
printf '%s\n' "$*" >> "$COMMAND_LOG"
if [ "$2" = "key:generate" ]; then
    printf 'base64:test-key'
fi
SH;
        file_put_contents($this->directory.'/php', $php);
        chmod($this->directory.'/php', 0755);
        file_put_contents($this->directory.'/frankenphp', "#!/bin/sh\nprintf '%s' \"\$APP_KEY\" > \"\$KEY_CAPTURE\"\n");
        chmod($this->directory.'/frankenphp', 0755);
    }

    protected function tearDown(): void
    {
        (new Filesystem)->deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_web_startup_persists_one_key_and_runs_migrations(): void
    {
        $first = $this->runEntrypoint(['frankenphp']);
        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertSame('base64:test-key', file_get_contents($this->directory.'/key'));
        $this->assertSame('base64:test-key', file_get_contents($this->directory.'/captured'));
        $this->assertSame(0600, fileperms($this->directory.'/key') & 0777);
        $this->assertStringContainsString('migrate --force --no-interaction', file_get_contents($this->directory.'/commands'));
        $second = $this->runEntrypoint(['frankenphp']);
        $this->assertTrue($second->isSuccessful());
        $this->assertSame(1, substr_count(file_get_contents($this->directory.'/commands'), 'key:generate'));
    }

    public function test_worker_loads_the_web_key_and_caches_its_own_config_without_migrating(): void
    {
        file_put_contents($this->directory.'/key', 'base64:shared-key');
        $process = $this->runEntrypoint(['php', 'artisan', 'horizon']);
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $commands = file_get_contents($this->directory.'/commands');
        $this->assertStringContainsString('config:cache --no-interaction', $commands);
        $this->assertStringContainsString('route:cache --no-interaction', $commands);
        $this->assertStringNotContainsString('migrate', $commands);
        $this->assertStringNotContainsString('key:generate', $commands);
    }

    public function test_worker_without_a_shared_key_fails_with_an_actionable_message(): void
    {
        $process = $this->runEntrypoint(['php', 'artisan', 'horizon']);
        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Start the app container first', $process->getOutput());
        $this->assertFileDoesNotExist($this->directory.'/key');
    }

    public function test_explicit_key_and_disabled_migrations_are_respected(): void
    {
        $process = $this->runEntrypoint(['frankenphp'], ['APP_KEY' => 'base64:provided-key', 'RUN_MIGRATIONS' => 'false']);
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('base64:provided-key', file_get_contents($this->directory.'/captured'));
        $this->assertFileDoesNotExist($this->directory.'/key');
        $this->assertStringNotContainsString('migrate', file_get_contents($this->directory.'/commands'));
    }

    public function test_storage_initialization_preserves_uploads_and_does_not_boot_laravel(): void
    {
        mkdir($this->directory.'/storage/app/private/coverage', 0755, true);
        file_put_contents($this->directory.'/storage/app/private/coverage/report.xml', '<coverage/>');
        file_put_contents($this->directory.'/chown', "#!/bin/sh\nprintf '%s\\n' \"\$*\" > \"\$COMMAND_LOG\"\n");
        chmod($this->directory.'/chown', 0755);

        $process = $this->runEntrypoint(['init-storage']);

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
        $this->assertSame('-R www-data:www-data '.$this->directory.'/storage'."\n", file_get_contents($this->directory.'/commands'));
        $this->assertSame('<coverage/>', file_get_contents($this->directory.'/storage/app/private/coverage/report.xml'));
        $this->assertDirectoryExists($this->directory.'/storage/app/public');
        $this->assertDirectoryExists($this->directory.'/storage/framework/cache/data');
        $this->assertDirectoryExists($this->directory.'/storage/framework/sessions');
        $this->assertDirectoryExists($this->directory.'/storage/framework/views');
        $this->assertDirectoryExists($this->directory.'/storage/logs');
        $this->assertFileDoesNotExist($this->directory.'/key');
        $this->assertFileDoesNotExist($this->directory.'/captured');
    }

    public function test_storage_initialization_failure_stops_startup(): void
    {
        file_put_contents($this->directory.'/chown', "#!/bin/sh\necho 'Permission denied' >&2\nexit 1\n");
        chmod($this->directory.'/chown', 0755);

        $process = $this->runEntrypoint(['init-storage']);

        $this->assertSame(1, $process->getExitCode());
        $this->assertStringContainsString('Permission denied', $process->getErrorOutput());
        $this->assertFileDoesNotExist($this->directory.'/commands');
    }

    /**
     * @param  list<string>  $command
     * @param  array<string, string>  $environment
     */
    private function runEntrypoint(array $command, array $environment = []): Process
    {
        $process = new Process(['sh', base_path('docker/entrypoint.sh'), ...$command], base_path(), array_merge([
            'PATH' => $this->directory.':/usr/bin:/bin',
            'APP_KEY' => '',
            'APP_KEY_FILE' => $this->directory.'/key',
            'APP_STORAGE_PATH' => $this->directory.'/storage',
            'COMMAND_LOG' => $this->directory.'/commands',
            'KEY_CAPTURE' => $this->directory.'/captured',
        ], $environment));
        $process->run();

        return $process;
    }
}
