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

namespace Core\HostSeverity\Application\Exception;

class HostSeverityException extends \Exception
{
    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access host severities'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function findHostSeverities(\Throwable $ex): self
    {
        return new self(_('Error while searching for host severities'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     * @param int $hostSeverityId
     *
     * @return self
     */
    public static function findHostSeverity(\Throwable $ex, int $hostSeverityId): self
    {
        return new self(sprintf(_('Error when searching for the host severity #%d'), $hostSeverityId), 0, $ex);
    }

    /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete host severities'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function deleteHostSeverity(\Throwable $ex): self
    {
        return new self(_('Error while deleting host severity'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function addHostSeverity(\Throwable $ex): self
    {
        return new self(_('Error while creating host severity'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function writeActionsNotAllowed(): self
    {
        return new self(_('You are not allowed to create/modify a host severity'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function updateHostSeverity(\Throwable $ex): self
    {
        return new self(_('Error while updating host severity'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingObject(): self
    {
        return new self(_('Error while retrieving host severity'));
    }

    /**
     * @return self
     */
    public static function hostNameAlreadyExists(): self
    {
        return new self(_('Host severity name already exists'));
    }

    /**
     * @param int $iconId
     *
     * @return self
     */
    public static function iconDoesNotExist(int $iconId): self
    {
        return new self(sprintf(_("The host severity icon with id '%d' does not exist"), $iconId));
    }
}
