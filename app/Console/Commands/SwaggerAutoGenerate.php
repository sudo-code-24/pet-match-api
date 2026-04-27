<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use ReflectionMethod;
use Throwable;

class SwaggerAutoGenerate extends Command
{
    protected $signature = 'swagger:auto {--force}';
    protected $description = 'Auto-generate Swagger annotations (v3 robust)';

    public function handle(): int
    {
        $targetsByClass = $this->collectTargetsByClass();
        $writtenMethods = 0;
        $skippedMethods = 0;
        $errorMethods = 0;

        foreach ($targetsByClass as $class => $targets) {
            try {
                $result = $this->injectForClassTargets($class, $targets);
                $writtenMethods += $result['written'];
                $skippedMethods += $result['skipped'];

                foreach ($result['logs'] as $log) {
                    $this->line($log);
                }
            } catch (Throwable $e) {
                $errorMethods += count($targets);
                $this->error("XX {$class}: {$e->getMessage()}");
            }
        }

        $this->newLine();
        $this->info(
            "Swagger v3 generation completed. written={$writtenMethods}, skipped={$skippedMethods}, errors={$errorMethods}"
        );

        return self::SUCCESS;
    }

    /**
     * @return array<string, array<int, array{class: string, method: string, tag: string, operations: array<int, array{verb: string, path: string, pathParameters: array<int, string>, hasRequestBody: bool}>}>>
     */
    private function collectTargetsByClass(): array
    {
        $groupedByMethod = [];
        $appControllerNamespacePrefix = app()->getNamespace() . 'Http\\Controllers\\';

        /** @var LaravelRoute $route */
        foreach (Route::getRoutes() as $route) {
            $resolved = $this->resolveControllerAction($route);

            if ($resolved === null) {
                continue;
            }

            $class = $resolved['class'];
            $method = $resolved['method'];
            if (!str_starts_with($class, $appControllerNamespacePrefix)) {
                continue;
            }

            $operations = $this->extractOperationsFromRoute($route);

            if ($operations === []) {
                continue;
            }

            $groupKey = $class . '@' . $method;
            if (!isset($groupedByMethod[$groupKey])) {
                $groupedByMethod[$groupKey] = [
                    'class' => $class,
                    'method' => $method,
                    'tag' => $this->makeTag($class),
                    'operations' => [],
                ];
            }

            foreach ($operations as $operation) {
                $dedupe = $operation['verb'] . ' ' . $operation['path'];
                $groupedByMethod[$groupKey]['operations'][$dedupe] = $operation;
            }
        }

        $targetsByClass = [];
        foreach (array_values($groupedByMethod) as $target) {
            $target['operations'] = array_values($target['operations']);
            $targetsByClass[$target['class']][] = $target;
        }

        return $targetsByClass;
    }

    /**
     * @return array{class: string, method: string}|null
     */
    private function resolveControllerAction(LaravelRoute $route): ?array
    {
        $action = $route->getAction();
        $candidates = [];

        if (array_key_exists('controller', $action)) {
            $candidates[] = $action['controller'];
        }
        if (array_key_exists('uses', $action)) {
            $candidates[] = $action['uses'];
        }

        foreach ($candidates as $candidate) {
            if ($candidate instanceof \Closure) {
                continue;
            }

            if (is_array($candidate) && count($candidate) === 2) {
                [$class, $method] = $candidate;

                if (is_string($class) && is_string($method) && class_exists($class)) {
                    return ['class' => ltrim($class, '\\'), 'method' => $method];
                }
            }

            if (!is_string($candidate) || $candidate === '') {
                continue;
            }

            if (str_contains($candidate, '@')) {
                [$class, $method] = explode('@', $candidate, 2);
                $class = ltrim($class, '\\');

                if (class_exists($class) && $method !== '') {
                    return ['class' => $class, 'method' => $method];
                }

                continue;
            }

            $class = ltrim($candidate, '\\');
            if (class_exists($class)) {
                return ['class' => $class, 'method' => '__invoke'];
            }
        }

        return null;
    }

