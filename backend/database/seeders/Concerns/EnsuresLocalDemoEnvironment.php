<?php

namespace Database\Seeders\Concerns;

use RuntimeException;

trait EnsuresLocalDemoEnvironment
{
    protected function ensureLocalOrTestingEnvironment(string $message = 'Demo marketplace data may only be seeded in local or testing environments.'): void
    {
        if (! app()->environment(['local', 'testing'])) {
            throw new RuntimeException($message);
        }
    }
}
