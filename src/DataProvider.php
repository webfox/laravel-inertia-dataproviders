<?php

declare(strict_types=1);

namespace Webfox\InertiaDataProviders;

use Closure;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Jsonable;
use Inertia\LazyProp;
use Inertia\Response;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use Symfony\Component\VarDumper\VarDumper;
use Webfox\InertiaDataProviders\AttributeNameFormatters\AttributeNameFormatter;

abstract class DataProvider implements Arrayable, Jsonable
{
    protected array|Arrayable $staticData = [];

    protected array $excludedMethods = ['__construct', 'toArray', 'toNestedArray', 'toJson', 'dd', 'dump',];

    public static function collection(DataProvider|array ...$dataProviders): DataProviderCollection
    {
        return new DataProviderCollection(...$dataProviders);
    }

    public function toArray(): array
    {
        $staticData = $this->staticData instanceof Arrayable ? $this->staticData->toArray() : $this->staticData;
        $reflectionClass = (new ReflectionClass($this));

        $convertedProperties = collect($reflectionClass->getProperties(ReflectionProperty::IS_PUBLIC))
            ->filter(fn (ReflectionProperty $property) => ! $property->isStatic())
            ->mapWithKeys(fn (ReflectionProperty $property) => [$property->getName() => $property->getValue($this)])
            ->map(fn ($value) => $value instanceof Arrayable ? $value->toArray() : $value);

        $convertedMethods = collect($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC))
            ->filter(fn (ReflectionMethod $method) => ! $method->isStatic())
            ->filter(fn (ReflectionMethod $method) => ! $method->isStatic() && ! in_array($method->name, $this->excludedMethods))
            ->mapWithKeys(function (ReflectionMethod $method) {
                $returnType = $method->getReturnType();
                if ($returnType instanceof ReflectionNamedType && in_array($returnType->getName(), [LazyProp::class, Closure::class])) {
                    return [$method->name => $method->invoke($this)];
                }

                return [$method->name => fn () => app()->call([$this, $method->name])];
            });

        return collect()
            ->merge($staticData)
            ->merge($convertedProperties)
            ->merge($convertedMethods)
            ->mapWithKeys(fn ($value, $key) => [$this->attributeNameFormatter()($key) => $value])
            ->toArray();
    }

    public function toNestedArray(): array
    {
        $response = new Response('', []);

        return $response->resolvePropertyInstances($this->toArray(), request());
    }

    public function toJson($options = 0): string
    {
        return json_encode($this->toNestedArray(), $options);
    }

    public function dump(): static
    {
        VarDumper::dump($this->toNestedArray());

        return $this;
    }

    #[\JetBrains\PhpStorm\NoReturn]
    public function dd()
    {
        $this->dump();
        exit(1);
    }

    protected function attributeNameFormatter(): AttributeNameFormatter
    {
        return app()->make(config('inertia-dataproviders.attribute_name_formatter'));
    }
}
