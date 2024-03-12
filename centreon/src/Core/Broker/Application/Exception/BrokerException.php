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

namespace Core\Broker\Application\Exception;

class BrokerException extends \Exception
{
    public const CODE_CONFLICT = 1;
    public const CODE_INVALID = 2;

    /**
     * @return self
     */
    public static function addBrokerOutput(): self
    {
        return new self(_('Error while adding a broker output'));
    }

    /**
     * @return self
     */
    public static function editNotAllowed(): self
    {
        return new self(_('You are not allowed to edit a broker configuration'));
    }

    /**
     * @param int $brokerId
     *
     * @return self
     */
    public static function notFound(int $brokerId): self
    {
        return new self(sprintf(_('Broker configuration #%d not found'), $brokerId));
    }

    /**
     * @param string $propertyName
     * @param int $propertyValue
     *
     * @return self
     */
    public static function idDoesNotExist(string $propertyName, int $propertyValue): self
    {
        return new self(
            sprintf(_("The %s with value '%d' does not exist"), $propertyName, $propertyValue),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param string $propertyName
     *
     * @return self
     */
    public static function missingParameter(string $propertyName): self
    {
        return new self(
            sprintf(_('Missing output parameter: %s'), $propertyName),
            self::CODE_INVALID
        );
    }

    /**
     * @param string $propertyName
     * @param mixed $propertyValue
     *
     * @return self
     */
    public static function invalidParameter(string $propertyName, mixed $propertyValue): self
    {
        return new self(
            sprintf(
                _("Parameter '%s' (%s) is invalid"),
                $propertyName,
                is_scalar($propertyValue) ? $propertyValue : gettype($propertyValue)
            ),
            self::CODE_INVALID
        );
    }

    /**
     * @param string $propertyName
     * @param mixed $propertyValue
     *
     * @return self
     */
    public static function invalidParameterType(string $propertyName, mixed $propertyValue): self
    {
        return new self(
            sprintf(_("Parameter '%s' of type %s is invalid"), $propertyName, gettype($propertyValue)),
            self::CODE_INVALID
        );
    }

    /**
     * @param int $brokerId
     * @param int $outputId
     *
     * @return self
     */
    public static function outputNotFound(int $brokerId, int $outputId): self
    {
        return new self(sprintf(_('Output #%d not found for broker configuration #%d'), $outputId, $brokerId));
    }
}
