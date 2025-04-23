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

namespace Core\HostCategory\Application\Exception;

class HostCategoryException extends \Exception
{
    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access host categories'));
    }

    /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete host categories'));
    }

    /**
     * @return self
     */
    public static function writingActionsNotAllowed(): self
    {
        return new self(_('You are not allowed to create/modify host categories'));
    }

    /**
     * @param \Throwable $ex
     * @param int $hostCategoryId
     *
     * @return self
     */
    public static function findHostCategory(\Throwable $ex, int $hostCategoryId): self
    {
        return new self(sprintf(_('Error when searching for the host category #%d'), $hostCategoryId), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function findHostCategories(\Throwable $ex): self
    {
        return new self(_('Error while searching for host categories'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function deleteHostCategory(\Throwable $ex): self
    {
        return new self(_('Error while deleting host category'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function addHostCategory(\Throwable $ex): self
    {
        return new self(_('Error while creating host category'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function updateHostCategory(\Throwable $ex): self
    {
        return new self(_('Error while updating the host category'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function hostNameAlreadyExists(): self
    {
        return new self(_('Host category name already exists'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingObject(): self
    {
        return new self(_('Error while retrieving a host category'));
    }

    public static function errorWhileRetrievingRealTimeHostCategories(\Throwable $exception): self
    {
        return new self(_('Error while searching host categories in real time context'), 0, $exception);
    }
}
