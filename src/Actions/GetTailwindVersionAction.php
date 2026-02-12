<?php

namespace Concept7\Kite\Actions;

class GetTailwindVersionAction extends GetNodePackageVersionAction
{
    public function __construct()
    {
        parent::__construct('tailwind_version', 'tailwindcss');
    }
}
