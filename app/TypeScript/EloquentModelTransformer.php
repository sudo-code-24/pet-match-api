<?php

namespace App\TypeScript;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Model;
use ReflectionClass;
use ReflectionException;
use ReflectionMethod;
use Spatie\LaravelTypeScriptTransformer\Transformers\LaravelAttributedClassTransformer;
use Spatie\TypeScriptTransformer\Data\TransformationContext;
use Spatie\TypeScriptTransformer\PhpNodes\PhpClassNode;
use Spatie\TypeScriptTransformer\TypeResolvers\Data\ParsedClass;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptArray;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptBoolean;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNode;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNull;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptNumber;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptObject;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptProperty;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptReference;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptString;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnknown;
use Spatie\TypeScriptTransformer\TypeScriptNodes\TypeScriptUnion;

class EloquentModelTransformer extends LaravelAttributedClassTransformer
{
    protected function getTypeScriptNode(
        PhpClassNode $phpClassNode,
        TransformationContext $context,
        ?ParsedClass $parsedClass = null,
    ): TypeScriptNode {
        $className = $phpClassNode->getName();

        if (! is_subclass_of($className, Model::class)) {
            return parent::getTypeScriptNode($phpClassNode, $context, $parsedClass);
        }

        /** @var Model $model */
        $model = new $className();

        $fillable = $model->getFillable();
        $casts = $model->getCasts();
        $hidden = $model->getHidden();

        $fieldNames = array_values(array_unique(array_merge($fillable, array_keys($casts))));
        $fieldNames = array_values(array_filter($fieldNames, fn (string $name): bool => ! in_array($name, $hidden, true)));

        $properties = [];

        foreach ($fieldNames as $fieldName) {
            $properties[] = new TypeScriptProperty(
                $fieldName,
                $this->mapCastToTypeScriptNode($casts[$fieldName] ?? null),
                true
            );
        }

        foreach ($this->buildRelationshipProperties($model) as $relationshipProperty) {
            $properties[] = $relationshipProperty;
        }

        return new TypeScriptObject($properties);
    }

    /**
     * @return array<TypeScriptProperty>
     */
    private function buildRelationshipProperties(Model $model): array
    {
        $properties = [];
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->isStatic()
                || $method->getNumberOfRequiredParameters() > 0
                || $method->getDeclaringClass()->getName() !== $reflection->getName()
            ) {
                continue;
            }

            try {
                $relation = $model->{$method->getName()}();
            } catch (\Throwable) {
                continue;
            }

            if (! is_object($relation) || ! method_exists($relation, 'getRelated')) {
                continue;
            }

            $relatedClass = $relation->getRelated()::class;

            if (! $this->hasTypeScriptAttribute($relatedClass)) {
                continue;
            }

            $relatedReference = TypeScriptReference::referencingPhpClass($relatedClass);
            $relationType = match (true) {
                $relation instanceof HasMany,
                $relation instanceof BelongsToMany => new TypeScriptArray([$relatedReference]),
                $relation instanceof BelongsTo => new TypeScriptUnion([$relatedReference, new TypeScriptNull()]),
                default => new TypeScriptUnion([$relatedReference, new TypeScriptNull()]),
            };

            $properties[] = new TypeScriptProperty($method->getName(), $relationType, true);
        }

        return $properties;
    }

    private function hasTypeScriptAttribute(string $className): bool
    {
        try {
            $reflection = new ReflectionClass($className);
        } catch (ReflectionException) {
            return false;
        }

        return $reflection->getAttributes(TypeScript::class) !== [];
    }

    private function mapCastToTypeScriptNode(?string $cast): TypeScriptNode
    {
        if ($cast === null || $cast === '') {
            return new TypeScriptUnion([new TypeScriptString(), new TypeScriptNull()]);
        }

        $normalized = strtolower(trim(explode(':', $cast)[0]));

        return match ($normalized) {
            'int', 'integer', 'real', 'float', 'double', 'decimal' => new TypeScriptUnion([new TypeScriptNumber(), new TypeScriptNull()]),
            'bool', 'boolean' => new TypeScriptUnion([new TypeScriptBoolean(), new TypeScriptNull()]),
            'array', 'json', 'object', 'collection' => new TypeScriptUnion([new TypeScriptUnknown(), new TypeScriptNull()]),
            'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp' => new TypeScriptUnion([new TypeScriptString(), new TypeScriptNull()]),
            default => new TypeScriptUnion([new TypeScriptString(), new TypeScriptNull()]),
        };
    }
}
