<?php

namespace Webfox\InertiaDataProviders\AttributeNameFormatters;

use Illuminate\Support\Str;

class AsWritten implements AttributeNameFormatter
{
    public function __invoke(string $name): string
    {
        return $name;
    }
}