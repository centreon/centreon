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

namespace Adaptation\Database\Connection\Enum;

/**
 * Enum
 *
 * @class   QueryParameterTypeEnum
 * @package Adaptation\Database\Connection\Enum
 */
enum QueryParameterTypeEnum
{
    /**
     * Represents the SQL NULL data type.
     */
    case NULL;

    /**
     * Represents the SQL INTEGER data type.
     */
    case INTEGER;

    /**
     * Represents the SQL CHAR, VARCHAR, or other string data type.
     */
    case STRING;

    /**
     * Represents the SQL large object data type.
     */
    case LARGE_OBJECT;

    /**
     * Represents a boolean data type.
     */
    case BOOLEAN;
}
