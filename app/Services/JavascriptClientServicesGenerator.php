<?php
namespace App\Services;

use App\Utils\ParsableFromCaseName;
use Illuminate\Support\Facades\Route;
use LogicException;
use ReflectionEnum;
use ReflectionEnumBackedCase;

class JavascriptClientServicesGenerator {
    public function generateClientEnum(string $enumClassName, bool $caseNamesAsValues = false) {
        if (!enum_exists($enumClassName)) {
            throw new LogicException("Not an enum: $enumClassName");
        }

        $enumInfo = new ReflectionEnum($enumClassName);
        
        $js = "const {$enumInfo->getShortName()} = {" . PHP_EOL;

        foreach($enumInfo->getCases() as $case) {
            $name = $case->getName();

            if ($caseNamesAsValues || !($case instanceof ReflectionEnumBackedCase)) {
                $js .= "    $name: " . json_encode($name) . "," . PHP_EOL;
            }
            else {
                $js .= "    $name: " . json_encode($case->getValue()->value) . "," . PHP_EOL;
            }
        }

        $js .= "};" . PHP_EOL;

        return $js;
    }

    public function generateClientService(string $clientServiceClassName, string $routePrefix): string {
        $routeFunctions = [];
        foreach (Route::getRoutes() as $route) {
            foreach($route->methods() as $method) {
                if (!in_array($method, ["GET", "POST"])) {
                    continue;
                }
                if (is_null($route->getName())) {
                    continue;
                }
                if (!str_starts_with($route->getName(), "$routePrefix.")) {
                    continue;
                }

                $routeSuffix = substr($route->getName(), strlen($routePrefix) + 1);
                $normalizedMethodName = lcfirst(join("",
                    array_map(
                        fn ($s) => ucfirst($s),
                        array_filter(
                            array_map(fn ($s) => strtolower($s), preg_split("/\\.|-/", $routeSuffix, flags: PREG_SPLIT_NO_EMPTY)),
                            fn ($s) => strtolower($s) != "ajax"
                        )
                    )
                ));

                preg_match("/\\{([^{}]+)\\}/", $route->uri(), $paramMatches);
                
                array_shift($paramMatches);
                $paramMatches[] = "data = {}";

                $paramList = join(", ", $paramMatches);

                $escapedUri = htmlentities(preg_replace("/\\{([^{}]+)\\}/", "\${\\1}", $route->uri()));

                $ajaxCallCode = match($method) {
                    "GET" => <<<HEREDOC
                        return \$.get({
                                url: this.#_baseUri + `/$escapedUri`,
                                data: data
                            });
                    HEREDOC,
                    "POST" => <<<HEREDOC
                        data._token = this.#_csrfToken;
                            return \$.post({
                                url: this.#_baseUri + `/$escapedUri`,
                                data: data
                            });
                    HEREDOC,
                };

                $routeFunctions[] = <<<HEREDOC
                    // $method {$route->uri()} ({$route->getName()})
                    $normalizedMethodName($paramList) {
                    $ajaxCallCode
                    }
                HEREDOC;
            }
        }

        $routeFunctionsCode = join(PHP_EOL, $routeFunctions);

        $serviceClassCode = <<<HEREDOC
        class $clientServiceClassName {
            #_baseUri;
            #_csrfToken;

            constructor(baseUri, crsfToken) {
                this.#_baseUri = baseUri;
                this.#_csrfToken = crsfToken;
            }

        $routeFunctionsCode
        }
        HEREDOC;

        return $serviceClassCode;
    }
}