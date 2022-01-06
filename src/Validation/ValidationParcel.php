<?php

namespace TeraBlaze\Validation;

use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class ValidationParcel extends Parcel implements ParcelInterface
{
    public function boot(): void
    {
        $config = loadConfig('validation');

        Validation::$throwException = $config->get('validation.throw_exception', true);

        foreach ($config->get('validation.namespaces') as $namespace) {
            Validation::$rulesNamespaces[] = $namespace;
        }

        $this->container->make('validator', [
            'class' => Validator::class,
        ]);
    }
}
