<?php

/*
 * Copyright 2005 - 2023 Centreon (https://www.centreon.com/)
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

namespace CloudMigration;

class PlatformMigrationException extends \Exception
{
    /**
     * @param string $elementName
     * @param \Exception $previousException
     *
     * @return self
     */
    public static function failToCreateElement(
        string $elementName,
        \Exception $previousException
    ): self {
        return new self(
            sprintf('Error while creating %s on target platform', $elementName),
            0,
            $previousException
        );
    }

    /**
     * @param string $elementName
     * @param string $platform
     * @param \Exception $previousException
     *
     * @return self
     */
    public static function failToRetrieveElements(
        string $elementName,
        string $platform,
        \Exception $previousException
    ): self {
        return new self(
            sprintf('Error while retrieving %s on %s platform', $elementName, $platform),
            0,
            $previousException
        );
    }

    /**
     * @param string $message
     *
     * @return self
     */
    public static function requestFailed(string $message): self
    {
        return new self($message);
    }
}