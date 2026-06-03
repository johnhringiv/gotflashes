<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use SQLite3;

class BackupDatabase extends Command
{
    protected $signature = 'db:backup {--no-cleanup : Skip old backup cleanup}';

    protected $description = 'Backup the SQLite database with date in filename';

    private const RETENTION_DAYS = 90;

    public function handle(): int
    {
        $sourcePath = database_path('data/database.sqlite');

        if (! file_exists($sourcePath)) {
            return $this->failBackup('Database file not found at: '.$sourcePath, ['source' => $sourcePath]);
        }

        $date = now()->format('Y-m-d');
        $filename = "database-backup-{$date}.sqlite";
        $backupDir = storage_path('app/backups');

        // Create backup directory if needed
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0750, true);
        }

        $backupPath = "{$backupDir}/{$filename}";

        // Use SQLite's backup API to properly handle WAL mode
        // This ensures a consistent backup even with active WAL journaling
        try {
            $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new SQLite3($backupPath);

            $source->backup($destination);

            // The backup copies the source's WAL journal mode, which leaves
            // -wal/-shm sidecars next to the file. Switching to DELETE mode
            // checkpoints the journal into the main file and drops the
            // sidecars, so the backup is a single self-contained .sqlite.
            $destination->exec('PRAGMA journal_mode=DELETE');

            $source->close();
            $destination->close();

            // Remove any sidecars left behind (defensive; DELETE mode should
            // have already cleaned these up on close).
            @unlink("{$backupPath}-wal");
            @unlink("{$backupPath}-shm");
        } catch (\Exception $e) {
            @unlink($backupPath);

            return $this->failBackup('Failed to create backup: '.$e->getMessage(), [
                'source' => $sourcePath,
                'backup' => $backupPath,
                'exception' => $e->getMessage(),
            ]);
        }

        // Validate the backup is a readable SQLite database before reporting success.
        // A corrupted backup is worse than no backup.
        if (! $this->isValidBackup($backupPath)) {
            @unlink($backupPath);

            return $this->failBackup('Backup failed integrity check and was removed: '.$filename, [
                'source' => $sourcePath,
                'backup' => $backupPath,
            ]);
        }

        // Restrict permissions: readable by owner/group only. Log if it fails,
        // since locking down the backup is an explicit security goal.
        if (! @chmod($backupPath, 0640)) {
            Log::channel('backup')->warning('Could not set backup file permissions to 0640', [
                'backup' => $backupPath,
            ]);
        }

        $size = round(filesize($backupPath) / 1024, 1);
        $this->info("Backup created: {$filename} ({$size} KB)");
        Log::channel('backup')->info('Database backup created', [
            'backup' => $backupPath,
            'size_kb' => $size,
        ]);

        // Cleanup old backups
        if (! $this->option('no-cleanup')) {
            $this->cleanupOldBackups();
        }

        return Command::SUCCESS;
    }

    /**
     * Reopen the backup read-only and verify it passes SQLite's integrity check.
     */
    private function isValidBackup(string $backupPath): bool
    {
        try {
            $db = new SQLite3($backupPath, SQLITE3_OPEN_READONLY);
        } catch (\Exception $e) {
            return false;
        }

        // try/finally so the connection is always closed, even if the
        // integrity check throws on a corrupt backup.
        try {
            return $db->querySingle('PRAGMA integrity_check') === 'ok';
        } catch (\Exception $e) {
            return false;
        } finally {
            $db->close();
        }
    }

    /**
     * Log an error and return a FAILURE exit code.
     *
     * @param  array<string, mixed>  $context
     */
    private function failBackup(string $message, array $context = []): int
    {
        $this->error($message);
        Log::channel('backup')->error($message, $context);

        return Command::FAILURE;
    }

    private function cleanupOldBackups(): void
    {
        $backupDir = storage_path('app/backups');
        $cutoffDate = now()->subDays(self::RETENTION_DAYS)->format('Y-m-d');
        $deleted = 0;

        foreach (glob("{$backupDir}/database-backup-*.sqlite") as $file) {
            if (preg_match('/database-backup-(\d{4}-\d{2}-\d{2})\.sqlite$/', $file, $matches)) {
                if ($matches[1] < $cutoffDate) {
                    // Silenced + logged (rather than a bare unlink that throws a raw
                    // PHP warning) so a permissions error surfaces in the backup log.
                    if (@unlink($file)) {
                        // Remove orphaned WAL/SHM sidecar files if present.
                        @unlink("{$file}-wal");
                        @unlink("{$file}-shm");
                        $deleted++;
                    } else {
                        Log::channel('backup')->warning('Could not delete old backup', ['backup' => $file]);
                    }
                }
            }
        }

        if ($deleted > 0) {
            $this->info("Cleaned up {$deleted} backups older than {$cutoffDate}");
            Log::channel('backup')->info('Cleaned up old backups', [
                'deleted' => $deleted,
                'cutoff' => $cutoffDate,
            ]);
        }
    }
}
