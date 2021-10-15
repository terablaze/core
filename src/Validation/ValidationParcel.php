<?php

namespace TeraBlaze\Validation;

use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Core\Parcel\ParcelInterface;

class ValidationParcel extends Parcel implements ParcelInterface
{
    public function boot(): void
    {
        $config = loadConfig('validation');

        Validator::$throwException = $config->get('validation.throw_exception', false);

        foreach ($config->get('validation.namespaces') as $namespace) {
            Validator::$rulesNamespaces[] = $namespace;
        }
        Validator::$rulesNamespaces[] = "TeraBlaze\Validation\Rule";

        $this->container->make('validator', [
            'class' => Validator::class,
            'alias' => ValidatorInterface::class,
        ]);
    }
}
