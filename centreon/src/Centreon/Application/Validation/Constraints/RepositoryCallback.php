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

class RepositoryCallback extends Constraint
{
    /** @var string|null */
    public $fieldAccessor = null;

    /** @var string|null */
    public $repoMethod = null;

    /** @var string|null */
    public $repository = null;

    /** @var string */
    public $fields = '';
    public const NOT_VALID_REPO_CALLBACK = '13bd9dbf-6b9b-41cd-a99e-4844bcf3077z';

    /** @var string */
    public $message = 'Does not satisfy validation callback. Check Repository.';

    /**
     * @var array<string,string>
     */
    protected const ERROR_NAMES = [
        self::NOT_VALID_REPO_CALLBACK => 'NOT_VALID_REPO_CALLBACK',
    ];

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
