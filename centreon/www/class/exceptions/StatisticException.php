<?php

/*
 * Copyright 2005-2021 CENTREON
 * Centreon is developed by : Julien Mathis and Romain Le Merlus under
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

use Core\Common\Domain\Exception\BusinessLogicException;

/**
 * Class
 *
 * @class StatisticException
 */
class StatisticException extends BusinessLogicException
{
    /**
     * StatisticException constructor
     *
     * @param string $message
     * @param array $context
     * @param Throwable|null $previous
     */
    public function __construct(
        string $message,
        array $context = [],
        ?Throwable $previous = null
    ) {
        parent::__construct($message, self::ERROR_CODE_INTERNAL, $context, $previous);
    }

}
