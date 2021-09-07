<?php

namespace TeraBlaze\Encryption;

use TeraBlaze\Encryption\Exception\DecryptException;
use TeraBlaze\Encryption\Exception\EncryptException;

interface EncrypterInterface
{
    /**
     * Encrypt the given value.
     *
     * @param  mixed  $value
     * @param  bool  $serialize
     * @return string
     *
     * @throws EncryptException
     */
    public function encrypt($value, bool $serialize = true): string;

    /**
     * Decrypt the given value.
     *
     * @param string $payload
     * @param  bool  $unserialize
     * @return mixed
     *
     * @throws DecryptException
     */
    public function decrypt(string $payload, bool $unserialize = true);
}