    /**
     * @return array<int, array{verb: string, path: string, pathParameters: array<int, string>, hasRequestBody: bool}>
     */
    private function extractOperationsFromRoute(LaravelRoute $route): array
    {
        $path = '/' . ltrim($route->uri(), '/');
        $pathParameters = $this->extractPathParameters($path);
        $methods = array_map('strtoupper', $route->methods());
        $methods = array_values(array_diff($methods, ['HEAD', 'OPTIONS']));

        $operations = [];
        foreach ($methods as $httpMethod) {
            $swaggerVerb = $this->toSwaggerVerb($httpMethod);
            if ($swaggerVerb === null) {
                continue;
            }

            $operations[] = [
                'verb' => $swaggerVerb,
                'path' => $path,
                'pathParameters' => $pathParameters,
                'hasRequestBody' => in_array($httpMethod, ['POST', 'PUT', 'PATCH'], true),
            ];
        }

        return $operations;
    }

    /**
     * @return array<int, string>
     */
    private function extractPathParameters(string $path): array
    {
        preg_match_all('/\{([^}]+)\}/', $path, $matches);

        return array_values(array_unique($matches[1] ?? []));
    }

    private function toSwaggerVerb(string $httpMethod): ?string
    {
        return match ($httpMethod) {
            'GET' => 'Get',
            'POST' => 'Post',
            'PUT' => 'Put',
            'PATCH' => 'Patch',
            'DELETE' => 'Delete',
            default => null,
        };
    }

    private function makeTag(string $controllerClass): string
    {
        $tag = str_replace('Controller', '', class_basename($controllerClass));

        return $tag !== '' ? $tag : 'Default';
    }

