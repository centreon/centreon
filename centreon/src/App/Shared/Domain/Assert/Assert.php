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

namespace App\Shared\Domain\Assert;

use Webmozart\Assert\Assert as WebmozartAssert;

final class Assert
{
    /**
     * RFC 1123 hostname + underscore (allowed for NetBIOS / Active Directory names).
     * Labels: 1-63 chars, may start/end with alphanumeric or '_', '-' allowed in the middle.
     * Whole hostname: max 253 chars.
     *
     * "D" is what makes both "$" the end of the subject rather than "before an optional trailing
     * newline": without it "host01\n" passes, and callers that interpolate the value they just
     * validated — a URL authority, a configuration file, a shell command — inherit the newline.
     */
    private const HOSTNAME_PATTERN
        = '/^(?=.{1,253}$)\w([\w-]{0,61}\w)?(\.\w([\w-]{0,61}\w)?)*$/D';

    public static function ipOrHostname(string $value, ?string $propertyPath = null): void
    {
        WebmozartAssert::true(
            filter_var($value, FILTER_VALIDATE_IP) !== false
            || preg_match(self::HOSTNAME_PATTERN, $value) === 1,
            sprintf(
                '[%s] The value "%s" was expected to be a valid IP address or hostname',
                $propertyPath ?? '',
                $value
            )
        );
    }
}
