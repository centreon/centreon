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

namespace Core\Dashboard\Application\Exception;

class DashboardException extends \Exception
{
    public const CODE_NOT_FOUND = 1;
    public const CODE_FORBIDDEN = 2;

    /**
     * @return self
     */
    public static function errorWhileSearching(): self
    {
        return new self(_('Error while searching for dashboards'));
    }

    /**
     * @return self
     */
    public static function accessNotAllowed(): self
    {
        return new self(_('You are not allowed to access dashboards'));
    }

    /**
     * @return self
     */
    public static function accessNotAllowedForWriting(): self
    {
        return new self(_('You are not allowed to perform write operations on dashboards'));
    }

    /**
     * @param int $dashboardId
     *
     * @return self
     */
    public static function dashboardAccessRightsNotAllowed(int $dashboardId): self
    {
        return new self(
            sprintf(
                _('You cannot view access rights on the dashboard #%d'),
                $dashboardId
            )
        );
    }

    /**
     * @param int $dashboardId
     *
     * @return self
     */
    public static function dashboardAccessRightsNotAllowedForWriting(int $dashboardId): self
    {
        return new self(
            sprintf(
                _('You are not allowed to edit access rights on the dashboard #%d'),
                $dashboardId
            ),
            self::CODE_FORBIDDEN
        );
    }

    /**
     * @return self
     */
    public static function errorWhileAdding(): self
    {
        return new self(_('Error while adding a dashboard'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrievingJustCreated(): self
    {
        return new self(_('Error while retrieving newly created dashboard'));
    }

    /**
     * @return self
     */
    public static function errorWhileRetrieving(): self
    {
        return new self(_('Error while retrieving a dashboard'));
    }

    /**
     * @return self
     */
    public static function errorWhileDeleting(): self
    {
        return new self(_('Error while deleting a dashboard'));
    }

    /**
     * @return self
     */
    public static function errorWhileUpdating(): self
    {
        return new self(_('Error while updating a dashboard'));
    }

    /**
     * @return self
     */
    public static function errorTryingToUpdateAPanelWhichDoesNotBelongsToTheDashboard(): self
    {
        return new self(_('Error while trying to update a widget which belongs to another dashboard'));
    }

    /**
     * @param int $dashboardId
     *
     * @return self
     */
    public static function theDashboardDoesNotExist(int $dashboardId): self
    {
        return new self(sprintf(_('The dashboard [%d] does not exist'), $dashboardId), self::CODE_NOT_FOUND);
    }

    /**
     * @param int[] $contactIds
     *
     * @return self
     */
    public static function theContactsDoNotExist(array $contactIds): self
    {
        return new self(sprintf(_('The contacts [%s] do not exist'), implode(', ', $contactIds)));
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @return self
     */
    public static function theContactGroupsDoNotExist(array $contactGroupIds): self
    {
        return new self(sprintf(_('The contact groups [%s] do not exist'), implode(', ', $contactGroupIds)));
    }

    /**
     * @param int[] $contactIds
     *
     * @return self
     */
    public static function theContactsDoesNotHaveDashboardAccessRights(array $contactIds): self
    {
        return new self (
            sprintf(_('The contacts [%s] do not have any dashboard Access rights'), implode(', ', $contactIds))
        );
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @return self
     */
    public static function theContactGroupsDoesNotHaveDashboardAccessRights(array $contactGroupIds): self
    {
        return new self (
            sprintf(
                _('The contact groups [%s] do not have any dashboard Access rights'),
                implode(', ', $contactGroupIds)
            )
        );
    }

    /**
     * @return self
     */
    public static function contactForShareShouldBeUnique(): self
    {
        return new self(_('You cannot share the same dashboard to a contact several times'));
    }

    /**
     * @return self
     */
    public static function contactGroupForShareShouldBeUnique(): self
    {
        return new self(_('You cannot share the same dashboard to a contact group several times'));
    }

    public static function notSufficientAccessRightForUser(int $contactId, string $role): self
    {
        return new self(sprintf(_('No sufficient access rights to user [%d] to give role [%s]'), $contactId, $role));
    }

    public static function notSufficientAccessRightForContactGroup(int $contactGroupId, string $role): self
    {
        return new self(
            sprintf(_('No sufficient access rights to contact group [%d] to give role [%s]'), $contactGroupId, $role)
        );
    }

    /**
     * @param int[] $contactIds
     *
     * @return self
     */
    public static function userAreNotInAccessGroups(array $contactIds): self
    {
        return new self(sprintf(
            _('The users [%s] are not in your access groups'),
            implode(', ', $contactIds)
        ));
    }

    /**
     * @param int[] $contactGroupIds
     *
     * @return self
     */
    public static function contactGroupIsNotInUserContactGroups(array $contactGroupIds): self
    {
        return new self(sprintf(
            _('The contact groups [%s] are not in your contact groups'),
            implode(', ', $contactGroupIds)
        ));
    }

    /**
     * @param int $dashboardId
     *
     * @return self
     */
    public static function thumbnailNotFound(int $dashboardId): self
    {
        return new self(sprintf(
            _('The thumbnail provided for dashboard [%s] was not found'),
            $dashboardId
        ));
    }
}
