<?php
declare(strict_types=1);

namespace Webfox\InertiaDataProviders;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Contracts\Support\Arrayable;

class DataProviderCollection implements Arrayable
{
    /** @var Collection<DataProvider> */
    public Collection $dataProviders;

    public function __construct(DataProvider|array ...$dataProviders)
    {
        $this->dataProviders = collect();
        $this->add($dataProviders);
    }

    public function add(DataProvider|array ...$dataProviders): static
    {
        $this->dataProviders = $this->dataProviders->concat(collect($dataProviders)
            ->map(fn(DataProvider|array $dataProvider) => Arr::wrap($dataProvider))
            ->flatten()
            ->filter()
        );

        return $this;
    }

    /**
     * @param class-string $dataProvider
     */
    public function remove(string $dataProvider): static
    {
        $this->dataProviders = $this->dataProviders->filter(fn(DataProvider $instance) => $instance::class !== $dataProvider);

        return $this;
    }

    public function collection(): Collection
    {
        return $this->dataProviders;
    }

    public function empty(): static
    {
        $this->dataProviders = collect();
        return $this;
    }

    /**
     * @param bool|callable $condition
     * @param callable $callback
     * @return $this
     */
    public function when(bool|callable $condition, callable $callback): static
    {
        if (value($condition)) {
            $callback($this);
        }

        return $this;
    }

    /**
     * @param bool|callable $condition
     * @param callable $callback
     * @return $this
     */
    public function unless(bool|callable $condition, callable $callback): static
    {
        if (!value($condition)) {
            $callback($this);
        }

        return $this;
    }

    public function toArray(): array
    {
        return $this->dataProviders
            ->flatMap(fn(DataProvider $dataProvider) => $dataProvider->toArray())
            ->all();
    }
}