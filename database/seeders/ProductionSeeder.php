<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ProductionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Clearing database tables...');

        // Disable foreign key checks
        Schema::disableForeignKeyConstraints();

        // Get all tables
        $tables = DB::select('SHOW TABLES');
        $dbName = DB::getDatabaseName();
        $key = 'Tables_in_' . $dbName;

        // Truncate all tables except migrations
        foreach ($tables as $table) {
            $tableName = $table->{$key} ?? current((array) $table);
            if ($tableName !== 'migrations') {
                DB::table($tableName)->truncate();
            }
        }

        // Enable foreign key checks
        Schema::enableForeignKeyConstraints();

        $this->command->info('Database cleared. Seeding necessary production data...');

        // Seed necessary production data
        $this->call([
            CountrySeeder::class,
            PermissionSeeder::class,
            RoleSeeder::class,
            AddAdminAccount::class,
            PlanSeeder::class,
        ]);
        
        $this->command->info('Database seeded successfully!');
    }
}
