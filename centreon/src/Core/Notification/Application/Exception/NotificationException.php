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

namespace Core\Notification\Application\Exception;

class NotificationException extends \Exception
{
    public const CODE_CONFLICT = 1;

    public static function addNotification(): self
    {
        return new self(_('Error when adding a notification configuration'));
    }

    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to add a notification configuration'));
    }

    public static function errorWhileRetrievingObject(): self
    {
        return new self(_('Error while retrieving a notification configuration'));
    }

    public static function nameAlreadyExists(): self
    {
        return new self(_('The notification configuration name already exists'), self::CODE_CONFLICT);
    }

    public static function emptyArrayNotAllowed(string $name): self
    {
        return new self(sprintf(_('You must provide at least one %s'), $name), self::CODE_CONFLICT);
    }

    public static function invalidId(string $propertyName): self
    {
        return new self(sprintf(_('Invalid id provided for %s'), $propertyName), self::CODE_CONFLICT);
    }

    public static function invalidResourceType(): self
    {
        return new self(_('Invalid resource type'), self::CODE_CONFLICT);
    }

    public static function listNotAllowed(): self
    {
        return new self(_('You are not allowed to list notifications configurations'));
    }

    public static function invalidUsers(): self
    {
        return new self(_('A notification should have at least one user'));
    }
}
