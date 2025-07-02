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
use Symfony\Component\Validator\ConstraintViolationList;

class ValidatorErrorList implements JsonSerializable
{
    /** @var ConstraintViolationList */
    private $errors;

    /**
     * Construct
     *
     * @param ConstraintViolationList $errors
     */
    public function __construct(ConstraintViolationList $errors)
    {
        $this->errors = $errors;
    }

    /**
     * @OA\Schema(
     *   schema="ValidatorErrorList",
     *       @OA\Property(property="field", type="string"),
     *       @OA\Property(property="messages", type="array", items={"string"})
     * )
     *
     * JSON serialization of errors
     *
     * @return array<array{field: string, messages: string}>
     */
    public function jsonSerialize(): mixed
    {
        $result = [];

        foreach ($this->errors as $error) {
            $result[] = [
                'field' => $error->getPropertyPath(),
                'messages' => $error->getMessage(),
            ];
        }

        return $result;
    }
}
