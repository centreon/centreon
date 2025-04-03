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

use Core\Resources\Application\UseCase\ExportResources\Enum\AllowedFormatEnum;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final readonly class ExportResourcesInput
{
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
        'checks',
    ];

    public function __construct(
        #[Assert\NotNull(
            message: 'format parameter is required'
        )]
        #[Assert\NotBlank(
            message: 'format parameter must not be empty'
        )]
        public mixed $format,
        #[Assert\NotNull(
            message: 'all_pages parameter is required'
        )]
        #[Assert\NotBlank(
            message: 'all_pages parameter must not be empty'
        )]
        public mixed $allPages,
        #[Assert\Type('array', message: 'columns must be an array')]
        #[Assert\All([
            new Assert\Type('string', message: 'columns must be an array of strings'),
            new Assert\NotBlank(message: 'columns value must not be empty'),
            new Assert\Choice(
                choices: self::EXPORT_ALLOWED_COLUMNS,
                message: 'columns must be one of the following: {{ choices }}'
            ),
        ])]
        public mixed $columns,
        public mixed $maxLines,
        public mixed $page,
        public mixed $limit,
        #[Assert\NotNull(
            message: 'sort_by parameter is required'
        )]
        #[Assert\NotBlank(
            message: 'sort_by parameter must not be empty'
        )]
        #[Assert\Json(
            message: 'sort_by parameter must be a valid JSON'
        )]
        public mixed $sort_by,
        #[Assert\NotNull(
            message: 'search parameter is required'
        )]
        #[Assert\NotBlank(
            message: 'search parameter must not be empty'
        )]
        #[Assert\Json(
            message: 'search parameter must be a valid JSON'
        )]
        public mixed $search
    ) {}

    #[Assert\Callback]
    public function validateFormat(ExecutionContextInterface $context): void
    {
        if (
            ! is_null($this->format)
            && (! is_string($this->format) || is_null(AllowedFormatEnum::tryFrom($this->format)))
        ) {
            $listAllowedFormat = AllowedFormatEnum::getAllowedFormatsAsString();
            $context->buildViolation("format parameter must be one of the following: {$listAllowedFormat}")
                ->atPath('format')
                ->addViolation();
        }
    }

    #[Assert\Callback]
    public function validateAllPages(ExecutionContextInterface $context): void
    {
        if (
            ! is_null($this->allPages)
            && (
                in_array($this->allPages, ['1', '0', 'true', 'false'], true) === false
            )
        ) {
            $context->buildViolation('all_pages parameter must be a boolean')
                ->atPath('all_pages')
                ->addViolation();
        } else {
            $allPages = filter_var($this->allPages, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            if (is_null($allPages)) {
                $context->buildViolation('all_pages parameter must be a boolean')
                    ->atPath('all_pages')
                    ->addViolation();
            } else {
                $this->validatePaginationByAllPages($context, $allPages);
                if ($allPages === true) {
                    $this->validateMaxLines($context);
                }
            }
        }
    }

    // ------------------------------------- PRIVATE METHODS -------------------------------------

    /**
     * @param ExecutionContextInterface $context
     * @param bool $allPages
     *
     * @return void
     */
    private function validatePaginationByAllPages(ExecutionContextInterface $context, bool $allPages): void
    {
        if (! is_null($this->page) && filter_var($this->page, FILTER_VALIDATE_INT) === false) {
            $context->buildViolation('page must be an integer')
                ->atPath('page')
                ->addViolation();
        }

        if (! is_null($this->limit) && filter_var($this->limit, FILTER_VALIDATE_INT) === false) {
            $context->buildViolation('limit must be an integer')
                ->atPath('limit')
                ->addViolation();
        }

        if (! $allPages) {
            if (is_null($this->page)) {
                $context->buildViolation('page is required when all_pages is false')
                    ->atPath('page')
                    ->addViolation();
            }

            if (is_null($this->limit)) {
                $context->buildViolation('limit is required when all_pages is false')
                    ->atPath('limit')
                    ->addViolation();
            }
        }
    }

    /**
     * @param ExecutionContextInterface $context
     *
     * @return void
     */
    private function validateMaxLines(ExecutionContextInterface $context): void
    {
        if (is_null($this->maxLines)) {
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
