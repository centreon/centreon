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

namespace Core\ServiceCategory\Application\Exception;

class ServiceCategoryException extends \Exception
{
    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access service categories'));
    }

    /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete service categories'));
    }

    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to create service categories'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function findServiceCategories(\Throwable $ex): self
    {
        return new self(_('Error while searching for service categories'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function deleteServiceCategory(\Throwable $ex): self
    {
        return new self(_('Error while deleting service category'), 0, $ex);
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function addServiceCategory(\Throwable $ex): self
    {
        return new self(_('Error while creating service category'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function serviceNameAlreadyExists(): self
    {
        return new self(_('Service category name already exists'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingJustCreated(): self
    {
        return new self(_('Error while retrieving recently created service category'));
    }
}
