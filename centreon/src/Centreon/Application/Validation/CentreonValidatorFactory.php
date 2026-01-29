<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * https://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 *
 * For more information : contact@centreon.com
 *
 */

namespace Centreon\Application\Validation;

use Centreon\Application\Validation\Validator\Interfaces\CentreonValidatorInterface;
use Pimple\Container;
use Pimple\Psr11\ServiceLocator;
use ReflectionClass;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidatorFactoryInterface;
use Symfony\Component\Validator\ConstraintValidatorInterface;

class CentreonValidatorFactory implements ConstraintValidatorFactoryInterface
{
    /** @var Container */
    protected $container;

    /** @var array */
    protected $validators = [];

    /**
     * Construct
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * {@inheritDoc}
     */
    public function getInstance(Constraint $constraint): ConstraintValidatorInterface
    {
        $className = $constraint->validatedBy();

        if (! isset($this->validators[$className])) {
            if (class_exists($className)) {
                // validator as a class with dependencies from centreon
                $reflection = (new ReflectionClass($className));

                if ($reflection->implementsInterface(CentreonValidatorInterface::class)) {
                    $this->validators[$className] = new $className(new ServiceLocator(
                        $this->container,
                        $reflection->hasMethod('dependencies') ? $className::dependencies() : []
                    ));
                } else {
                    // validator as a class with empty property accessor
                    $this->validators[$className] = new $className();
                }
            } elseif (in_array($className, $this->container->keys())) {
                // validator as a service
                $this->validators[$className] = $this->container[$className];
            } else {
                throw new \RuntimeException(sprintf(_('The validator "%s" is not found'), $className));
            }
        }

        return $this->validators[$className];
    }
}
