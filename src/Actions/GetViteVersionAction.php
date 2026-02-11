<?php

namespace Concept7\Kite\Actions;

class GetViteVersionAction extends GetNodePackageVersionAction
{
    public function __construct(string $projectRoot)
    {
        parent::__construct($projectRoot, 'vite_version', 'vite');
    }
}