    /**
     * @param array<int, array{class: string, method: string, tag: string, operations: array<int, array{verb: string, path: string, pathParameters: array<int, string>, hasRequestBody: bool}>}> $targets
     * @return array{written: int, skipped: int, logs: array<int, string>}
     */
    private function injectForClassTargets(string $class, array $targets): array
    {
        $result = ['written' => 0, 'skipped' => 0, 'logs' => []];
        $refClass = new ReflectionClass($class);
        $file = $refClass->getFileName();

        if (!is_string($file) || $file === '' || !is_file($file)) {
            foreach ($targets as $target) {
                $result['skipped']++;
                $result['logs'][] = ".. skipped {$target['class']}@{$target['method']} (file not found)";
            }

            return $result;
        }

        $realFile = realpath($file) ?: $file;
        $realApp = realpath(app_path()) ?: app_path();
        if (!str_starts_with($realFile, $realApp . DIRECTORY_SEPARATOR)) {
            foreach ($targets as $target) {
                $result['skipped']++;
                $result['logs'][] = ".. skipped {$target['class']}@{$target['method']} (outside app path)";
            }

            return $result;
        }

        $content = file_get_contents($file);
        if ($content === false) {
            foreach ($targets as $target) {
                $result['skipped']++;
                $result['logs'][] = ".. skipped {$target['class']}@{$target['method']} (file unreadable)";
            }

            return $result;
        }

        $lines = explode("\n", $content);
        $importAdded = $this->ensureOpenApiAliasImport($lines);
        $plans = [];

        foreach ($targets as $target) {
            if (!$refClass->hasMethod($target['method'])) {
                $result['skipped']++;
                $result['logs'][] = ".. skipped {$target['class']}@{$target['method']} (method not found)";
                continue;
            }

            $anchorLine = $this->findMethodLine($lines, $target['method']);
            if ($anchorLine === null) {
                $result['skipped']++;
                $result['logs'][] = ".. skipped {$target['class']}@{$target['method']} (function line not found)";
                continue;
            }

            $plans[] = [
                'target' => $target,
                'anchorLine' => $anchorLine,
                'requestClass' => $this->detectRequestClass($refClass->getMethod($target['method'])),
                'responseClass' => $this->detectResponseResourceClass($refClass->getMethod($target['method']), $lines),
                'requestProperties' => $this->extractRequestProperties(
                    $this->detectRequestClass($refClass->getMethod($target['method']))
                ),
                'responseProperties' => $this->extractResponseProperties(
                    $this->detectResponseResourceClass($refClass->getMethod($target['method']), $lines)
                ),
                'inlineResponseDataProperties' => $this->extractInlineResponseDataProperties(
                    $refClass->getMethod($target['method']),
                    $lines
                ),
                'serviceResponseDataProperties' => $this->extractServiceResponseDataProperties(
                    $refClass,
                    $refClass->getMethod($target['method']),
                    $lines
                ),
                'inlineSuccessResponseProperties' => $this->extractInlineSuccessResponseProperties(
                    $refClass->getMethod($target['method']),
                    $lines
                ),
            ];
        }

        usort(
            $plans,
            static fn (array $a, array $b): int => $b['anchorLine'] <=> $a['anchorLine']
        );

        foreach ($plans as $plan) {
            $target = $plan['target'];
            $methodLine = $plan['anchorLine'];
            $anchorLine = $this->resolveAnchorLine($lines, $methodLine);

            // Detect existing OA attributes/docblocks immediately attached to this method.
            $attachedLines = array_slice($lines, $anchorLine - 1, max(0, $methodLine - $anchorLine));
            $attachedText = implode("\n", $attachedLines);

            $windowStart = max(1, $anchorLine - 120);
            $windowLines = array_slice($lines, $windowStart - 1, $anchorLine - $windowStart);
            $windowText = implode("\n", $windowLines);

            if (
                (str_contains($attachedText, '@OA\\') || str_contains($attachedText, '#[OA\\')
                || str_contains($windowText, '@OA\\') || str_contains($windowText, '#[OA\\'))
                && !$this->option('force')
            ) {
                $result['skipped']++;
                $result['logs'][] = ".. skipped {$target['class']}@{$target['method']} (already has @OA)";
                continue;
            }

            $indent = $this->detectIndent($lines[$anchorLine - 1] ?? '');
            $attributes = $this->buildAttributeLines(
                $target['operations'],
                $target['tag'],
                $indent,
                $plan['requestClass'],
                $plan['responseClass'],
                $plan['requestProperties'],
                $plan['responseProperties'],
                $plan['inlineResponseDataProperties'],
                $plan['serviceResponseDataProperties'],
                $plan['inlineSuccessResponseProperties']
            );
            array_splice($lines, $anchorLine - 1, 0, $attributes);
            $result['written']++;
            $result['logs'][] = "OK {$target['class']}@{$target['method']}";
        }

        $updated = implode("\n", $lines);
        if ($updated !== $content) {
            file_put_contents($file, $updated);
        }

        return $result;
    }

    private function findMethodLine(array $lines, string $method): ?int
    {
        $pattern = '/\bfunction\s+' . preg_quote($method, '/') . '\s*\(/';
        foreach ($lines as $index => $line) {
            if (preg_match($pattern, $line) === 1) {
                return $index + 1;
            }
        }

        return null;
    }

