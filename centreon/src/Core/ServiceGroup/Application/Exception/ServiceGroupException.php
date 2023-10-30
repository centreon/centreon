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

namespace Core\ServiceGroup\Application\Exception;

class ServiceGroupException extends \Exception
{
    public const CODE_CONFLICT = 1;

    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access service groups'));
    }

    /**
     * @return self
     */
    public static function accessNotAllowedForWriting(): self
    {
        return new self(_('You are not allowed to perform write operations on service groups'));
    }

    /**
     * @return self
     */
    public static function errorWhileSearching(): self
    {
        return new self(_('Error while searching for service groups'));
    }

    /**
     * @return self
     */
    public static function errorWhileDeleting(): self
    {
        return new self(_('Error while deleting a service group'));
    }

    /**
     * @return self
     */
    public static function errorWhileAdding(): self
    {
        return new self(_('Error while adding a service group'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingJustCreated(): self
    {
        return new self(_('Error while retrieving newly created service group'));
    }

    /**
     * @param string $serviceGroupName
     *
     * @return self
     */
    public static function nameAlreadyExists(string $serviceGroupName): self
    {
        return new self(sprintf(_("The service group name '%s' already exists"), $serviceGroupName), self::CODE_CONFLICT);
    }
}
