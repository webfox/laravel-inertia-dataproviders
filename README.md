# Laravel Data Providers for Inertia.js

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webfox/laravel-inertia-dataproviders.svg?style=flat-square)](https://packagist.org/packages/webfox/laravel-inertia-dataproviders)
[![GitHub Tests Action Status](https://img.shields.io/github/workflow/status/webfox/laravel-inertia-dataproviders/run-tests?label=tests)](https://github.com/webfox/laravel-inertia-dataproviders/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://img.shields.io/github/workflow/status/webfox/laravel-inertia-dataproviders/Check%20&%20fix%20styling?label=code%20style)](https://github.com/webfox/laravel-inertia-dataproviders/actions?query=workflow%3A"Check+%26+fix+styling"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/webfox/laravel-inertia-dataproviders.svg?style=flat-square)](https://packagist.org/packages/webfox/laravel-inertia-dataproviders)

Data providers encapsulate logic for Inertia views, keep your controllers clean and simple.

## Installation

We assume you've already got Inertia installed, so you can just install this package via composer:

```bash
composer require webfox/laravel-inertia-dataproviders
```

## Usage

### Using a Data Provider
Data providers take advantage of the fact that `Inertia::render` can accept `Arrayable`s as well as standard arrays and so are used   
instead a standard array in the `Inertia::render` method. They can also be used as discrete attributes in the data array.

```php
use App\Models\Demo;
use App\DataProviders\DemoDataProvider;

class DemoController extends Controller
{
    public function show(Demo $demo)
    {
        return Inertia::render('DemoPage', new DemoDataProvider($demo));
    }
    
    public function edit(Demo $demo)
    {
        return Inertia::render('DemoPage', [
          'some' => 'data',
          'more' => 'data',
          'demo' => new DemoDataProvider($demo),
        ]);
    }
}
```

### What Does a Data Provider Look Like?

Data providers can live anywhere, but we'll use `App/Http/DataProviders` for this example.

The simplest data provider is just a class that extends `DataProvider`, any public methods or properties will be available to the page as data.  
A **fully featured** data provider might look like this:

```php
<?php
declare(strict_types=1);

namespace App\Http\DataProviders;

use Inertia\LazyProp;
use App\Services\InjectedDependency;
use Webfox\InertiaDataProviders\DataProvider;

class DemoDataProvider extends DataProvider
{

    public function __construct(
        /*
         * All public properties are automatically available in the page
         * This would be available to the page as `demo`
         */
        public Demo $demo;
    )
    {
        /*
         * Data providers have a `staticData` property, which you can use to add any data that doesn't warrant a full
         * property or separate method
         */
        $this->staticData = [
            /*
             * This will be available to the page as `title`
             */
            'title' => $this->calculateTitle($demo),
        ];
    }
    
    /*
     * All public methods are automatically evaluated as data and provided to the page.
     * ALWAYS included on first visit, OPTIONALLY included on partial reloads, ALWAYS evaluated
     * This would be available to the page as `someData`.
     * Additionally these methods are resolved through Laravel's service container, so any parameters will be automatically resolved.
     */
    public function someData(InjectedDependency $example): array
    {
        return [
            'some' => $example->doThingWith('some'),
            'more' => 'data',
        ];
    }
    
    /*
     * If a method returns a `Closure` it will be evaluated as a lazy property.
     * ALWAYS included on first visit, OPTIONALLY included on partial reloads, ONLY evaluated when needed
     * Additionally the callback methods are resolved through Laravel's service container, so any parameters will be automatically resolved.
     * @see https://inertiajs.com/partial-reloads#lazy-data-evaluation
     */
    public function quickLazyExample(): Closure
    {
        return function(InjectedDependency $example): string {
            return $example->formatName($this->demo->user->name);
        };
    }
    
    /*
     * If a method is typed to return a LazyProp, it will only be evaluated when requested following inertia's rules for lazy data evaluation
     * NEVER included on first visit, OPTIONALLY included on partial reloads, ONLY evaluated when needed
     * Additionally the lazy callback methods are resolved through Laravel's service container, so any parameters will be automatically resolved.
     * @see https://inertiajs.com/partial-reloads#lazy-data-evaluation
     */
    public function lazyExample(): LazyProp
    {
        return Inertia::lazy(
            fn (InjectedDependency $example) => $example->aHeavyCalculation($this->demo)
        );
    }
    
    /*
     * `protected` and `private` methods are not available to the page
     */
    protected function calculateTitle(Demo $demo): string
    {
        return $demo->name . ' Demo';
    }

}
```

### Using Multiple Data Providers
Sometimes you might find yourself wanting to return multiple DataProviders `DataProvider::collection` is the method for you.
Each DataProvider in the collection will be evaluated and merged into the page's data, later values from DataProviders will override earlier DataProviders.

```php
use App\Models\Demo;
use App\DataProviders\TabDataProvider;
use App\DataProviders\DemoDataProvider;

class DemoController extends Controller
{
    public function show(Demo $demo)
    {
        return Inertia::render('DemoPage', DataProvider::collection(
            new TabDataProvider(current: 'demo'),
            new DemoDataProvider($demo),
        ));
    }
}
```

You can also conditionally include DataProviders in the collection:
```php
use App\Models\Demo;
use App\DataProviders\TabDataProvider;
use App\DataProviders\DemoDataProvider;
use App\DataProviders\EditDemoDataProvider;
use App\DataProviders\CreateVenueDataProvider;

class DemoController extends Controller
{
    public function show(Demo $demo)
    {
        return Inertia::render('DemoPage', DataProvider::collection(
            new TabDataProvider(current: 'demo'),
            new DemoDataProvider($demo),
        )->when($demo->has_venue, function (DataProviderCollection $collection) use($demo) {
            $collection->push(new CreateVenueDataProvider($demo));
        })
        ->unless($demo->locked, function (DataProviderCollection $collection) use($demo) {
            $collection->push(new EditDemoDataProvider($demo));
        }));
    }
}
```

Or you can use the `DataProviderCollection::add` method to add a DataProvider to the collection later:
```php
use App\Models\Demo;
use App\DataProviders\TabDataProvider;
use App\DataProviders\DemoDataProvider;
use App\DataProviders\CreateVenueDataProvider;

class DemoController extends Controller
{
    public function show(Demo $demo)
    {
        $pageData = DataProvider::collection(
            new TabDataProvider(current: 'demo'),
            new DemoDataProvider($demo),
        );
        
        if($demo->has_venue) {
            $pageData->add(new CreateVenueDataProvider($demo));
        }

        return Inertia::render('DemoPage', $pageData);
    }
}
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

We welcome all contributors to the project.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
