<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

final class DatabaseSeeder extends Seeder
{
    /**
     * Nothing to seed. A Document only ever comes from a real UploadSession
     * (I-1), so seeding one would mean bytes on disk that no upload wrote.
     */
    public function run(): void {}
}
