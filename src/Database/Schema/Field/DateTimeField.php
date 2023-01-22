<?php

namespace Terablaze\Database\Schema\Field;

class DateTimeField extends Field
{
    public bool $useCurrent = false;
    public bool $useCurrentOnUpdate = false;

    public function useCurrent()
    {
        $this->useCurrent = true;
        return $this;
    }

    public function useCurrentOnUpdate()
    {
        $this->useCurrentOnUpdate = true;
        return $this;
    }
}
