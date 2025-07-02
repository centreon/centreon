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
use Centreon\Application\Validation\Constraints\RepositoryCallback;
use Centreon\Application\Validation\Validator\Interfaces\CentreonValidatorInterface;
use Centreon\ServiceProvider;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints\CallbackValidator;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class RepositoryCallbackValidator extends CallbackValidator implements CentreonValidatorInterface
{
    /**
     * {@inheritDoc}
     * @return void
     */
    public function validate($object, Constraint $constraint): void
    {
        if (! $constraint instanceof RepositoryCallback) {
            throw new UnexpectedTypeException($constraint, __NAMESPACE__ . '\RepositoryCallback');
        }

        $method = $constraint->repoMethod;

        $repository = (Kernel::createForWeb())
            ->getContainer()
            ->get($constraint->repository);

        $fieldAccessor = $constraint->fieldAccessor;
        $value = $object->{$fieldAccessor}();
        $field = $constraint->fields;

        if (! method_exists($constraint->repository, $method)) {
            throw new ConstraintDefinitionException(sprintf(
                '%s targeted by Callback constraint is not a valid callable in the repository',
                json_encode($method)
            ));
        }
        if (null !== $object && ! $repository->{$method}($object)) {
            $this->context->buildViolation($constraint->message)
                ->atPath($field)
                ->setInvalidValue($value)
                ->setCode(RepositoryCallback::NOT_VALID_REPO_CALLBACK)
                ->setCause('Not Satisfying method:' . $method)
                ->addViolation();
        }
    }

    /**
     * List of required services
     *
     * @return string[]
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::CENTREON_DB_MANAGER,
        ];
    }
}
