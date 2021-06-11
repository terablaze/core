<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace TeraBlaze\Routing\Controller\ArgumentResolver;

use TeraBlaze\HttpBase\Request;
use TeraBlaze\Routing\Controller\ArgumentValueResolverInterface;
use TeraBlaze\Routing\ControllerMetadata\ArgumentMetadata;

/**
 * Yields the default value defined in the action signature when no value has been given.
 *
 * @author Iltar van der Berg <kjarli@gmail.com>
 */
final class DefaultValueResolver implements ArgumentValueResolverInterface
{
    /**
     * {@inheritdoc}
     */
    public function supports(Request $request, ArgumentMetadata $argument): bool
    {
        return $argument->hasDefaultValue()
            || (null !== $argument->getType() && $argument->isNullable() && !$argument->isVariadic());
    }

    /**
     * {@inheritdoc}
     */
    public function resolve(Request $request, ArgumentMetadata $argument): iterable
    {
        yield $argument->hasDefaultValue() ? $argument->getDefaultValue() : null;
    }
}