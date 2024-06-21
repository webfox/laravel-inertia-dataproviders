<?php

namespace Webfox\InertiaDataProviders\AttributeNameFormatters;

use Illuminate\Support\Str;

class CamelCase implements AttributeNameFormatter
{
    public function __invoke(string $name): string
    {
        return Str::of($name)->camel()->toString();
    }
}