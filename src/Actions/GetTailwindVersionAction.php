<?php

namespace Concept7\Kite\Actions;

class GetTailwindVersionAction extends GetNodePackageVersionAction
{
    public function __construct(string $projectRoot)
    {
        parent::__construct($projectRoot, 'tailwind_version', 'tailwindcss');
    }
}
