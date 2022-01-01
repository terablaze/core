<?php

namespace TeraBlaze\Validation;

use TeraBlaze\Container\Container;
use TeraBlaze\Translation\Translator;
use TeraBlaze\Translation\TranslatorInterface;

class Validator
{
    private Container $container;
    private ?Translator $translator = null;

    public function __construct(Container $container, ?Translator $translator = null)
    {
        $this->container = $container;
        $this->translator = $translator;
    }

    public function make(
        array $data,
        array $rules,
        array $customMessages = [],
        array $customAttributes = []
    ): Validation {
        $validation = new Validation($data, $rules, $customMessages, $customAttributes);
        $validation->setContainer($this->container);
        if ($this->translator instanceof TranslatorInterface) {
            $validation->setTranslator($this->translator);
        }
        return $validation;
    }

    public function validate(
        array $data,
        array $rules,
        array $customMessages = [],
        array $customAttributes = []
    ): array {
        $validation = $this->make($data, $rules, $customMessages, $customAttributes);
        return $validation->validate();
    }
}