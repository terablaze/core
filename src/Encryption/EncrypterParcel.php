<?php

namespace Terablaze\Encryption;

use Exception;
use RuntimeException;
use Terablaze\Core\Parcel\Parcel;
use Terablaze\Encryption\Exception\DecryptException;
use Terablaze\Encryption\Exception\EncryptException;
use Terablaze\Encryption\Exception\MissingAppKeyException;
use Terablaze\Support\StringMethods;

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
