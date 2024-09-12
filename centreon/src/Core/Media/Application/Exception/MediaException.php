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

declare(strict_types = 1);

namespace Core\Media\Application\Exception;

class MediaException extends \Exception
{
    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to add media'));
    }

    /**
     * @return self
     */
    public static function updateNotAllowed(): self
    {
        return new self(_('You are not allowed to update a media'));
    }

    /**
     * @return self
     */
    public static function errorWhileAddingMedia(): self
    {
        return new self(_('Error while adding a media'));
    }

    /**
     * @return self
     */
    public static function errorWhileUpdatingMedia(): self
    {
        return new self(_('Error while updating a media'));
    }

    /**
     * @return self
     */
    public static function errorWhileSearchingForMedias(): self
    {
        return new self(_('Error while searching for media'));
    }

    /**
     * @return self
     */
    public static function fileExtensionNotAuthorized(): self
    {
        return new self(_('File extension not authorized'));
    }

    /**
     * @return self
     */
    public static function listingNotAllowed(): self
    {
        return new self(_('You are not allowed to list media'));
    }

    /**
     * @return self
     */
    public static function mediaAlreadyExists(): self
    {
        return new self(_('Media already exists'));
    }

    public static function operationRequiresAdminUser(): self
    {
        return new self(_('This operation requires an admin user'));
    }
}
