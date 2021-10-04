<?php

namespace TeraBlaze\ErrorHandler\ErrorEnhancer;

use TeraBlaze\ErrorHandler\Error\FatalError;
use TeraBlaze\ErrorHandler\Error\UndefinedMethodError;

/**
 * @author Grégoire Pineau <lyrixx@lyrixx.info>
 */
class UndefinedMethodErrorEnhancer implements ErrorEnhancerInterface
{
    /**
     * {@inheritdoc}
     */
    public function enhance(\Throwable $error): ?\Throwable
    {
        if ($error instanceof FatalError) {
            return null;
        }

        $message = $error->getMessage();
        preg_match('/^Call to undefined method (.*)::(.*)\(\)$/', $message, $matches);
        if (!$matches) {
            return null;
        }

        $className = $matches[1];
        $methodName = $matches[2];

        $message = sprintf('Attempted to call an undefined method named "%s" of class "%s".', $methodName, $className);

        if (!class_exists($className) || null === $methods = get_class_methods($className)) {
            // failed to get the class or its methods on which an unknown method was called (for example on an anonymous class)
            return new UndefinedMethodError($message, $error);
        }

        $candidates = [];
        foreach ($methods as $definedMethodName) {
            $lev = levenshtein($methodName, $definedMethodName);
            if ($lev <= \strlen($methodName) / 3 || false !== strpos($definedMethodName, $methodName)) {
                $candidates[] = $definedMethodName;
            }
        }

        if ($candidates) {
            sort($candidates);
            $last = array_pop($candidates).'"?';
            if ($candidates) {
                $candidates = 'e.g. "'.implode('", "', $candidates).'" or "'.$last;
            } else {
                $candidates = '"'.$last;
            }

            $message .= "\nDid you mean to call ".$candidates;
        }

        return new UndefinedMethodError($message, $error);
    }
}
