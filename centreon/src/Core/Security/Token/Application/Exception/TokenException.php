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

namespace Core\Security\Token\Application\Exception;

class TokenException extends \Exception
{
    public const CODE_CONFLICT = 1;

    /**
     * @return self
     */
    public static function addToken(): self
    {
        return new self(_('Error while adding a token'));
    }

    /**
     * @return self
     */
    public static function deleteToken(): self
    {
        return new self(_('Error while deleting a token'));
    }

    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to add tokens'));
    }

    /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete tokens'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingObject(): self
    {
        return new self(_('Error while retrieving a token'));
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public static function nameAlreadyExists(string $name): self
    {
        return new self(
            sprintf(_("The token name '%s' already exists"), $name),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param int $userId
     *
     * @return self
     */
    public static function invalidUserId(int $userId): self
    {
        return new self(
            sprintf(_("The user with ID %d doesn't exist"), $userId),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param int $userId
     *
     * @return self
     */
    public static function notAllowedToCreateTokenForUser(int $userId): self
    {
        return new self(
            sprintf(_('You are not allowed to add tokens linked to user ID %d'), $userId),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param int $userId
     *
     * @return self
     */
    public static function notAllowedToDeleteTokenForUser(int $userId): self
    {
        return new self(
            sprintf(_('You are not allowed to delete tokens linked to user ID %d'), $userId),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileSearching(\Throwable $ex): self
    {
        return new self(_('Error while searching for tokens'), 0, $ex);
    }

    public static function notAllowedToListTokens(): self
    {
        return new self(_('You are not allowed to list the tokens'));
    }

    public static function errorWhilePartiallyUpdatingToken(): self
    {
        return new self(_('Error while partially updating the token'));
    }

    public static function notAllowedToPartiallyUpdateToken(): self
    {
        return new self(_('You are not allowed to partially update the token'));
    }
}
