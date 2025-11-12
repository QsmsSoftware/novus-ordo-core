<?php
namespace App\Services;

use App\Utils\Annotations\Context;
use App\Utils\Annotations\Payload;
use App\Utils\Annotations\QueryParameter;
use App\Utils\Annotations\Response;
use App\Utils\Annotations\ResponseCollection;
use App\Utils\Annotations\ResponseElement;
use App\Utils\Annotations\RouteParameter;
use App\Utils\Annotations\Summary;
use App\Utils\Reflection;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use LogicException;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionEnum;
use ReflectionEnumBackedCase;
use ReflectionMethod;
use ReflectionParameter;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

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
            if (isset($route->getAction()['controller'])) {
                $controller = $route->getAction()['controller'];
                if (is_string($controller)) {
                    $controller = explode("@", $controller);
                }

                assert(is_array($controller));
                assert(count($controller) == 2);
                [$controllerClassName, $controllerMethodName] = $controller;
                $controllerClass = new ReflectionClass($controllerClassName);
                $controllerMethod = $controllerClass->getMethod($controllerMethodName);
            }
            else {
                $controllerMethod = null;
            }

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

                $routeSignature = JavascriptClientServicesGenerator::getRouteSignature($route, $method);

                preg_match("/\\{([^{}]+)\\}/", $route->uri(), $paramMatches);
                array_shift($paramMatches);
                $uriParamList = $paramMatches;
                $paramList = join(", ", [...$uriParamList, "data = {}"]);
                $documentationLines = array_merge(
                    JavascriptClientServicesGenerator::getDocumentationForSummary($controllerMethod),
                    ["", "@route $routeSignature", "@async"],
                    JavascriptClientServicesGenerator::getDocumentationForContext($controllerMethod),
                    JavascriptClientServicesGenerator::getDocumentationForRouteParameters($uriParamList, $controllerMethod, $routeSignature),
                    JavascriptClientServicesGenerator::getDocumentationForQueryParamsResponseAndSchema($controllerMethod, $routeSignature),
                );
                
                $documentation = empty($documentationLines) ? "" : PHP_EOL . collect($documentationLines)->map(fn (string $l) => "     * " . $l)->join(PHP_EOL);
                
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
                    /**$documentation
                     */
                    $normalizedMethodName($paramList) {
                    $ajaxCallCode
                    }
                HEREDOC;
            }
        }

        $routeFunctionsCode = join(PHP_EOL, $routeFunctions);

        $serviceClassCode = <<<HEREDOC
        /**
         * @typedef {number} Int
         * @typedef {number} Float
         */
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

    private static function getDocumentationForContext(?ReflectionMethod $controllerMethod): array {
        $documentationLines = [];
        if (!is_null($controllerMethod)) {
            $contextParameterOrNull = array_find($controllerMethod->getParameters(), fn (ReflectionParameter $p) => class_exists(strval($p->getType())) && (new ReflectionClass(strval($p->getType())))->getAttributes(Context::class) > 0);
            if (!is_null($contextParameterOrNull)) {
                $contextClass = new ReflectionClass(strval($contextParameterOrNull->getType()));
                $contextOrEmpty = $contextClass->getAttributes(Context::class);
                $contextOrNull = array_pop($contextOrEmpty);
                if (!is_null($contextOrNull)) {
                    $context = Context::notNull($contextOrNull->newInstance());
                    $documentationLines[] = "@context {" . JavascriptClientServicesGenerator::getClassBaseName($contextParameterOrNull->getType()) . "} - {$context->description}";
                }
            }
        }

        return $documentationLines;
    }

    private static function getDocumentationForQueryParamsResponseAndSchema(?ReflectionMethod $controllerMethod, string $routeSignature): array {
        $documentationLines = [];
        if (!is_null($controllerMethod)) {
            $queryParams = collect($controllerMethod->getAttributes(QueryParameter::class))
                ->map(fn (ReflectionAttribute $a) => $a->newInstance())
                ->map(fn (QueryParameter $p) => "@param " . JavascriptClientServicesGenerator::mapPhpTypeStringToJsDoc($p->type) . " {$p->name} - {$p->description}")
                ->all();
            if (count($queryParams) > 0) {
                $documentationLines[] = "";
                $documentationLines[] = "Query Parameters:";
                $documentationLines = array_merge($documentationLines, $queryParams);
                $documentationLines[] = "";
            }
        
            $payloadOrEmpty = $controllerMethod->getAttributes(Payload::class);
            $payloadOrNull = array_pop($payloadOrEmpty);
            if (!is_null($payloadOrNull)) {
                $payload = Payload::notNull($payloadOrNull->newInstance());
                $documentationLines[] = "";
                $documentationLines[] = "POST Body:";
                if (class_exists($payload->classNameOrDescription)) {
                    $documentationLines = array_merge($documentationLines, JavascriptClientServicesGenerator::describePostDataAsJsDocParams(new ReflectionClass($payload->classNameOrDescription)));
                }
                else {
                    $documentationLines[] = "@param {object} - {$payload->classNameOrDescription}";
                }
            }
        
            $schemas = [];
            $responseElements = collect($controllerMethod->getAttributes(ResponseElement::class))
                ->map(fn (ReflectionAttribute $a) => $a->newInstance())
                ->map(function (ResponseElement $e) use (&$schemas) {
                    if (class_exists($e->type)) {
                        $schemas[$e->type] = $e->type;
                    }
                    return "  {$e->member}: " . JavascriptClientServicesGenerator::mapPhpTypeStringToTs($e->type) . ", // {$e->description}";
                });
            $responseElements = $responseElements->merge(collect($controllerMethod->getAttributes(ResponseCollection::class))
                ->map(fn (ReflectionAttribute $a) => $a->newInstance())
                ->map(function (ResponseCollection $c) use (&$schemas) {
                    if (class_exists($c->type)) {
                        $schemas[$c->type] = $c->type;
                    }
                    return "  {$c->member}: " . JavascriptClientServicesGenerator::mapPhpTypeStringToTs($c->type) . "[], // {$c->description}";
                })
            );
            if (count($responseElements) > 0) {
                $documentationLines[] = "@returns {Promise<{";
                $documentationLines = array_merge($documentationLines, $responseElements->all());
                $documentationLines[] = "}>}";
            }
            if (count($schemas) > 0) {
                $documentationLines[] = "";
                collect($schemas)
                    ->each(function (string $schema) use (&$documentationLines) {
                        $documentationLines = array_merge($documentationLines, JavascriptClientServicesGenerator::describeSchemaAsJsDocTypedef(new ReflectionClass($schema)));
                    });
            }
        
            $responseOrEmpty = $controllerMethod->getAttributes(Response::class);
            $responseOrNull = array_pop($responseOrEmpty);
            if (!is_null($responseOrNull)) {
                $response = Response::notNull($responseOrNull->newInstance());
                if (count($responseElements) > 0) {
                    throw new LogicException("A Response '{$response->classNameOrDescription}' has been documented for route [$routeSignature] there was also ResponseElement or ResponseCollection specified. These attributes are exclusive.");
                }
                $documentationLines[] = "";
                if (class_exists($response->classNameOrDescription)) {
                    $documentationLines[] = "@returns {Promise<" . JavascriptClientServicesGenerator::getClassBaseName($response->classNameOrDescription) . ">}";
                    $documentationLines[] = "";
                    $documentationLines = array_merge($documentationLines, JavascriptClientServicesGenerator::describeSchemaAsJsDocTypedef(new ReflectionClass($response->classNameOrDescription)));
                }
                else {
                    $documentationLines[] = "@returns {Promise} - {$response->classNameOrDescription}";
                }
            }
        }

        return $documentationLines;
    }

    private static function getDocumentationForSummary(?ReflectionMethod $controllerMethod): array {
        $documentationLines = [];
        if (!is_null($controllerMethod)) {
            $summaryOrEmpty = $controllerMethod->getAttributes(Summary::class);
            $summaryOrNull = array_pop($summaryOrEmpty);
            if (!is_null($summaryOrNull)) {
                $summary = Summary::notNull($summaryOrNull->newInstance());
                $documentationLines[] = $summary->value;
            }
        }

        return $documentationLines;
    }

    private static function getRouteSignature(RoutingRoute $route, string $method): string {
        return "$method {$route->uri()} ({$route->getName()})";
    }

    private static function mapPhpTypeStringToJsDoc(string $typeAsString):string {
        return "{" . JavascriptClientServicesGenerator::mapPhpTypeStringToTs($typeAsString) . "}";
    }

    private static function mapPhpTypeStringToTs(string $typeAsString):string {
        $isNullable = substr($typeAsString, 0, 1) === "?";

        if ($isNullable) {
            $typeAsString = substr($typeAsString, 1);
        }

        if (class_exists($typeAsString)) {
            return ($isNullable ? "?" : "") . JavascriptClientServicesGenerator::getClassBaseName($typeAsString);
        }

        $map = [
            'int'     => 'Int',
            'float'   => 'Float',
            'string'  => 'string',
            'bool'    => 'boolean',
            'array'   => 'Array<any>',
            'object'  => 'Object',
            'mixed'   => '*',
        ];

        return ($isNullable ? "?" : "") . ($map[$typeAsString] ?? $typeAsString);
    }

    private static function mapPhpTypeToJsDoc(ReflectionType $type): string {
        $typeAsString = strval($type);

        return JavascriptClientServicesGenerator::mapPhpTypeStringToJsDoc($typeAsString);
    }

    private static function mapPhpTypeToTs(ReflectionType $type): string {
        $typeAsString = strval($type);
        
        return JavascriptClientServicesGenerator::mapPhpTypeStringToTs($typeAsString);
    }

    private static function getDocumentationForRouteParameters(array $routeParamList, ?ReflectionMethod $controllerMethod, string $routeSignature): array {
        $documentationLines = [];
        if (count($routeParamList) > 0) {
            $documentationLines[] = "";
            $documentationLines[] = "URI Parameters:";
            if (!is_null($controllerMethod)) {
                $paramTypes = collect($controllerMethod->getParameters())
                    ->mapWithKeys(fn (ReflectionParameter $p) => [$p->getName() => $p->getType()]);
                $documentedParamDescriptions = collect($controllerMethod->getAttributes(RouteParameter::class))
                    ->map(fn (ReflectionAttribute $a) => $a->newInstance())
                    ->mapWithKeys(function (RouteParameter $p) use ($paramTypes, $controllerMethod, $routeSignature) {
                        if (!$paramTypes->has($p->name)) {
                            throw new LogicException("A RouteParameter '{$p->name}' has been documented for route [$routeSignature] but method {$controllerMethod->getDeclaringClass()->getName()}::{$controllerMethod->getName()} has no such parameter.");
                        }
                        return [$p->name => $p->description];
                    });
                $documentationLines = array_merge($documentationLines, collect($routeParamList)
                    ->map(fn (string $p) => "@param " . JavascriptClientServicesGenerator::mapPhpTypeToJsDoc($paramTypes->get($p, 'type_unknown')) . " $p " . ($documentedParamDescriptions->has($p) ? " - {$documentedParamDescriptions->get($p)}" : ""))
                    ->all()
                );
            }
            else {
                $documentationLines = array_merge(collect($routeParamList)
                    ->map(fn (string $p) => "@param {Unknown} $p")
                    ->all()
                );
            }
        }

        return $documentationLines;
    }

    private static function getClassBaseName(string $fullyQualifiedClassName): string {
        $parts = explode('\\', $fullyQualifiedClassName);
        return end($parts);
    }

    private static function describeSchemaAsJsDocTypedef(ReflectionClass $class): array {
        $documentationLines = [];
        $documentationLines[] = "@typedef {Object} " . $class->getShortName();
        collect(Reflection::getPublicProperties($class->getName()))
            ->each(function (ReflectionProperty $p) use (&$documentationLines, $class) {
                if ($p->getDeclaringClass()->getName() != $class->getName()) {
                    return;
                }

                $documentationLines[] = "@property " . JavascriptClientServicesGenerator::mapPhpTypeToJsDoc($p->getType()) . " {$p->getName()}";
        });

        return $documentationLines;
    }

    private static function describePostDataAsJsDocParams(ReflectionClass $class): array {
        $documentationLines = [];
        $documentationLines[] = "@param {{";
        collect(Reflection::getPublicProperties($class->getName()))
            ->each(function (ReflectionProperty $p) use (&$documentationLines, $class) {
                if ($p->getDeclaringClass()->getName() != $class->getName()) {
                    return;
                }

                $documentationLines[] = "  {$p->getName()}: " . JavascriptClientServicesGenerator::mapPhpTypeToTs($p->getType()) . ",";
        });
        $documentationLines[] = "}} body - POST body data";

        return $documentationLines;
    }
}