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

declare(strict_types=1);

namespace Core\Broker\Infrastructure\Repository;

/**
 * A broker input/output as stored in cfg_centreonbroker_input_output.
 *
 * Parameters use the shape of the broker input/output API:
 * - simple parameters: "key" => "value"
 * - multiselect filters: "filters_<key>" => list of values
 * - grouped fields: "<group>" => list of ["<field>" => "value"]
 *
 * @phpstan-type _StoredParameters array<string, string|list<string>|list<array<string, string>>>
 */
final readonly class StoredBrokerInputOutput
{
    /**
     * @param 'input'|'output' $tag
     * @param string|null $type the type short name, null when unknown
     * @param int|null $typeId the cb_type id, null when unknown
     * @param _StoredParameters $parameters
     */
    public function __construct(
        public string $tag,
        public int $id,
        public string $name,
        public ?string $type,
        public ?int $typeId,
        public array $parameters,
    ) {
    }

    /**
     * @throws \JsonException
     */
    public function encodeParameters(): string
    {
        return json_encode(
            $this->parameters === [] ? new \stdClass() : $this->parameters,
            JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
        );
    }
}
