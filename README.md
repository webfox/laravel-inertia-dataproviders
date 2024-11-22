# Laravel Data Providers for Inertia.js

[![Latest Version on Packagist](https://img.shields.io/packagist/v/webfox/laravel-inertia-dataproviders.svg?style=flat-square)](https://packagist.org/packages/webfox/laravel-inertia-dataproviders)
[![Total Downloads](https://img.shields.io/packagist/dt/webfox/laravel-inertia-dataproviders.svg?style=flat-square)](https://packagist.org/packages/webfox/laravel-inertia-dataproviders)

Data providers encapsulate logic for Inertia views, keep your controllers clean and simple.

## Installation

Install this package via composer:

```bash
composer require webfox/laravel-inertia-dataproviders
```

Optionally publish the configuration file:

```bash
php artisan vendor:publish --provider="Webfox\InertiaDataProviders\InertiaDataProvidersServiceProvider"
````

We assume you've already got the Inertia adapter for Laravel installed.

## What Problem Does This Package Solve?
Controllers in Laravel are meant to be slim. We have Form Requests to extract our the validation & authorization logic and
our display logic is in our views, so why do we still insist on making our controllers handle fetching the data for those views?

This is especially evident in Inertia applications due to the introduction of concepts like lazy props.

Data providers extract the data composition for your Inertia views into their own classes. Inertia data providers may prove particularly 
useful if multiple routes or controllers within your application always needs a particular piece of data.

No more 40 line controller methods for fetching data!

## Usage

### Using a Data Provider
Data providers take advantage of the fact that `Inertia::render` can accept an `Arrayable`. 
They can also be used as discrete attributes in the data array.

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

A **kitchen sink** data provider might look like this:

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

If you need to return the entire dataset as an array, for instance for use in JSON responses, you can use `toNestedArray()

```php
use App\Models\Demo;
use Illuminate\Http\Request;
use App\DataProviders\TabDataProvider;
use App\DataProviders\DemoDataProvider;
use App\DataProviders\CreateVenueDataProvider;

class DemoController extends Controller
{
    public function show(Request $request, Demo $demo)
    {
        return (new DemoDataProvider($demo))->toNestedArray();
    }
}
```

## Attribute Name Formatting
The attribute name format can be configured in the configuration file by setting the `attribute_name_formatter`.  
The package ships with three formatters under the namespace `\Webfox\InertiaDataProviders\AttributeNameFormatters` but you are free to create your own.

### AsWritten
This is the default formatter. The output attribute name will be the same as the input name.
E.g. a property named `$someData` and a method named `more_data()` will be available in the page as `someData` and `more_data`. 

### SnakeCase
This formatter will convert the attribute name to snake_case.  
E.g. a property named `$someData` and a method named `more_data()` will be available in the page as `some_data` and `more_data`.

### CamelCase
This formatter will convert the attribute name to camelCase.  
E.g. a property named `$someData` and a method named `more_data()` will be available in the page as `someData` and `moreData`.

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

We welcome all contributors to the project.

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
