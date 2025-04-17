<?php

namespace Webfox\InertiaDataProviders\WrappingAttributes;

use Attribute;
use Inertia\Inertia;

#[Attribute(Attribute::TARGET_METHOD)]
class InertiaMerge implements WrappingAttribute
{
    public function __invoke($data)
    {
        return Inertia::Merge($data);
    }
}
