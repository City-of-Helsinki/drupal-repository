<?php

declare(strict_types=1);

namespace App;

trait PackageTrait
{
    protected array $packages;

    protected function repositoryToPackageName(string $repository) :? string
    {
        foreach ($this->packages as $item) {
            if (!isset($item->url)) {
                continue;
            }

            if (str_contains(strtolower($item->url), strtolower($repository))) {
                return $item->name;
            }
        }
        return null;
    }

    protected function getPackageName(string $name) : string
    {
        if ($value = $this->repositoryToPackageName($name)) {
            return $value;
        }
        return $this->packages[$name]->name ?? '';
    }
}
