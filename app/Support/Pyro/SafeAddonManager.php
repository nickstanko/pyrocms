<?php

namespace App\Support\Pyro;

use Anomaly\Streams\Platform\Addon\AddonManager;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Schema;

class SafeAddonManager extends AddonManager
{
    protected function getEnabledAddonNamespaces()
    {
        if (!$this->addonTablesExist()) {
            return [];
        }

        return parent::getEnabledAddonNamespaces();
    }

    protected function getInstalledAddonNamespaces()
    {
        if (!$this->addonTablesExist()) {
            return [];
        }

        return parent::getInstalledAddonNamespaces();
    }

    protected function addonTablesExist(): bool
    {
        try {
            return Schema::hasTable($this->modules->getTable()) && Schema::hasTable($this->extensions->getTable());
        } catch (QueryException $exception) {
            return false;
        }
    }
}
