<?php

namespace Tests\Feature;

use SQLite3;
use Tests\TestCase;

class BackupDatabaseTest extends TestCase
{
    private string $sourcePath;

    private string $backupDir;

    /** @var array<int, string> */
    private array $preexisting = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourcePath = database_path('data/database.sqlite');
        $this->backupDir = storage_path('app/backups');

        // Ensure a valid SQLite source exists so tests don't depend on dev DB state.
        if (! file_exists($this->sourcePath)) {
            if (! is_dir(dirname($this->sourcePath))) {
                mkdir(dirname($this->sourcePath), 0750, true);
            }
            (new SQLite3($this->sourcePath))->close();
        }

        if (! is_dir($this->backupDir)) {
            mkdir($this->backupDir, 0750, true);
        }

        // Snapshot existing files so we only remove what the tests create.
        $this->preexisting = glob("{$this->backupDir}/*") ?: [];
    }

    protected function tearDown(): void
    {
        foreach (glob("{$this->backupDir}/*") ?: [] as $file) {
            if (! in_array($file, $this->preexisting, true)) {
                @unlink($file);
            }
        }

        parent::tearDown();
    }

    private function todaysBackupPath(): string
    {
        return "{$this->backupDir}/database-backup-".now()->format('Y-m-d').'.sqlite';
    }

    public function test_backup_command_succeeds(): void
    {
        $this->artisan('db:backup', ['--no-cleanup' => true])->assertSuccessful();

        $this->assertFileExists($this->todaysBackupPath());
    }

    public function test_backup_is_a_single_file_with_no_sidecars(): void
    {
        $this->artisan('db:backup', ['--no-cleanup' => true])->assertSuccessful();

        $this->assertFileExists($this->todaysBackupPath());
        $this->assertFileDoesNotExist($this->todaysBackupPath().'-wal');
        $this->assertFileDoesNotExist($this->todaysBackupPath().'-shm');
    }

    public function test_backup_produces_valid_sqlite(): void
    {
        $this->artisan('db:backup', ['--no-cleanup' => true])->assertSuccessful();

        $db = new SQLite3($this->todaysBackupPath(), SQLITE3_OPEN_READONLY);
        $result = $db->querySingle('PRAGMA integrity_check');
        $db->close();

        $this->assertSame('ok', $result);
    }

    public function test_backup_fails_gracefully_when_source_missing(): void
    {
        $temp = $this->sourcePath.'.moved';
        rename($this->sourcePath, $temp);

        try {
            $this->artisan('db:backup')->assertFailed();
        } finally {
            rename($temp, $this->sourcePath);
        }
    }

    public function test_cleanup_removes_old_backups(): void
    {
        $old = "{$this->backupDir}/database-backup-2020-01-01.sqlite";
        touch($old);
        touch("{$old}-wal");
        touch("{$old}-shm");

        $this->artisan('db:backup')->assertSuccessful();

        $this->assertFileDoesNotExist($old);
        $this->assertFileDoesNotExist("{$old}-wal");
        $this->assertFileDoesNotExist("{$old}-shm");
    }

    public function test_no_cleanup_flag_skips_retention(): void
    {
        $old = "{$this->backupDir}/database-backup-2020-01-01.sqlite";
        touch($old);

        $this->artisan('db:backup', ['--no-cleanup' => true])->assertSuccessful();

        $this->assertFileExists($old);
    }
}
