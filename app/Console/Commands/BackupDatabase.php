<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
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
            $this->error('Database file not found at: '.$sourcePath);

            return Command::FAILURE;
        }

        $date = now()->format('Y-m-d');
        $filename = "database-backup-{$date}.sqlite";
        $backupDir = storage_path('app/backups');

        // Create backup directory if needed
        if (! is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $backupPath = "{$backupDir}/{$filename}";

        // Use SQLite's backup API to properly handle WAL mode
        // This ensures a consistent backup even with active WAL journaling
        try {
            $source = new SQLite3($sourcePath, SQLITE3_OPEN_READONLY);
            $destination = new SQLite3($backupPath);

            $source->backup($destination);

            $source->close();
            $destination->close();
        } catch (\Exception $e) {
            $this->error('Failed to create backup: '.$e->getMessage());

            return Command::FAILURE;
        }

        $size = round(filesize($backupPath) / 1024, 1);
        $this->info("Backup created: {$filename} ({$size} KB)");

        // Cleanup old backups
        if (! $this->option('no-cleanup')) {
            $this->cleanupOldBackups();
        }

        return Command::SUCCESS;
    }

    private function cleanupOldBackups(): void
    {
        $backupDir = storage_path('app/backups');
        $cutoffDate = now()->subDays(self::RETENTION_DAYS)->format('Y-m-d');
        $deleted = 0;

        foreach (glob("{$backupDir}/database-backup-*.sqlite") as $file) {
            if (preg_match('/database-backup-(\d{4}-\d{2}-\d{2})\.sqlite$/', $file, $matches)) {
                if ($matches[1] < $cutoffDate) {
                    unlink($file);
                    $deleted++;
                }
            }
        }

        if ($deleted > 0) {
            $this->info("Cleaned up {$deleted} backups older than {$cutoffDate}");
        }
    }
}
