<?php

namespace Webfox\InertiaDataProviders\AttributeNameFormatters;

interface AttributeNameFormatter
{
    public function __invoke(string $name): string;
}