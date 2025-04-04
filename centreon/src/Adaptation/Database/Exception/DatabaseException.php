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

namespace Adaptation\Database\Exception;

use Core\Common\Domain\Exception\BusinessLogicException;

/**
 * Class
 *
 * @class   DatabaseException
 * @package Adaptation\Database\Exception
 */
abstract class DatabaseException extends BusinessLogicException
{
    public const ERROR_CODE_DATABASE = 10;
    public const ERROR_CODE_DATABASE_TRANSACTION = 11;
    public const ERROR_CODE_UNBUFFERED_QUERY = 12;
    public const ERROR_CODE_QUERY_BUILDER = 20;
    public const ERROR_CODE_EXPRESSION_BUILDER = 30;
}
