<?php

/*
 * Copyright 2005 - 2024 Centreon (https://www.centreon.com/)
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

namespace Core\Command\Application\Exception;

final class CommandException extends \Exception
{
    public const CODE_CONFLICT = 1;

    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access commands'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileSearching(\Throwable $ex): self
    {
        return new self(_('Error while searching for commands'), 0, $ex);
    }

    /**
     * @param string $name
     *
     * @return CommandException
     */
    public static function nameAlreadyExists(string $name): self
    {
        return new self(
            sprintf( _("The '%s' command name already exists"), $name),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param int $type
     *
     * @return CommandException
     */
    public static function invalidCommandType(int $type): self
    {
        return new self(
            sprintf( _("'%d' is not a valid command type"), $type),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param string[] $arguments
     *
     * @return CommandException
     */
    public static function invalidArguments(array $arguments): self
    {
        return new self(
            sprintf( _('The following arguments are not valid: %s'), implode(', ', $arguments)),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param string[] $macros
     *
     * @return CommandException
     */
    public static function invalidMacros(array $macros): self
    {
        return new self(
            sprintf( _('The following macros are not valid: %s'), implode(', ', $macros)),
            self::CODE_CONFLICT
        );
    }

    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to add a command'));
    }

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileAdding(\Throwable $ex): self
    {
        return new self(_('Error while adding the command'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function errorWhileRetrieving(): self
    {
        return new self(_('Error while retrieving a command'));
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
}
