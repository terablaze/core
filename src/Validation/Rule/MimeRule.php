<?php

namespace TeraBlaze\Validation\Rule;

use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Mime\MimeTypes;
use TeraBlaze\Filesystem\Files;
use TeraBlaze\Support\StringMethods;
use TeraBlaze\Validation\Validation;

class MimeRule extends Rule
{
    protected Files $filesystem;
    protected MimeTypes $mimeType;

    /**
     * @param string $field
     * @param array<string, mixed> $data
     * @param mixed|array<string, mixed> $params
     */
    public function __construct(Validation $validation, string $field, array $data = [], $params = [])
    {
        parent::__construct($validation, $field, $data, $params);
        $this->filesystem = new Files();
        $this->mimeType = new MimeTypes();
    }

    public function validate(): bool
    {
        if (!$this->value instanceof UploadedFileInterface) {
            return false;
        }

        $valueMimes = $this->mimeType->getMimeTypes($this->filesystem->extension($this->value->getClientFilename()));

        foreach ($valueMimes as $valueMime) {
            if (
                in_array($valueMime, $this->params) ||
                in_array(StringMethods::before($valueMime, "/") . '/*', $this->params)
            ) {
                return true;
            }
        }
        return false;
    }

    public function getMessage(): string
    {
        $paramsCount = count($this->params);
        return $this->message ??
            sprintf(
                "Accepted mime %s for :field %s: %s",
                $paramsCount > 1 ? "types" : "type",
                $paramsCount > 1 ? "are" : "is",
                implode(", ", $this->params),
            );
    }
}
