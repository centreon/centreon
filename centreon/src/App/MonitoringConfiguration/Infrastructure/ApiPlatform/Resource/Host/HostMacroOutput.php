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

namespace App\MonitoringConfiguration\Infrastructure\ApiPlatform\Resource\Host;

/**
 * The macro wire object, shared by every endpoint returning host macros: `{ id, name, value,
 * is_password, parent }`.
 */
final readonly class HostMacroOutput
{
    /**
     * @param ?int $id the macro's id in the table its parent points to; null only for a command macro
     *                 the command never recorded (see Command::macros())
     * @param string $name the short macro name (upper-cased), without the $_HOST…$ wrapper
     * @param ?string $value always null for a password macro, whose secret is never echoed back;
     *                       the null is then dropped from the payload by skip_null_values
     * @param ?string $parent "template" or "command" for an inherited macro, null for a direct one
     */
    public function __construct(
        public ?int $id,
        public string $name,
        public ?string $value,
        public bool $isPassword,
        public ?string $parent,
    ) {
    }
}
