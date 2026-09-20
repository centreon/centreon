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
 * A URL path made of safe segments, in its canonical leading-slash form: "", "/centreon",
 * "/base/path".
 *
 * The alphabet is deliberately narrower than RFC 3986. Such a path is interpolated unquoted
 * into the `curl … | bash` one-liner an admin runs as root, so sub-delims a URL accepts
 * ("$", "(", ")", backtick) are rejected here rather than escaped later.
 *
 * The type exists so that alphabet lives in one place: CentralAddress, CentralUrl and
 * CentralUrlFactory each carried their own copy, and the copies had already drifted apart
 * once.
 */
final readonly class UrlPath
{
    /** The single definition of a safe segment. Public so composed patterns can interpolate it. */
    public const SEGMENT = '[A-Za-z0-9._\-]+';

    /**
     * The slash belongs to each segment, so an empty segment ("//") cannot pass.
     *
     * "D" is what makes "$" the end of the subject rather than "before an optional trailing
     * newline": without it "/centreon\n" passes. CentralUrlFactory::baseUri() rtrims "/" alone,
     * which leaves such a newline in place, so the guarantee above has to hold on its own.
     */
    public const PATTERN = '~^(?:/' . self::SEGMENT . ')*$~D';

    /** "." and ".." match PATTERN but resolve outside the path, so they need their own check. */
    public const DOT_SEGMENT_PATTERN = '~(?:^|/)\.{1,2}(?:/|$)~';

    /**
     * The value as given, once validated: a leading slash on every segment, no trailing slash,
     * "" for a root-mounted path. Nothing is normalized here on purpose, so a caller cannot have
     * a malformed path quietly repaired into a valid one.
     */
    public string $value;

    public function __construct(string $value, ?string $propertyPath = null)
    {
        $property = $propertyPath ?? 'UrlPath::value';

        Assert::regex(
            $value,
            self::PATTERN,
            sprintf('[%s] The path "%s" contains invalid characters', $property, $value)
        );
        Assert::false(
            (bool) preg_match(self::DOT_SEGMENT_PATTERN, $value),
            sprintf('[%s] The path "%s" must not contain dot segments', $property, $value)
        );

        $this->value = $value;
    }

    /**
     * For callers that degrade instead of failing, such as a base URI dropped from a URL and
     * logged rather than turned into an error response.
     */
    public static function tryFrom(string $value): ?self
    {
        try {
            return new self($value);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }

    /**
     * The segments without their leading slash ("base/path"), null when the path is empty.
     */
    public function segments(): ?string
    {
        return $this->value === '' ? null : mb_substr($this->value, 1);
    }
}
