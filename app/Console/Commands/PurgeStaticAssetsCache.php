<?php

namespace App\Console\Commands;

use App\Utils\RuntimeInfo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class PurgeStaticAssetsCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'purge-static-assets-cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Delete all cached static assets in public/var.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        Cache::lock("purging_static_js", RuntimeInfo::maxExectutionTimeSeconds() * 0.8)
            ->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {
                $cachedFiles = glob(public_path("var/*.js"));
                array_walk($cachedFiles, fn ($filename) => unlink($filename));
            });
    }
}
