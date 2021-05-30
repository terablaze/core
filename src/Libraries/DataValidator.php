<?php

namespace TeraBlaze\Libraries;

class DataValidator
{
    public function custom($match, $inputString)
    {
        return (bool)preg_match('/^' . $match . '$/i', $inputString);
    }

    public function required($inputString)
    {
        return !empty($inputString);
    }

    public function alpha($inputString)
    {
        return ctype_alpha($inputString);
    }

    public function alphaNumeric($inputString)
    {
        return ctype_alnum((string)$inputString);
    }

    public function alnumSpaces($inputString)
    {
        return (bool)preg_match('/^[a-zA-Z0-9 ]+$/i', $inputString);
    }

    public function alnumDash($inputString)
    {
        return (bool)preg_match('/^[a-zA-Z0-9_-]+$/i', $inputString);
    }

    public function string($inputString)
    {
        return (bool)preg_match('/^[a-zA-Z0-9 _-]+$/i', $inputString);
    }

    public function integer($inputString)
    {
        return (bool)preg_match('/^[\-+]?[0-9]+$/', $inputString);
    }

    public function decimal($inputString)
    {
        return (bool)preg_match('/^[\-+]?[0-9]+\.[0-9]+$/', $inputString);
    }

    public function minLength($inputString, $value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        return ($value <= mb_strlen($inputString));
    }


    public function maxLength($inputString, $value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        return ($value >= mb_strlen($inputString));
    }

    public function exactLength($inputString, $value)
    {
        if (!is_numeric($value)) {
            return false;
        }

        return (mb_strlen($inputString) === (int)$value);
    }

    public function validUrl($inputString)
    {
        if (
            (filter_var('http://' . $inputString, FILTER_VALIDATE_URL)) ||
            (filter_var('https://' . $inputString, FILTER_VALIDATE_URL)) ||
            (filter_var($inputString, FILTER_VALIDATE_URL))
        ) {
            return true;
        }

        return false;
    }

    public function email($email = "")
    {
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return true;
        }

        return false;
    }

    public function ip($ip)
    {
        if (filter_var($ip, FILTER_VALIDATE_IP)) {
            return true;
        }

        return false;
    }
}
