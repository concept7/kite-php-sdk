<?php

namespace Concept7\Kite\Actions;

class GetKiteVersionAction extends GetComposerPackageVersionAction
{
    public function __construct()
    {
        parent::__construct('kite_version', ['concept7/kite-php-sdk']);
    }
}
