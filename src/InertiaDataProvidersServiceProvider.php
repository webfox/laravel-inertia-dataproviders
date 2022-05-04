<?php

namespace Webfox\InertiaDataProviders;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class InertiaDataProvidersServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-inertia-dataproviders')
            ->hasConfigFile();
    }
}
