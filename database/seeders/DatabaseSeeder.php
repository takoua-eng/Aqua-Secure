<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
public function run(): void
{
    User::create(['nom' => 'Administrateur', 'email' => 'admin@aquasecure.tn', 'password' => 'password', 'role' => 'admin']);
    User::create(['nom' => 'Gestionnaire Test', 'email' => 'gestionnaire@aquasecure.tn', 'password' => 'password', 'role' => 'gestionnaire']);
    User::create(['nom' => 'Citoyen Test', 'email' => 'citoyen@aquasecure.tn', 'password' => 'password', 'role' => 'citoyen']);

    // 10 citoyens fictifs pour tester la liste et la pagination
    User::factory(10)->create();

    // Zones et réseaux d'eau de démonstration
    $this->call(InfrastructureSeeder::class);
    $this->call(QualiteEauSeeder::class);
}
}
