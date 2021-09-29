<?php

namespace TeraBlaze\Encryption;

use Exception;
use RuntimeException;
use TeraBlaze\Core\Parcel\Parcel;
use TeraBlaze\Encryption\Exception\DecryptException;
use TeraBlaze\Encryption\Exception\EncryptException;

use TeraBlaze\Encryption\Exception\MissingAppKeyException;
use TeraBlaze\Support\StringMethods;
use function openssl_decrypt;
use function openssl_encrypt;

class EncrypterParcel extends Parcel
{

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function boot(): void
    {
        $this->registerEncrypter();
    }

    /**
     * Register the encrypter.
     *
     * @return void
     */
    protected function registerEncrypter()
    {
        if (empty($key = getConfig('app.key'))) {
            throw new MissingAppKeyException();
        }
        $this->container->make('encrypter', [
            'class' => Encrypter::class,
            'arguments' => [$this->parseKey($key), getConfig('app.cipher')]
        ]);
    }

    /**
     * Parse the encryption key.
     *
     * @param  string  $key
     * @return string
     */
    protected function parseKey(string $key)
    {
        if (StringMethods::startsWith($key, $prefix = 'base64:')) {
            $key = base64_decode(StringMethods::after($key, $prefix));
        }

        return $key;
    }
}
