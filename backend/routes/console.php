<?php

use App\Models\Service;
use App\Services\SwiftCatalogImport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('swift:import-catalog {--file= : Reviewed catalog JSON file} {--apply : Apply the reviewed catalog} {--backup= : Save existing services before applying}', function () {
    $path = $this->option('file') ?: database_path('data/swift-catalog.json');
    try {
        $manifest = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        $importer = app(SwiftCatalogImport::class);
        $stats = $importer->run($manifest);
        if ($this->option('apply')) {
            $backup = $this->option('backup');
            if (app()->isProduction() && ! $backup) {
                $this->error('Production imports require --backup with a new private backup path.');

                return 1;
            }
            if ($backup) {
                $handle = fopen($backup, 'x');
                if (! $handle) {
                    throw new RuntimeException('Cannot create a new service backup.');
                }
                try {
                    chmod($backup, 0600);
                    fwrite($handle, Service::all()->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
                } finally {
                    fclose($handle);
                }
            }
            $stats = $importer->run($manifest, true);
        }
        $this->info(json_encode($stats, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));

        return 0;
    } catch (Throwable $error) {
        $this->error($error->getMessage());

        return 1;
    }
})->purpose('Preview or import the reviewed Swift Rent cars and transfers without changing other suppliers');
