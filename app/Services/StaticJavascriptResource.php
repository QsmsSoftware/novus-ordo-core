<?php
namespace App\Services;

use App\Models\Game;
use App\Models\Nation;
use App\Models\Turn;
use App\Utils\RuntimeInfo;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class StaticJavascriptResource {
    private function __construct(
        public readonly string $staticResourseName,
        private readonly Closure $codeGenerator,
        private readonly Closure $staticDifferentiators,
    )
    {
        
    }

    public static function permanent(string $staticResourseName, Closure $codeGenerator): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => [StaticJavascriptResource::hashValue(RuntimeInfo::readGitCommitHashOrNull()??"global")]
        );
    }

    public static function permanentForGame(string $staticResourseName, Closure $codeGenerator, Game $game): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => ["game_{$game->getId()}"],
        );
    }

    public static function permanentForTurn(string $staticResourseName, Closure $codeGenerator, Turn $turn): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => ["game_{$turn->getGame()->getId()}", "turn_{$turn->getId()}"],
        );
    }
    

    public static function expireAllForGame(Game $game): void {
        DB::table('cache')
            ->where('key', 'like', "static_js_file:game_{$game->getId()}-%")
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
            Cache::delete("static_js_file:$identifier");
            Cache::set("static_js_file:$identifier", $filename);
        });

        if (!$gotLock) {
            // Assuming that the static file is being cached for another user, waiting for the execution to finish.
            $lock->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});
        }
    }

    public function render(): string {
        $force = config('novusordo.always_rerender_permanent_js');
        $identifier = $this->getIdentifier();
        if (!$force) {
            $filenameOrNull = Cache::get("static_js_file:$identifier");
            if (!is_null($filenameOrNull) && file_exists($filenameOrNull)) {
                $filename = $filenameOrNull;
            }
        }

        if (!isset($filename))  {
            $renderedCode = ($this->codeGenerator)();
            $hash = $this->hashValue($renderedCode);
            $filename = public_path("var/$identifier-$hash.js");
            if (!file_exists($filename)) {
                $this->cacheAsFile($renderedCode, $filename);
            }
        }

        return '<script src="' . "/var/" . basename($filename) . '"></script>';
    }
}