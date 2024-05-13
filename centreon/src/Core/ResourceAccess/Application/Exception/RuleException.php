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

namespace Core\ResourceAccess\Application\Exception;

class RuleException extends \Exception
{
    public const CODE_CONFLICT = 1;

    /**
     * @param \Throwable $ex
     *
     * @return self
     */
    public static function errorWhileDeleting(\Throwable $ex): self
    {
        return new self(_('Error while deleting the resource access rule'), 0, $ex);
    }

    /**
     * @return self
     */
    public static function notAllowed(): self
    {
        return new self(_('You are not allowed to list resource access rules'));
    }

    /**
     * @return self
     */
    public static function errorWhileSearchingRules(): self
    {
        return new self(_('Error while search resource access rules'));
    }

    /**
     * @param string $formattedName
     * @param string $originalName
     *
     * @return self
     */
    public static function nameAlreadyExists(string $formattedName, string $originalName = 'undefined'): self
    {
        return new self(
            sprintf(_('The name %s (original name: %s) already exists'), $formattedName, $originalName),
            self::CODE_CONFLICT
        );
    }

    public static function errorWhileRetrievingARule(): self
    {
        return new self(_('Error while retrieving a resource access rule'));
    }

    public static function addRule(): self
    {
        return new self(_('Error while adding a resource access rule'));
    }

    public static function updateRule(): self
    {
        return new self(_('Error while updating the resource access rule'));
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
            )
        );
    }

    public static function noLinkToContactsOrContactGroups(): self
    {
        return new self(_('At least one contact or contactgroup should be linked to the rule'));
    }
}
