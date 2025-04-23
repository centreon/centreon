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

namespace Core\Host\Application\Exception;

class HostException extends \Exception
{
    public const CODE_CONFLICT = 1;

    /**
     * @return self
     */
    public static function accessNotAllowedForRealTime(): self
    {
        return new self(_('You are not allowed to access hosts in the real time context'));
    }

    /**
     * @return self
     */
    public static function addHost(): self
    {
        return new self(_('Error while adding a host'));
    }

    /**
     * @return self
     */
    public static function editHost(): self
    {
        return new self(_('Error while updating a host'));
    }

    /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete a host'));
    }

    /**
     * @return self
     */
    public static function editNotAllowed(): self
    {
        return new self(_('You are not allowed to edit a host'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function linkHostCategories(\Throwable $ex): self
    {
        return new self(_('Error while linking host categories to a host'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to add hosts'));
    }

    /**
     * @param int $hostId
     *
     * @return self
     */
    public static function notFound(int $hostId): self
    {
        return new self(sprintf(_('Host #%d not found'), $hostId));
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
     * @param int[] $propertyValues
     *
     * @return self
     */
    public static function idsDoNotExist(string $propertyName, array $propertyValues): self
    {
        return new self(
            sprintf(
                _("The %s does not exist with ID(s) '%s'"),
                $propertyName,
                implode(',', $propertyValues)
            ),
            self::CODE_CONFLICT
        );
    }

    /**
     * @return self
     */
    public static function listingNotAllowed(): self
    {
        return new self(_('You are not allowed to list hosts'));
    }

    /**
     * @param string $formattedName
     * @param string $originalName
     *
     * @return self
     */
    public static function nameAlreadyExists(string $formattedName, string $originalName = 'undefined'): self
    {
        return new self(
            sprintf(_('The name %s (original name: %s) already exists'), $formattedName, $originalName),
            self::CODE_CONFLICT
        );
    }

    /**
     * @return self
     */
    public static function nameIsInvalid(): self
    {
        return new self(
            _("Host name should not start with '_Module_'"),
            self::CODE_CONFLICT
        );
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingObject(): self
    {
        return new self(_('Error while retrieving a host'));
    }

    /**
     * @return self
     */
    public static function circularTemplateInheritance(): self
    {
        return new self(_('Circular inheritance not allowed'), self::CODE_CONFLICT);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileDeleting(\Throwable $ex): self
    {
        return new self(_('Error while deleting the host'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileSearchingForHosts(\Throwable $ex): self
    {
        return new self(_('Error while searching for host configurations'));
    }

    /**
     * @return HostException
     */
    public static function errorWhileRetrievingHostStatusesCount(): self
    {
        return new self(_('Error while retrieving host statuses distribution'));
    }
}
