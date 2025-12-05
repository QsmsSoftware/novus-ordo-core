<?php
namespace App\Services;

use App\Facades\RuntimeInfo;
use App\Models\Game;
use App\Models\Turn;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

readonly class PurgeUnreferencedFilesResult {
    public function __construct(
        public int $numberOfFilesPurged,
    ) {}
}

class StaticJavascriptResource {
    private const ?int TTL_FOREVER = null;
    private const int TTL_NON_PERMANENT_SECONDS = 72 * 60 * 60; // 72 hours, enough to keep current turn and previous one in cache.;

    private function __construct(
        private readonly string $staticResourseName,
        private readonly Closure $codeGenerator,
        private readonly Closure $staticDifferentiators,
        private readonly bool $permanent,
    )
    {
        
    }

    public static function generateStaticResourceNameFromMethodName(string $methodName) {
        if (preg_match('/\\\\([^:\\\\]+)::([^:\\\\]+)$/', $methodName, $matches) !== 1) {
            throw new InvalidArgumentException("methodName: expecting Namespace\\Class::Method but got '$methodName'");
        }

        return "{$matches[1]}-{$matches[2]}";
    }

    public static function permanent(string $staticResourseName, Closure $codeGenerator): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => [StaticJavascriptResource::hashValue(RuntimeInfo::readGitCommitHashOrNull()??"global")],
            true
        );
    }

    public static function permanentForGame(string $staticResourseName, Closure $codeGenerator, Game $game): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => ["game_{$game->getId()}"],
            true
        );
    }

    public static function forTurn(string $staticResourseName, Closure $codeGenerator, Turn $turn): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => ["game_{$turn->getGame()->getId()}", "turn_{$turn->getId()}"],
            false
        );
    }

    public static function expireAll(): void {
        DB::table('cache')
            ->where('key', 'like', config('cache.prefix') . "static_js_file:%")
            ->delete();
    }

    private static function hashValue(string $value): string {
        return hash('xxh128', $value, false);
    }

    private function getIdentifier(): string {
        $differentiator = join("-", ($this->staticDifferentiators)());
        return "{$this->staticResourseName}-$differentiator";
    }

    private function cacheAsFile(string $renderedCode, string $filename): void {
        $identifier = $this->getIdentifier();
        $lock = Cache::lock("caching_static_js_file:$identifier", RuntimeInfo::maxExectutionTimeSeconds() * 0.8);
        $gotLock = $lock->get(function () use ($filename, $renderedCode, $identifier) {
            $bytesWritten = file_put_contents($filename, $renderedCode);
            if ($bytesWritten !== strlen($renderedCode)) {
                throw new RuntimeException("Rendered static Javascript file could not be written properly");
            }
            Cache::delete("static_js_file:$identifier-");
            Cache::set("static_js_file:$identifier-", $filename, $this->permanent ? StaticJavascriptResource::TTL_FOREVER : StaticJavascriptResource::TTL_NON_PERMANENT_SECONDS);
        });

        if (!$gotLock) {
            // Assuming that the static file is being cached for another user, waiting for the execution to finish.
            $lock->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});
        }
    }

    private function render(): string {
        $force = config('novusordo.always_rerender_permanent_js');
        $identifier = $this->getIdentifier();
        if (!$force) {
            $filenameOrNull = Cache::get("static_js_file:$identifier-");
            if (!is_null($filenameOrNull) && file_exists($filenameOrNull)) {
                $filename = $filenameOrNull;
            }
        }

        if (!isset($filename))  {
            $renderedCode = ($this->codeGenerator)();
            $hash = $this->hashValue($renderedCode);
            $filename = public_path("var/static/$identifier-$hash.js");
            if (file_exists($filename)) {
                // Content was stored in static file but cache entry was missing.
                Cache::set("static_js_file:$identifier-", $filename, $this->permanent ? StaticJavascriptResource::TTL_FOREVER : StaticJavascriptResource::TTL_NON_PERMANENT_SECONDS);
            }
            else {
                $this->cacheAsFile($renderedCode, $filename);
            }
        }

        return $filename;
    }

    public function renderAsRelativeUri(): string {
        $filename = $this->render();

        return "/var/static/" . basename($filename);
    }

    public function renderAsCode(): string {
        $filename = $this->render();

        return file_get_contents($filename);
    }

    public function renderAsTag(): string {
        $filename = $this->render();

        return '<script src="' . "/var/static/" . basename($filename) . '"></script>';
    }

    public static function purgeUnreferencedFiles(): PurgeUnreferencedFilesResult {
        $files = glob(public_path("var/static/*.js"));

        if ($files === false) {
            throw new RuntimeException("Unable to read directory: " . public_path("var/static/*"));
        }

        $purgedCount = 0;

        foreach($files as $fullName) {
            $fileReferenced = DB::table('cache')
                ->where('key', 'like', config('cache.prefix') . "static_js_file:%")
                ->where('value', 'like', '%"' . $fullName . '"%')
                ->where('expiration', '>', CarbonImmutable::now('UTC')->getTimestamp())
                ->exists();
            
            if (!$fileReferenced) {
                unlink($fullName);
                $purgedCount++;
            }
        }

        return new PurgeUnreferencedFilesResult($purgedCount);
    }
}