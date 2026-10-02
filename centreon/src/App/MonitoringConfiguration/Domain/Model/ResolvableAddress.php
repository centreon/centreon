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

namespace App\MonitoringConfiguration\Domain\Model;

use Webmozart\Assert\Assert;

/**
 * An address the host form can resolve: a valid IPv4, or a hostname valid by RFC 1123 §2.1
 * (letters, digits and inner hyphens per label), RFC 1035 §2.3.4 (63 per label, 253 overall)
 * and RFC 3696 §2 (the top-level label is never all-numeric, so "127.0.1" is no hostname).
 */
final readonly class ResolvableAddress
{
    public const MAX_LENGTH = 253;
    private const HOSTNAME_PATTERN
        = '/^(?=.{1,253}$)(?:[a-z\d](?:[a-z\d-]{0,61}[a-z\d])?\.)*(?=[a-z\d-]*[a-z])[a-z\d](?:[a-z\d-]{0,61}[a-z\d])?$/iD';

    public string $value;

    public function __construct(string $value)
    {
        $value = trim($value);
        Assert::true(
            filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false
                || preg_match(self::HOSTNAME_PATTERN, $value) === 1,
            sprintf('The value "%s" was expected to be an IPv4 address or a hostname', $value),
        );
        $this->value = $value;
    }
}
