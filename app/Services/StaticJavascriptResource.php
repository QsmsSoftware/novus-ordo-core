<?php
namespace App\Services;

use App\Utils\RuntimeInfo;
use Closure;
use RuntimeException;

class StaticJavascriptResource {
    public function __construct(
        public string $staticResourseName,
        private Closure $codeGenerator,
    )
    {
        
    }

    private function hashValue(string $value): string {
        return hash('xxh128', $value, false);
    }

    public function renderResource(): string {
        $commitHashOrNull = $this->hashValue(RuntimeInfo::readGitCommitHashOrNull());
        if (!config('novusordo.always_render_js')) {
            $globPattern = public_path("var/$this->staticResourseName-$commitHashOrNull-*.js");
            $filenamesOrError = glob($globPattern);
            if ($filenamesOrError !== false && count($filenamesOrError) > 1) {
                throw new RuntimeException("Expecting at most one match for pattern '$globPattern', " . count($filenamesOrError) . " found");
            }
            $filename = array_first($filenamesOrError);
        }

        if (!isset($filename))  {
            $renderedCode = ($this->codeGenerator)();
            $hash = $this->hashValue($renderedCode);
            $filename = public_path("var/{$this->staticResourseName}-$commitHashOrNull-$hash.js");
            if (!file_exists($filename)) {
                $globPattern = public_path("var/$this->staticResourseName-*-*.js");
                $filenamesOrError = glob($globPattern);
                if ($filenamesOrError === false) {
                    throw new RuntimeException("Search for cached static Javascript file failed");
                }
                array_walk($filenamesOrError, fn ($filename) => unlink($filename));
                $bytesWritten = file_put_contents($filename, $renderedCode);
                if ($bytesWritten !== strlen($renderedCode)) {
                    throw new RuntimeException("Rendered static Javascript file could not be written properly");
                }
            }
        }

        return '<script src="' . "/var/" . basename($filename) . '"></script>';
    }
}