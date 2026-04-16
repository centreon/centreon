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

namespace Centreon\Application\Validation\Constraints;

use Centreon\Application\Validation\Validator\RepositoryCallbackValidator;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Exception\ConstraintDefinitionException;

class RepositoryCallback extends Constraint
{
    public const NOT_VALID_REPO_CALLBACK = '13bd9dbf-6b9b-41cd-a99e-4844bcf3077z';

    /**
     * @var array<string,string>
     */
    protected const ERROR_NAMES = [
        self::NOT_VALID_REPO_CALLBACK => 'NOT_VALID_REPO_CALLBACK',
    ];

    /**
     * @param string $errorMessage Validation error message
     * @param mixed|null $payload
     */
    public function __construct(
        public string $field,
        public string $fieldAccessor,
        public string $repository,
        public string $repositoryMethod,
        public string $errorMessage,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        $this->field = trim($this->field);
        if ($this->field === '') {
            throw new ConstraintDefinitionException('The field name cannot be empty.');
        }
        $this->fieldAccessor = trim($this->fieldAccessor);
        if ($this->fieldAccessor === '') {
            throw new ConstraintDefinitionException('The field accessor cannot be empty.');
        }
        $this->repository = trim($this->repository);
        if ($this->repository === '') {
            throw new ConstraintDefinitionException('The repository cannot be empty.');
        }
        $this->repositoryMethod = trim($this->repositoryMethod);
        if ($this->repositoryMethod === '') {
            throw new ConstraintDefinitionException('The repository method cannot be empty.');
        }
        $this->errorMessage = trim($this->errorMessage);
        if ($this->errorMessage === '') {
            throw new ConstraintDefinitionException('The validation error message cannot be empty.');
        }

        parent::__construct(groups: $groups, payload: $payload);
    }

    /**
     * {@inheritDoc}
     */
    public function validatedBy(): string
    {
        return RepositoryCallbackValidator::class;
    }

    /**
     * {@inheritDoc}
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }
}