    /**
     * Ensure "@OA\..." docblocks can be parsed by adding:
     * use OpenApi\Annotations as OA;
     */
    private function ensureOpenApiAliasImport(array &$lines): bool
    {
        $updated = false;

        foreach ($lines as $line) {
            if (preg_match('/^\s*use\s+OpenApi\\\\Attributes\s+as\s+OA\s*;\s*$/', $line) === 1) {
                return $updated;
            }
        }

        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*use\s+OpenApi\\\\Annotations\s+as\s+OA\s*;\s*$/', $line) === 1) {
                $lines[$index] = 'use OpenApi\\Attributes as OA;';
                $updated = true;
                return true;
            }
        }

        $namespaceLine = null;
        $lastUseLine = null;
        foreach ($lines as $index => $line) {
            if ($namespaceLine === null && preg_match('/^\s*namespace\s+[^;]+;\s*$/', $line) === 1) {
                $namespaceLine = $index;
            }
            if (preg_match('/^\s*use\s+[^;]+;\s*$/', $line) === 1) {
                $lastUseLine = $index;
            }
        }

        $insertIndex = null;
        if ($lastUseLine !== null) {
            $insertIndex = $lastUseLine + 1;
        } elseif ($namespaceLine !== null) {
            $insertIndex = $namespaceLine + 1;
        } else {
            $insertIndex = 1;
        }

        array_splice($lines, $insertIndex, 0, ['use OpenApi\\Annotations as OA;']);
        $lines[$insertIndex] = 'use OpenApi\\Attributes as OA;';
        $updated = true;

        return $updated;
    }

    private function resolveAnchorLine(array $lines, int $startLine): int
    {
        $lineIndex = max(1, min($startLine, count($lines)));

        while ($lineIndex > 1 && str_starts_with(ltrim($lines[$lineIndex - 2] ?? ''), '#[')) {
            $lineIndex--;
        }

        return $lineIndex;
    }

    /**
     * @return array{0:int,1:int}|null
     */
    private function findDocBlockAbove(array $lines, int $lineNumber): ?array
    {
        $i = $lineNumber - 1;
        while ($i >= 1 && trim($lines[$i - 1]) === '') {
            $i--;
        }

        if ($i < 1 || !str_contains($lines[$i - 1], '*/')) {
            return null;
        }

        $end = $i;
        while ($i >= 1 && !str_contains($lines[$i - 1], '/**')) {
            $i--;
        }

        if ($i < 1) {
            return null;
        }

        return [$i, $end];
    }

    private function hasSwaggerAnnotation(string $docBlock): bool
    {
        return str_contains($docBlock, '@OA\\');
    }

    private function detectRequestClass(ReflectionMethod $method): ?string
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();
            if (!$type instanceof \ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }
            $className = $type->getName();
            if (is_subclass_of($className, FormRequest::class)) {
                return class_basename($className);
            }
        }

        return null;
    }

    private function detectResponseResourceClass(ReflectionMethod $method, array $lines): ?string
    {
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        if ($start <= 0 || $end <= 0 || $end < $start) {
            return null;
        }

        $snippet = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
        if (preg_match('/new\s+([A-Za-z_\\\\][A-Za-z0-9_\\\\]*Resource)\s*\(/', $snippet, $matches) !== 1) {
            return null;
        }

        return class_basename($matches[1]);
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function extractInlineResponseDataProperties(ReflectionMethod $method, array $lines): array
    {
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        if ($start <= 0 || $end <= 0 || $end < $start) {
            return [];
        }

        $snippet = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
        if (preg_match("/['\"]data['\"]\\s*=>\\s*\\[([\\s\\S]*?)\\]/", $snippet, $dataMatch) !== 1) {
            return [];
        }

        $properties = [];
        if (preg_match_all("/['\"]([^'\"]+)['\"]\\s*=>\\s*([^,\\n]+)/", $dataMatch[1], $propMatches, PREG_SET_ORDER) > 0) {
            foreach ($propMatches as $match) {
                $properties[] = [
                    'name' => $match[1],
                    'type' => $this->inferTypeFromExpression((string) $match[2]),
                ];
            }
        }

        return $this->uniquePropertiesByName($properties);
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function extractServiceResponseDataProperties(
        ReflectionClass $controllerClass,
        ReflectionMethod $method,
        array $lines
    ): array {
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        if ($start <= 0 || $end <= 0 || $end < $start) {
            return [];
        }

        $snippet = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));

        if (preg_match("/['\"]data['\"]\\s*=>\\s*\\$([A-Za-z_][A-Za-z0-9_]*)/", $snippet, $dataVarMatch) !== 1) {
            return [];
        }
        $dataVar = $dataVarMatch[1];

        $pattern = '/\\$' . preg_quote($dataVar, '/') . '\\s*=\\s*\\$this->([A-Za-z_][A-Za-z0-9_]*)->([A-Za-z_][A-Za-z0-9_]*)\\s*\\(/';
        if (preg_match($pattern, $snippet, $callMatch) !== 1) {
            return [];
        }

        $serviceProperty = $callMatch[1];
        $serviceMethod = $callMatch[2];
        if (!$controllerClass->hasProperty($serviceProperty)) {
            return [];
        }

        $property = $controllerClass->getProperty($serviceProperty);
        $propertyType = $property->getType();
        if (!$propertyType instanceof \ReflectionNamedType || $propertyType->isBuiltin()) {
            return [];
        }

        $serviceClass = $propertyType->getName();
        if (!class_exists($serviceClass)) {
            return [];
        }

        $serviceRef = new ReflectionClass($serviceClass);
        if (!$serviceRef->hasMethod($serviceMethod)) {
            return [];
        }

        $serviceMethodRef = $serviceRef->getMethod($serviceMethod);
        $returnType = $serviceMethodRef->getReturnType();
        if (!$returnType instanceof \ReflectionNamedType || $returnType->isBuiltin()) {
            return [];
        }

        return $this->extractPropertiesFromReturnedClass($returnType->getName());
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function extractPropertiesFromReturnedClass(string $className): array
    {
        if (!class_exists($className)) {
            return [];
        }

        if (is_subclass_of($className, Model::class)) {
            /** @var Model $model */
            $model = new $className();
            $fillable = method_exists($model, 'getFillable') ? $model->getFillable() : [];
            $casts = method_exists($model, 'getCasts') ? $model->getCasts() : [];

            $properties = [];
            foreach ($fillable as $field) {
                $cast = strtolower((string) ($casts[$field] ?? 'string'));
                $properties[] = [
                    'name' => (string) $field,
                    'type' => match (true) {
                        str_contains($cast, 'int') => 'integer',
                        str_contains($cast, 'float'), str_contains($cast, 'double'), str_contains($cast, 'decimal') => 'number',
                        str_contains($cast, 'bool') => 'boolean',
                        str_contains($cast, 'array'), str_contains($cast, 'json') => 'array',
                        default => 'string',
                    },
                ];
            }

            return $this->uniquePropertiesByName($properties);
        }

        $ref = new ReflectionClass($className);
        $properties = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            if ($property->isStatic()) {
                continue;
            }
            $type = $property->getType();
            $typeName = 'string';
            if ($type instanceof \ReflectionNamedType) {
                $raw = $type->getName();
                $typeName = match ($raw) {
                    'int' => 'integer',
                    'float' => 'number',
                    'bool' => 'boolean',
                    'array' => 'array',
                    default => 'string',
                };
            }
            $properties[] = ['name' => $property->getName(), 'type' => $typeName];
        }

        return $this->uniquePropertiesByName($properties);
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function extractInlineSuccessResponseProperties(ReflectionMethod $method, array $lines): array
    {
        $start = $method->getStartLine();
        $end = $method->getEndLine();
        if ($start <= 0 || $end <= 0 || $end < $start) {
            return [];
        }

        $snippet = implode("\n", array_slice($lines, $start - 1, $end - $start + 1));
        $needle = 'return response()->json([';
        $offset = 0;
        $payload = null;

        while (($pos = strpos($snippet, $needle, $offset)) !== false) {
            $openPos = $pos + strlen('return response()->json(');
            $candidate = $this->extractBracketedArray($snippet, $openPos);
            if ($candidate !== null) {
                $payload = $candidate; // keep last, usually success payload
            }
            $offset = $pos + strlen($needle);
        }

        if ($payload === null) {
            return [];
        }

        $properties = [];
        if (preg_match_all("/['\"]([^'\"]+)['\"]\\s*=>\\s*([^,\\n]+)/", $payload, $matches, PREG_SET_ORDER) > 0) {
            foreach ($matches as $match) {
                $properties[] = [
                    'name' => $match[1],
                    'type' => $this->inferTypeFromExpression((string) $match[2]),
                ];
            }
        }

        return $this->uniquePropertiesByName($properties);
    }

    private function extractBracketedArray(string $text, int $startPos): ?string
    {
        if (!isset($text[$startPos]) || $text[$startPos] !== '[') {
            return null;
        }

        $depth = 0;
        $length = strlen($text);
        for ($i = $startPos; $i < $length; $i++) {
            $char = $text[$i];
            if ($char === '[') {
                $depth++;
            } elseif ($char === ']') {
                $depth--;
                if ($depth === 0) {
                    return substr($text, $startPos + 1, $i - $startPos - 1);
                }
            }
        }

        return null;
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function extractRequestProperties(?string $requestClass): array
    {
        if ($requestClass === null) {
            return [];
        }

        $file = app_path("Http/Requests/{$requestClass}.php");
        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        if (preg_match('/function\s+rules\s*\([^)]*\)\s*:\s*array\s*\{([\s\S]*?)\n\s*\}/', $content, $methodMatch) !== 1) {
            return [];
        }

        $methodBody = $methodMatch[1];
        $properties = [];

        if (preg_match_all("/'([^']+)'\\s*=>\\s*\\[([^\\]]*)\\]/", $methodBody, $arrayRules, PREG_SET_ORDER) > 0) {
            foreach ($arrayRules as $match) {
                $properties[] = [
                    'name' => $match[1],
                    'type' => $this->inferTypeFromRulesString($match[2]),
                ];
            }
        }

        if (preg_match_all("/'([^']+)'\\s*=>\\s*'([^']+)'/", $methodBody, $stringRules, PREG_SET_ORDER) > 0) {
            foreach ($stringRules as $match) {
                $properties[] = [
                    'name' => $match[1],
                    'type' => $this->inferTypeFromRulesString($match[2]),
                ];
            }
        }

        return $this->uniquePropertiesByName($properties);
    }

    /**
     * @return array<int, array{name: string, type: string}>
     */
    private function extractResponseProperties(?string $resourceClass): array
    {
        if ($resourceClass === null) {
            return [];
        }

        $file = app_path("Http/Resources/{$resourceClass}.php");
        if (!is_file($file)) {
            return [];
        }

        $content = file_get_contents($file);
        if ($content === false) {
            return [];
        }

        if (preg_match('/function\s+toArray\s*\([^)]*\)\s*:\s*array\s*\{([\s\S]*?)\n\s*\}/', $content, $methodMatch) !== 1) {
            return [];
        }

        $methodBody = $methodMatch[1];
        $properties = [];

        if (preg_match_all("/'([^']+)'\\s*=>\\s*([^,\\n]+)/", $methodBody, $propMatches, PREG_SET_ORDER) > 0) {
            foreach ($propMatches as $match) {
                $properties[] = [
                    'name' => $match[1],
                    'type' => $this->inferTypeFromExpression((string) $match[2]),
                ];
            }
        }

        return $this->uniquePropertiesByName($properties);
    }

    private function inferTypeFromRulesString(string $rules): string
    {
        $value = strtolower($rules);

        return match (true) {
            str_contains($value, 'boolean') => 'boolean',
            str_contains($value, 'integer') => 'integer',
            str_contains($value, 'numeric') => 'number',
            str_contains($value, 'array') => 'array',
            default => 'string',
        };
    }

    private function inferTypeFromExpression(string $expression): string
    {
        $value = strtolower(trim($expression));

        return match (true) {
            str_contains($value, '(bool)') || str_contains($value, '=== true') || str_contains($value, '=== false') => 'boolean',
            str_contains($value, '(int)') => 'integer',
            str_contains($value, '(float)') || str_contains($value, '(double)') => 'number',
            str_contains($value, 'collect(') || str_contains($value, '->map(') || str_contains($value, '->all(') => 'array',
            str_contains($value, 'resourcecollection') => 'array',
            default => 'string',
        };
    }

    /**
     * @param array<int, array{name: string, type: string}> $properties
     * @return array<int, array{name: string, type: string}>
     */
    private function uniquePropertiesByName(array $properties): array
    {
        $seen = [];
        $result = [];

        foreach ($properties as $property) {
            $name = $property['name'];
            if (isset($seen[$name])) {
                continue;
            }
            $seen[$name] = true;
            $result[] = $property;
        }

        return $result;
    }

    /**
     * @param array<int, array{verb: string, path: string, pathParameters: array<int, string>, hasRequestBody: bool}> $operations
     * @return array<int, string>
     */
    private function buildAttributeLines(
        array $operations,
        string $tag,
        string $indent,
        ?string $requestClass,
        ?string $responseClass,
        array $requestProperties,
        array $responseProperties,
        array $inlineResponseDataProperties,
        array $serviceResponseDataProperties,
        array $inlineSuccessResponseProperties
    ): array
    {
        $lines = [];
        $escapedTag = addslashes($tag);

        foreach ($operations as $operation) {
            $verb = $operation['verb'];
            $escapedPath = addslashes($operation['path']);

            $lines[] = "{$indent}#[OA\\{$verb}(";
            $lines[] = "{$indent}    path: \"{$escapedPath}\",";
            $lines[] = "{$indent}    tags: [\"{$escapedTag}\"],";
            $lines[] = "{$indent}    summary: \"Auto generated endpoint\",";
            if ($operation['pathParameters'] !== []) {
                $lines[] = "{$indent}    parameters: [";
                foreach ($operation['pathParameters'] as $parameterName) {
                    $escapedParam = addslashes($parameterName);
                    $lines[] = "{$indent}        new OA\\Parameter(";
                    $lines[] = "{$indent}            name: \"{$escapedParam}\",";
                    $lines[] = "{$indent}            in: \"path\",";
                    $lines[] = "{$indent}            required: true,";
                    $lines[] = "{$indent}            schema: new OA\\Schema(type: \"string\")";
                    $lines[] = "{$indent}        ),";
                }
                $lines[] = "{$indent}    ],";
            }
            if ($operation['hasRequestBody']) {
                $requestTitle = addslashes($requestClass ?? 'RequestPayload');
                $lines[] = "{$indent}    requestBody: new OA\\RequestBody(";
                $lines[] = "{$indent}        required: true,";
                $lines[] = "{$indent}        content: new OA\\JsonContent(";
                $lines[] = "{$indent}            type: \"object\",";
                $lines[] = "{$indent}            title: \"{$requestTitle}\",";
                if ($requestProperties !== []) {
                    $lines[] = "{$indent}            properties: [";
                    foreach ($requestProperties as $property) {
                        $name = addslashes($property['name']);
                        $type = $property['type'];
                        if ($type === 'array') {
                            $lines[] = "{$indent}                new OA\\Property(";
                            $lines[] = "{$indent}                    property: \"{$name}\",";
                            $lines[] = "{$indent}                    type: \"array\",";
                            $lines[] = "{$indent}                    items: new OA\\Items(type: \"string\")";
                            $lines[] = "{$indent}                ),";
                        } else {
                            $lines[] = "{$indent}                new OA\\Property(property: \"{$name}\", type: \"{$type}\"),";
                        }
                    }
                    $lines[] = "{$indent}            ]";
                } else {
                    $lines[] = "{$indent}            additionalProperties: true";
                }
                $lines[] = "{$indent}        )";
                $lines[] = "{$indent}    ),";
            }
            $responseTitle = addslashes($responseClass ?? 'ResponseData');
            $lines[] = "{$indent}    responses: [";
            $lines[] = "{$indent}        new OA\\Response(";
            $lines[] = "{$indent}            response: 200,";
            $lines[] = "{$indent}            description: \"Success\",";
            $lines[] = "{$indent}            content: new OA\\JsonContent(";
            $lines[] = "{$indent}                type: \"object\",";
            $lines[] = "{$indent}                properties: [";
            if ($inlineSuccessResponseProperties !== []) {
                foreach ($inlineSuccessResponseProperties as $property) {
                    $name = addslashes($property['name']);
                    $type = $property['type'];
                    if ($type === 'array') {
                        $lines[] = "{$indent}                    new OA\\Property(";
                        $lines[] = "{$indent}                        property: \"{$name}\",";
                        $lines[] = "{$indent}                        type: \"array\",";
                        $lines[] = "{$indent}                        items: new OA\\Items(type: \"object\")";
                        $lines[] = "{$indent}                    ),";
                    } elseif ($type === 'object' && $name === 'data') {
                        $lines[] = "{$indent}                    new OA\\Property(";
                        $lines[] = "{$indent}                        property: \"data\",";
                        $lines[] = "{$indent}                        type: \"object\",";
                        $lines[] = "{$indent}                        title: \"{$responseTitle}\",";
                        $effectiveResponseProperties = $responseProperties !== []
                            ? $responseProperties
                            : ($inlineResponseDataProperties !== [] ? $inlineResponseDataProperties : $serviceResponseDataProperties);
                        if ($effectiveResponseProperties !== []) {
                            $lines[] = "{$indent}                        properties: [";
                            foreach ($effectiveResponseProperties as $nestedProperty) {
                                $nestedName = addslashes($nestedProperty['name']);
                                $nestedType = $nestedProperty['type'];
                                if ($nestedType === 'array') {
                                    $lines[] = "{$indent}                            new OA\\Property(";
                                    $lines[] = "{$indent}                                property: \"{$nestedName}\",";
                                    $lines[] = "{$indent}                                type: \"array\",";
                                    $lines[] = "{$indent}                                items: new OA\\Items(type: \"string\")";
                                    $lines[] = "{$indent}                            ),";
                                } else {
                                    $lines[] = "{$indent}                            new OA\\Property(property: \"{$nestedName}\", type: \"{$nestedType}\"),";
                                }
                            }
                            $lines[] = "{$indent}                        ]";
                        } else {
                            $lines[] = "{$indent}                        additionalProperties: true";
                        }
                        $lines[] = "{$indent}                    ),";
                    } else {
                        $lines[] = "{$indent}                    new OA\\Property(property: \"{$name}\", type: \"{$type}\"),";
                    }
                }
            } else {
                $lines[] = "{$indent}                    new OA\\Property(property: \"success\", type: \"boolean\", example: true),";
                $lines[] = "{$indent}                    new OA\\Property(property: \"message\", type: \"string\", nullable: true, example: \"Request processed successfully\"),";
                $lines[] = "{$indent}                    new OA\\Property(";
                $lines[] = "{$indent}                        property: \"data\",";
                $lines[] = "{$indent}                        type: \"object\",";
                $lines[] = "{$indent}                        title: \"{$responseTitle}\",";
                $effectiveResponseProperties = $responseProperties !== []
                    ? $responseProperties
                    : ($inlineResponseDataProperties !== [] ? $inlineResponseDataProperties : $serviceResponseDataProperties);
                if ($effectiveResponseProperties !== []) {
                    $lines[] = "{$indent}                        properties: [";
                    foreach ($effectiveResponseProperties as $property) {
                        $name = addslashes($property['name']);
                        $type = $property['type'];
                        if ($type === 'array') {
                            $lines[] = "{$indent}                            new OA\\Property(";
                            $lines[] = "{$indent}                                property: \"{$name}\",";
                            $lines[] = "{$indent}                                type: \"array\",";
                            $lines[] = "{$indent}                                items: new OA\\Items(type: \"string\")";
                            $lines[] = "{$indent}                            ),";
                        } else {
                            $lines[] = "{$indent}                            new OA\\Property(property: \"{$name}\", type: \"{$type}\"),";
                        }
                    }
                    $lines[] = "{$indent}                        ]";
                } else {
                    $lines[] = "{$indent}                        additionalProperties: true";
                }
                $lines[] = "{$indent}                    ),";
                $lines[] = "{$indent}                    new OA\\Property(";
                $lines[] = "{$indent}                        property: \"meta\",";
                $lines[] = "{$indent}                        type: \"object\",";
                $lines[] = "{$indent}                        nullable: true,";
                $lines[] = "{$indent}                        additionalProperties: true";
                $lines[] = "{$indent}                    ),";
            }
            $lines[] = "{$indent}                ]";
            $lines[] = "{$indent}            )";
            $lines[] = "{$indent}        )";
            $lines[] = "{$indent}    ]";
            $lines[] = "{$indent})]";
        }

        return $lines;
    }

    private function detectIndent(string $line): string
    {
        preg_match('/^\s*/', $line, $matches);

        return $matches[0] ?? '';
    }
}
