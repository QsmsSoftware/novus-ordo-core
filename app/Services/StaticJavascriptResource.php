<?php
namespace App\Services;

use App\Models\Game;
use App\Models\Nation;
use App\Models\Turn;
use App\Utils\RuntimeInfo;
use Closure;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class StaticJavascriptResource {
    private function __construct(
        public readonly string $staticResourseName,
        private readonly Closure $codeGenerator,
        private readonly Closure $staticDifferentiator,
    )
    {
        
    }

    public static function permanent(string $staticResourseName, Closure $codeGenerator): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => StaticJavascriptResource::hashValue(RuntimeInfo::readGitCommitHashOrNull()??"global")
        );
    }

    public static function permanentForGame(string $staticResourseName, Closure $codeGenerator, Game $game): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => "game_{$game->getId()}",
        );
    }

    public static function permanentForTurn(string $staticResourseName, Closure $codeGenerator, Turn $turn): StaticJavascriptResource {
        return new StaticJavascriptResource(
            $staticResourseName,
            $codeGenerator,
            static fn () => "game_{$turn->getGame()->getId()}-turn_{$turn->getId()}",
        );
    }
    

    public static function expireAllForGame(Game $game): void {
        $cachedFiles = glob(public_path("var/*-game_{$game->getId()}-*"));
        array_walk($cachedFiles, fn ($filename) => unlink($filename));
    }

    private static function hashValue(string $value): string {
        return hash('xxh128', $value, false);
    }

    private function getIdentifier(): string {
        $differentiator = ($this->staticDifferentiator)();
        return "{$this->staticResourseName}-$differentiator";
    }

    private function findMostRecentCachedFileOrNull(): ?string {
        $filenamesOrError = glob(public_path("var/{$this->getIdentifier()}-*.js"));
        if ($filenamesOrError === false) {
            throw new RuntimeException("An error happened while looking up cached files");
        }
        usort($filenamesOrError, function($a, $b) {
            return filemtime($b) <=> filemtime($a);
        });
        return array_first($filenamesOrError);
    }

    private function cacheAsFile(string $renderedCode, string $filename): void {
        $lock = Cache::lock("caching_static_js_file:{$this->getIdentifier()}", RuntimeInfo::maxExectutionTimeSeconds() * 0.8);
        $gotLock = $lock->get(function () use ($filename, $renderedCode) {
            $bytesWritten = file_put_contents($filename, $renderedCode);
            if ($bytesWritten !== strlen($renderedCode)) {
                throw new RuntimeException("Rendered static Javascript file could not be written properly");
            }
        });

        if (!$gotLock) {
            // Assuming that the static file is being cached for another user, waiting for the execution to finish.
            $lock->block(RuntimeInfo::maxExectutionTimeSeconds() * 0.8, function () {});
        }
    }

    public function render(): string {
        $force = config('novusordo.always_render_js');
        $identifier = $this->getIdentifier();
        if (!$force) {
            $filenameOrNull = $this->findMostRecentCachedFileOrNull();
            if (!is_null($filenameOrNull)) {
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