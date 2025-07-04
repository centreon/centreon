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

use Centreon\Application\Validation\Validator\UniqueEntityValidator;
use Symfony\Component\Validator\Constraint;

class UniqueEntity extends Constraint
{
    public const NOT_UNIQUE_ERROR = '23bd9dbf-6b9b-41cd-a99e-4844bcf3077c';

    /** @var string */
    public string $validatorClass = UniqueEntityValidator::class;

    /** @var string */
    public $message = 'This value is already used.';

    /** @var string */
    public $entityIdentificatorMethod = 'getId';

    /** @var string */
    public $entityIdentificatorColumn = 'id';

    /** @var mixed */
    public $repository = null;

    /** @var string */
    public $repositoryMethod = 'findOneBy';

    /** @var array<mixed> */
    public $fields = [];

    /** @var string|null */
    public $errorPath = null;

    /** @var bool */
    public $ignoreNull = true;

    /**
     * @var array<string, string>
     */
    protected const ERROR_NAMES = [
        self::NOT_UNIQUE_ERROR => 'NOT_UNIQUE_ERROR',
    ];

    /**
     * {@inheritDoc}
     */
    public function getTargets(): string|array
    {
        return self::CLASS_CONSTRAINT;
    }

    /**
     * @return string
     */
    public function getDefaultOption(): string
    {
        return 'fields';
    }

    /**
     * The validator class name.
     *
     * @return string
     */
    public function validatedBy(): string
    {
        return $this->validatorClass;
    }
}
