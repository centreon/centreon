<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace Core\Gorgone\Application\Exception;

/**
 * An enrolment request the central refuses (bad input, feature off): told to the user as is.
 */
final class CollectorEnrolmentException extends \Exception
{
    /** Codes are the HTTP statuses the API answers with. */
    public const INVALID = 400;
    public const NOT_FOUND = 404;
    public const CONFLICT = 409;

    public static function featureDisabled(string $featureFlag): self
    {
        return new self(sprintf('Collector enrolment needs the feature flag "%s"', $featureFlag), self::INVALID);
    }

    public static function invalid(string $message): self
    {
        return new self($message, self::INVALID);
    }

    public static function notFound(string $message): self
    {
        return new self($message, self::NOT_FOUND);
    }

    /**
     * @param string $message what gorgone-server refused, e.g. "enrolment is completed"
     */
    public static function conflict(string $message): self
    {
        return new self($message, self::CONFLICT);
    }
}
