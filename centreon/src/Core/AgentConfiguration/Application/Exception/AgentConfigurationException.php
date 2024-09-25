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

namespace Core\AgentConfiguration\Application\Exception;

class AgentConfigurationException extends \Exception
{
    public const CODE_CONFLICT = 1;

    public static function addAc(): self
    {
        return new self(_('Error while adding an agent configuration'));
    }

    public static function updateAc(): self
    {
        return new self(_('Error while updating an agent configuration'));
    }

    public static function deleteAc(): self
    {
        return new self(_('Error while deleting an agent configuration'));
    }

    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access agent configurations'));
    }

    public static function unsufficientRights(): self
    {
        return new self(_("You don't have sufficient permissions for this action"));
    }

    /**
     * @param string $propertyName
     * @param int[] $propertyValues
     *
     * @return self
     */
    public static function idsDoNotExist(string $propertyName, array $propertyValues): self
    {
        return new self(
            sprintf(
                _("The %s does not exist with ID(s) '%s'"),
                $propertyName,
                implode(',', $propertyValues)
            ),
            self::CODE_CONFLICT
        );
    }

    /**
     * @param int[] $invalidPollers
     *
     * @return self
     */
    public static function alreadyAssociatedPollers(array $invalidPollers): self
    {
        return new self(
            sprintf(
                _("An agent configuration is already associated with poller ID(s) '%s'"),
                implode(',', $invalidPollers)
            ),
            self::CODE_CONFLICT
        );
    }

    public static function duplicatesNotAllowed(string $propertyName): self
    {
        return new self(
            sprintf(
                _("Duplicates not allowed for property '%s'"),
                $propertyName
            ),
            self::CODE_CONFLICT
        );
    }

    public static function arrayCanNotBeEmpty(string $propertyName): self
    {
        return new self(
            sprintf(
                _("'%s' must contain at least one element"),
                $propertyName
            ),
            self::CODE_CONFLICT
        );
    }

    public static function errorWhileRetrievingObject(): self
    {
        return new self(_('Error while retrieving an agent configuration'));
    }

    public static function nameAlreadyExists(string $name): self
    {
        return new self(
            sprintf( _("The agent configuration name '%s' already exists"), $name),
            self::CODE_CONFLICT
        );
    }

    public static function typeChangeNotAllowed(): self
    {
        return new self(
            _('Changing type of an existing agent configuration is not allowed'),
            self::CODE_CONFLICT
        );
    }

    public static function invalidFilename(string $name, string $value): self
    {
        return new self(
            sprintf(_("Filename '%s' (%s) is invalid"), $value, $name),
            self::CODE_CONFLICT
        );
    }

    public static function onlyOnePoller(int $pollerId, int $acId): self
    {
         return new self(
            sprintf(_('Poller ID #%d is the only one linked to Agent Configuration ID #%d'), $pollerId, $acId),
            self::CODE_CONFLICT
        );
    }
}
