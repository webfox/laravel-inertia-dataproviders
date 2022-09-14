<?php
declare(strict_types=1);

namespace Webfox\InertiaDataProviders;

use ReflectionClass;
use Inertia\LazyProp;
use Inertia\Response;
use ReflectionMethod;
use ReflectionProperty;
use ReflectionNamedType;
use Symfony\Component\VarDumper\VarDumper;
use Illuminate\Contracts\Support\Arrayable;

abstract class DataProvider implements Arrayable
{
    protected array|Arrayable $staticData = [];

    protected array $excludedMethods = ['__construct', 'toArray', 'dd', 'dump',];

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
                // @phpstan-ignore-next-line
                if ($returnType instanceof ReflectionNamedType && $returnType->getName() === LazyProp::class) {
                    return [$method->name => $method->invoke($this)];
                }

                return [$method->name => fn () => app()->call([$this, $method->name])];
            });

        return collect()->merge($staticData)->merge($convertedProperties)->merge($convertedMethods)->toArray();
    }

    public function dump(): static
    {
        $response = new Response('', []);
        $props    = $response->resolvePropertyInstances($this->toArray(), request());
        VarDumper::dump($props);
        return $this;
    }

    #[\JetBrains\PhpStorm\NoReturn]
    public function dd()
    {
        $this->dump();
        exit(1);
    }
}
