<?php

namespace TeraBlaze\Validation\Rule;

use Psr\Http\Message\UploadedFileInterface;
use Symfony\Component\Mime\MimeTypes;
use TeraBlaze\Filesystem\Files;

class ExtRule extends Rule
{
    protected Files $filesystem;
    protected MimeTypes $mimeType;

    /**
     * @param string $field
     * @param array<string, mixed> $data
     * @param mixed|array<string, mixed> $params
     */
    public function __construct(string $field, array $data = [], $params = [])
    {
        parent::__construct($field, $data, $params);
        $this->filesystem = new Files();
        $this->mimeType = new MimeTypes();
    }

    public function validate(): bool
    {
        if (!$this->value instanceof UploadedFileInterface) {
            return false;
        }

        if (in_array('jpg', $this->params) || in_array('jpeg', $this->params)) {
            $this->params = array_unique(array_merge($this->params, ['jpg', 'jpeg']));
        }

        return in_array($this->filesystem->extension($this->value->getClientFilename()), $this->params);
    }

    public function getMessage(): string
    {
        $paramsCount = count($this->params);
        return $this->message ??
            sprintf(
                "Accepted file %s for :field %s: %s",
                $paramsCount > 1 ? "extensions" : "extension",
                $paramsCount > 1 ? "are" : "is",
                implode(", ", $this->params),
            );
    }
}
