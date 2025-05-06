<?php

namespace Webfox\InertiaDataProviders\WrappingAttributes;

interface WrappingAttribute
{
    /**
     * Wrap the data with the attribute.
     *
     * @param mixed $data
     * @return array
     */
    public function __invoke($data);
}
