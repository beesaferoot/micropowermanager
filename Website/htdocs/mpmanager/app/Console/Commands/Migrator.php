<?php

namespace App\Console\Commands;

use App\Models\CompanyDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;

class Migrator extends AbstractSharedCommand
{
    protected $signature = 'migrator:migrate {database-name}';
    protected $description = 'Migrates all base migrations to provided database name';

     function runInCompanyScope(): void
    {
        $dbName = $this->argument('database-name');
        if ($dbName == 'base') {
            $this->call('optimize:clear');
            $this->call('migrate', [
                '--database' => 'micro_power_manager',
                '--path' => '/database/migrations/' . $dbName,
            ]);
        } elseif('all') {
            CompanyDatabase::all()->each(function ($companyDatabase) {
                $this->call('optimize:clear');
                $this->info('Running migration for "' . $companyDatabase->database_name . '"');
                $this->call('migrate', [
                    '--database' => 'shard',
                    '--path' => '/database/migrations/' . $companyDatabase->database_name,
                ]);
            });
        }else{
            $this->call('optimize:clear');
            $this->call('migrate', [
                '--database' => 'shard',
                '--path' => '/database/migrations/' . $dbName,
            ]);
        }
    }

    private function isUnNecessaryConnection($name): bool
    {
        if ($name == 'sqlite' || $name == 'pgsql' || $name == 'sqlsrv') {
            return true;
        }
        return false;
    }

    private function isKeyMicroPowerManager($key): bool
    {
        if ($key == 'micro_power_manager') {
            $this->call('migrate', [
                '--database' => $key,
                '--path' => '/database/migrations/base',
            ]);
            return true;
        }
        return false;
    }


}
