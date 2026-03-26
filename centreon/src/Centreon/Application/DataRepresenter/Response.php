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

namespace Centreon\Application\DataRepresenter;

use JsonSerializable;

/**
 * Unification of API response
 */
class Response implements JsonSerializable
{
    /** @var bool */
    private $status;

    /** @var mixed */
    private $result;

    /**
     * Construct
     *
     * @param mixed $result
     * @param bool $status
     */
    public function __construct($result, bool $status = true)
    {
        $this->status = $status;
        $this->result = $result;
    }

    /**
     * JSON serialization of response
     *
     * @return array{status: bool, result: mixed}
     */
    public function jsonSerialize(): mixed
    {
        return [
            'status' => $this->status,
            'result' => $this->result,
        ];
    }
}
