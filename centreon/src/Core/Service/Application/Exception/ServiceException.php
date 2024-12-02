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

namespace Core\Service\Application\Exception;

class ServiceException extends \Exception
{
    public const CODE_CONFLICT = 1;

    /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete a service'));
    }

    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to add a service'));
    }

    /**
     * @return self
     */
    public static function editNotAllowed(): self
    {
        return new self(_('You are not allowed to edit a service'));
    }

    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access services'));
    }

    /**
     * @return self
     */
    public static function checkCommandCannotBeNull(): self
    {
        return new self(_('The check command cannot be null if the service template is null'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileDeleting(\Throwable $ex): self
    {
        return new self(_('Error while deleting the service'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileAdding(\Throwable $ex): self
    {
        return new self(_('Error while adding the service'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function errorWhileEditing(): self
    {
        return new self(_('Error while updating a service'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrieving(): self
    {
        return new self(_('Error while retrieving a service'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileSearching(\Throwable $ex): self
    {
        return new self(_('Error while searching for services'), 0, $ex);
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
            sprintf(
                _("The %s with value '%d' does not exist"),
                $propertyName,
                $propertyValue
            ),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param string $propertyName
     * @param list<int> $propertyValue
     *
     * @return self
     */
    public static function idsDoNotExist(string $propertyName, array $propertyValue): self
    {
        return new self(
            sprintf(
                _("'%s' does not exist with ID(s) '%s'"),
                $propertyName,
                implode(',', $propertyValue)
            ),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param string $serviceName
     * @param int $hostId
     *
     * @return self
     */
    public static function nameAlreadyExists(string $serviceName, int $hostId): self
    {
        return new self(
            sprintf(
                _("'%s' service name already exists for host ID %d"),
                $serviceName,
                $hostId
            ),
            self::CODE_CONFLICT
        );
    }

    /**
     * @return self
     */
    public static function accessNotAllowedForRealTime(): self
    {
        return new self(_('You are not allowed to access services in the real time context'));
    }

    /**
     * @return ServiceException
     */
    public static function errorWhileRetrievingServiceStatusesCount(): self
    {
        return new self(_('Error while retrieving service statuses distribution'));
    }
}
