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

namespace Core\Common\Domain\Exception;

/**
 * Class
 *
 * @class   RepositoryException
 * @package Core\Common\Domain\Exception
 */
class RepositoryException extends BusinessLogicException
{
    /**
     * RepositoryException constructor
     *
     * @param string $message
     * @param array<string,mixed> $context
     * @param \Throwable|null $previous
     */
    public function __construct(string $message, array $context = [], ?\Throwable $previous = null)
    {
        parent::__construct($message, self::ERROR_CODE_REPOSITORY, $context, $previous);
    }
}
