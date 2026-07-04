<?php

namespace App\Console\Commands;

use App\Services\SystemBackupService;
use Illuminate\Console\Command;

class CreateAutomaticSystemBackup extends Command
{
    protected $signature = 'system-backups:auto';

    protected $description = 'Create an automatic system backup.';

    public function handle(SystemBackupService $backups): int
    {
        $createdAt = now();

        $backups->create('Auto Backup '.$createdAt->format('Y-m-d H:i'), 'auto');

        $this->info('Automatic system backup created.');

        return self::SUCCESS;
    }
}
