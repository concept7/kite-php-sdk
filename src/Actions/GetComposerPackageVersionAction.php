<?php

namespace Concept7\Kite\Actions;

use Closure;
use Composer\InstalledVersions;
use Concept7\Kite\Contracts\ActionInterface;
use Concept7\Kite\Support\Collection;
use OutOfBoundsException;

class GetComposerPackageVersionAction implements ActionInterface
{
    public function __construct(
        protected string $metaKey,
        protected array $packages,
    ) {}

    public function handle(Collection $data, Closure $next): Collection
    {
        foreach ($this->packages as $package) {
            try {
                $version = InstalledVersions::getVersion($package);

                if ($version !== null && $version !== '') {
                    $data->push([
                        'key' => $this->metaKey,
                        'value' => $version,
                    ]);

                    return $next($data);
                }
            } catch (OutOfBoundsException) {
            }
        }

        return $next($data);
    }
}
