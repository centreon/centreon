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

namespace Centreon\Application\Validation\Validator;

use App\Kernel;
use Centreon\Application\Validation\Constraints\UniqueEntity;
use Centreon\Application\Validation\Validator\Interfaces\CentreonValidatorInterface;
use Centreon\ServiceProvider;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueEntityValidator extends ConstraintValidator implements CentreonValidatorInterface
{
    private ?ContainerInterface $container = null;

    /**
     * {@inheritDoc}
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof UniqueEntity) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\UniqueEntity');
        }

        $field = $constraint->field;
        $methodValueGetter = 'get' . ucfirst($field);
        if (
            $value === null
            || ! (
                is_object($value)
                && method_exists($value, $methodValueGetter)
            )
        ) {
            return;
        }
        $repository = $this->getContainer()->get($constraint->repository);

        $methodRepository = 'findOneBy';
        if (! method_exists($repository, $methodRepository)) {
            throw new ConstraintDefinitionException(sprintf(
                'The repository "%s" must expose "%s()" for UniqueEntity validation.',
                $constraint->repository,
                $methodRepository
            ));
        }

        $retrieveValue = $value->{$methodValueGetter}();
        $result = $repository->{$methodRepository}([$field => $retrieveValue]);
        if (! $result) {
            return;
        }

        $methodIdGetter = 'getId';
        if (! method_exists($result, $methodIdGetter) || ! method_exists($value, $methodIdGetter)) {
            throw new ConstraintDefinitionException(sprintf(
                'The entity must expose a "%s()" method for UniqueEntity validation.',
                $methodIdGetter
            ));
        }

        $isSameEntity = $result->{$methodIdGetter}() === $value->{$methodIdGetter}();

        if (! $isSameEntity) {
            $this->context->buildViolation('Name already in use')
                ->atPath($field)
                ->setInvalidValue($retrieveValue)
                ->setCode(UniqueEntity::NOT_UNIQUE_ERROR)
                ->setCause($result)
                ->addViolation();
        }
    }

    /**
     * List of required services
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::CENTREON_DB_MANAGER,
        ];
    }

    private function getContainer(): ContainerInterface
    {
        if (! $this->container instanceof ContainerInterface) {
            $this->container = (Kernel::createForWeb())->getContainer();
        }

        return $this->container;
    }
}
