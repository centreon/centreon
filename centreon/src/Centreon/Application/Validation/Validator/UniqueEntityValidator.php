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
use LogicException;
use Symfony\Component\DependencyInjection\Exception\ServiceCircularReferenceException;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

use function is_array;
use function is_string;

class UniqueEntityValidator extends ConstraintValidator implements CentreonValidatorInterface
{
    /**
     * @param $entity
     * @param Constraint $constraint
     *
     * @throws ConstraintDefinitionException
     * @throws UnexpectedTypeException
     * @throws LogicException
     * @throws ServiceCircularReferenceException
     * @throws ServiceNotFoundException
     * @return void|null
     */
    public function validate($entity, Constraint $constraint)
    {
        if (! $constraint instanceof UniqueEntity) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\UniqueEntity');
        }

        if (! is_array($constraint->fields) && ! is_string($constraint->fields)) {
            throw new UnexpectedTypeException($constraint->fields, 'array');
        }

        if (null !== $constraint->errorPath && ! is_string($constraint->errorPath)) {
            throw new UnexpectedTypeException($constraint->errorPath, 'string or null');
        }

        // define fields to check
        $fields = (array) $constraint->fields;
        $methodRepository = $constraint->repositoryMethod;
        $methodIdGetter = $constraint->entityIdentificatorMethod;

        if ([] === $fields) {
            throw new ConstraintDefinitionException('At least one field has to be specified.');
        }
        if (null === $entity) {
            return null;
        }

        foreach ($fields as $field) {
            $methodValueGetter = 'get' . ucfirst($field);
            $value = $entity->{$methodValueGetter}();

            $repository = (Kernel::createForWeb())
                ->getContainer()
                ->get($constraint->repository);

            $result = $repository->{$methodRepository}([$field => $value]);

            if ($result && $result->{$methodIdGetter}() !== $entity->{$methodIdGetter}()) {
                $this->context->buildViolation($constraint->message)
                    ->atPath($field)
                    ->setInvalidValue($value)
                    ->setCode(UniqueEntity::NOT_UNIQUE_ERROR)
                    ->setCause($result)
                    ->addViolation();
            }
        }
    }

    /**
     * List of required services
     *
     * @return array
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::CENTREON_DB_MANAGER,
        ];
    }
}
