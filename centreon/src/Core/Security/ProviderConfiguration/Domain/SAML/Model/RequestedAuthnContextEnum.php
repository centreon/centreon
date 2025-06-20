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

namespace Core\Security\ProviderConfiguration\Domain\SAML\Model;

enum RequestedAuthnContextEnum 
{
    case MINIMUM;
    case EXACT;
    case BETTER;
    case MAXIMUM;

    /**
     * @return string
     */
    public function toString(): string
    {
        return match ($this) {
            self::MINIMUM => 'minimum',
            self::EXACT => 'exact',
            self::BETTER => 'better',
            self::MAXIMUM => 'maximum',
        };
    }

    /**
     * @param string $value
     *
     * @return self
     */
    public static function fromString(string $value): self
    {
        return match ($value) {
            'minimum' => self::MINIMUM,
            'exact' => self::EXACT,
            'better' => self::BETTER,
            'maximum' => self::MAXIMUM,
            default => self::MINIMUM,
        };
    }
}
