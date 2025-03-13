<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

declare(strict_types=1);

namespace Core\Resources\Infrastructure\API\ExportResources;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class ExportResourcesInput
{
    public const EXPORT_ALLOWED_FORMAT = ['csv'];
    public const EXPORT_MAX_LINES = 10000;
    public const EXPORT_ALLOWED_COLUMNS = [
        'resource',
        'status',
        'parent_resource',
        'duration',
        'last_check',
        'information',
        'tries',
        'severity',
        'notes_url',
        'action_url',
        'state',
        'alias',
        'parent_alias',
        'fqdn',
        'monitoring_server_name',
        'notification',
        'checks'
    ];

    /**
     * ExportResourcesInput constructor
     *
     * @param string|null $format
     * @param bool|null $allPages
     * @param array<string>|null $columns
     * @param int|null $maxLines
     */
    public function __construct(
        #[Assert\NotNull(
            message: 'format parameter is required'
        )]
        #[Assert\NotBlank(
            message: 'format parameter must not be empty'
        )]
        #[Assert\Choice(
            choices: self::EXPORT_ALLOWED_FORMAT,
            message: 'format must be one of the following: {{ choices }}'
        )]
        public mixed $format,
        #[Assert\NotNull(
            message: 'all_pages parameter is required'
        )]
        #[Assert\NotBlank(
            message: 'all_pages parameter must not be empty'
        )]
        public mixed $allPages,
        #[Assert\Type('array', message: 'Columns must be an array')]
        #[Assert\Sequentially([
            new Assert\All(
                [
                    new Assert\Type('string', message: 'columns must be an array of strings'),
                    new Assert\NotBlank(message: 'columns value must not be empty'),
                    new Assert\Choice(
                        choices: self::EXPORT_ALLOWED_COLUMNS,
                        message: 'columns must be one of the following: {{ choices }}'
                    )
                ]
            ),
        ])]
        public mixed $columns,

        public mixed $maxLines,
    ) {}

    #[Assert\Callback]
    public function validateAllPages(ExecutionContextInterface $context): void
    {
        if (
            ! is_null($this->allPages)
            &&
            (
                in_array($this->allPages, ['1', '0', 'true', 'false'], true) === false
            )
        ) {
            $context->buildViolation('all_pages parameter must be a boolean')
                ->atPath('all_pages')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateMaxLines(ExecutionContextInterface $context): void
    {
        if ($this->allPages === true && is_null($this->maxLines)) {
            $context->buildViolation('max_lines is required when all_pages is true')
                ->atPath('max_lines')
                ->addViolation();
        }

        if (! is_null($this->maxLines) && filter_var($this->maxLines, FILTER_VALIDATE_INT) === false) {
            $context->buildViolation('max_lines must be an integer')
                ->atPath('max_lines')
                ->addViolation();
        }

        if (! is_null($this->maxLines) && $this->maxLines > self::EXPORT_MAX_LINES) {
            $context->buildViolation('max_lines must be less than or equal to {{ limit }}')
                ->setParameter('{{ limit }}', (string) self::EXPORT_MAX_LINES)
                ->atPath('max_lines')
                ->addViolation();
        }
    }
}
