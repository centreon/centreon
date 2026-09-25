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

namespace App\MonitoringConfiguration\Domain\Aggregate\Host;

use App\Shared\Domain\Logging\Attribute\Sensitive;
use Webmozart\Assert\Assert;

final readonly class HostMacro
{
    public const MAX_VALUE_LENGTH = 4096;
    public const MAX_DESCRIPTION_LENGTH = 65535;

    /**
     * @param bool $isPassword when true the value is a secret, eligible for the vault and never
     *                         echoed back in a response
     */
    public function __construct(
        public HostMacroName $name,
        // Masked in logs: a password macro's value is a secret (the flag is per-macro, but the
        // attribute is static, so every macro value is masked — safe, non-secret ones included).
        #[Sensitive] public string $value,
        public bool $isPassword,
        public ?string $description = null,
    ) {
        Assert::maxLength($value, self::MAX_VALUE_LENGTH);
        if ($description !== null) {
            Assert::maxLength($description, self::MAX_DESCRIPTION_LENGTH);
        }
    }
}
