<?php

namespace Webfox\InertiaDataProviders\AttributeNameFormatters;

use Illuminate\Support\Str;

class SnakeCase implements AttributeNameFormatter
{
    public function __invoke(string $name): string
    {
        return Str::of($name)->snake()->toString();
    }
}