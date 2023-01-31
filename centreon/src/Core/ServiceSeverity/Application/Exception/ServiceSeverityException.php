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

namespace Core\ServiceSeverity\Application\Exception;

class ServiceSeverityException extends \Exception
{
    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access service severities'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function findServiceSeverities(\Throwable $ex): self
    {
        return new self(_('Error while searching for service severities'), 0, $ex);
    }

     /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete service severities'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function deleteServiceSeverity(\Throwable $ex): self
    {
        return new self(_('Error while deleting service severity'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function addServiceSeverity(\Throwable $ex): self
    {
        return new self(_('Error while creating service severity'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to create service severities'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingJustCreated(): self
    {
        return new self(_('Error while retrieving recently created service severity'));
    }

    /**
     * @return self
     */
    public static function serviceNameAlreadyExists(): self
    {
        return new self(_('Service severity name already exists'));
    }

    /**
     * @param int $iconId
     *
     * @return self
     */
    public static function iconDoesNotExist(int $iconId): self
    {
        return new self(sprintf(_("The service severity icon with id '%d' does not exist"), $iconId));
    }
}
