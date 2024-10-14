<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

declare(strict_types=1);

namespace Core\Common\Infrastructure\Validator;

final class DateFormat
{
    /**
     * This is not the DateTime:ISO8601 PHP format. This represents the format sent by the frontend to Centreon APIs
     */
    public const ISO8601 = 'Y-m-d\TH:i:s.u\Z';
    public const INVALID_DATE_MESSAGE = 'this field does not match expected date format. Expected : 2024-09-10T12:45:00.000Z';
}
