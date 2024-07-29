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

namespace Core\AdditionalConnector\Application\Exception;

use Core\AdditionalConnector\Domain\Model\Type;

class AdditionalConnectorException extends \Exception
{
    public const CODE_CONFLICT = 1;

    /**
     * @return self
     */
    public static function addAdditionalConnector(): self
    {
        return new self(_('Error while adding an additional connector'));
    }

    /**
     * @return self
     */
    public static function deleteAdditionalConnector(): self
    {
        return new self(_('Error while deleting an additional connector'));
    }

    /**
     * @return self
     */
    public static function findAdditionalConnectors(): self
    {
        return new self(_('Error while searching for additional connectors'));
    }

    /**
     * @param string $type
     *
     * @return self
     */
    public static function findPollers(string $type): self
    {
        return new self( sprintf(
            _("Error while searching for available pollers for type '%s'"),
            $type
        ));
    }

    /**
     * @return self
     */
    public static function addNotAllowed(): self
    {
        return new self(_('You are not allowed to add additional connectors'));
    }

    /**
     * @return self
     */
    public static function deleteNotAllowed(): self
    {
        return new self(_('You are not allowed to delete additional connectors'));
    }

    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access additional connectors'));
    }

    /**
     * @return self
     */
    public static function unsufficientRights(): self
    {
        return new self(_("You don't have sufficient permission for this action"));
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
     * @param Type $type
     * @param int[] $invalidPollers
     *
     * @return self
     */
    public static function alreadyAssociatedPollers(Type $type, array $invalidPollers): self
    {
        return new self(
            sprintf(
                _("An additional connector of type '%s' is already associated with poller ID(s) '%s'"),
                $type->value,
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

    /**
     * @return self
     */
    public static function errorWhileRetrievingObject(): self
    {
        return new self(_('Error while retrieving an additional connector'));
    }

    /**
     * @param string $name
     *
     * @return self
     */
    public static function nameAlreadyExists(string $name): self
    {
        return new self(
            sprintf( _("The additional connector name '%s' already exists"), $name),
            self::CODE_CONFLICT
        );
    }
}
