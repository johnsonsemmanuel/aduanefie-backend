<?php

namespace App\Traits;

trait ActivationClass
{
    /**
     * Licensing removed — always active.
     */
    public function checkActivationCache(string|null $app = null): bool
    {
        return true;
    }
}
