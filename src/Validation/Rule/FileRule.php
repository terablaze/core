<?php

namespace TeraBlaze\Validation\Rule;

use Psr\Http\Message\UploadedFileInterface;

class FileRule extends EqualsRule
{
    protected ?string $message = ":Field must be a file";

    public function validate(): bool
    {
        if ($this->value instanceof \SplFileInfo) {
            return $this->value->isFile();
        }

        if ($this->value instanceof UploadedFileInterface) {
            return $this->value->getError() == UPLOAD_ERR_OK;
        }

        return is_string($this->value) && is_file($this->value);
    }
}
