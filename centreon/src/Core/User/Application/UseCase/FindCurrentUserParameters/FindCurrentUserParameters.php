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

namespace Core\User\Application\UseCase\FindCurrentUserParameters;

use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\User\Application\Exception\UserException;
use Core\User\Application\Model\UserInterfaceDensityConverter;
use Core\User\Application\Model\UserThemeConverter;

final class FindCurrentUserParameters
{
    use LoggerTrait;

    public function __construct(
        private readonly ContactInterface $user,
        private readonly DashboardRights $rights
    ) {
    }

    public function __invoke(FindCurrentUserParametersPresenterInterface $presenter): void
    {
        try {
            $response = $this->createResponse($this->user);

            $presenter->presentResponse($response);
        } catch (\Throwable $ex) {
            $presenter->presentResponse(new ErrorResponse(UserException::errorWhileSearchingForUser($ex)));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        }
    }

    /**
     * @param ContactInterface $user
     *
     * @return FindCurrentUserParametersResponse
     */
    public function createResponse(ContactInterface $user): FindCurrentUserParametersResponse
    {
        $dto = new FindCurrentUserParametersResponse();

        $dto->id = $user->getId();
        $dto->name = $user->getName();
        $dto->alias = $user->getAlias();
        $dto->email = $user->getEmail();
        $dto->timezone = $user->getTimezone()->getName();
        $dto->locale = $user->getLocale();
        $dto->isAdmin = $user->isAdmin();
        $dto->useDeprecatedPages = $user->isUsingDeprecatedPages();
        $dto->isExportButtonEnabled = $this->hasExportButtonRole($user);
        $dto->canManageApiTokens = $this->canManageApiTokens($user);
        $dto->theme = UserThemeConverter::fromString($user->getTheme());
        $dto->userInterfaceDensity = UserInterfaceDensityConverter::fromString($user->getUserInterfaceDensity());
        $dto->defaultPage = $user->getDefaultPage()?->getRedirectionUri();

        $dto->dashboardPermissions->globalRole = $this->rights->getGlobalRole();
        $dto->dashboardPermissions->hasViewerRole = $this->rights->hasViewerRole();
        $dto->dashboardPermissions->hasCreatorRole = $this->rights->hasCreatorRole();
        $dto->dashboardPermissions->hasAdminRole = $this->rights->hasAdminRole();

        return $dto;
    }

    /**
     * @param ContactInterface $user
     *
     * @return bool
     */
    private function hasExportButtonRole(ContactInterface $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $user->hasRole(Contact::ROLE_GENERATE_CONFIGURATION);
    }

    /**
     * @param ContactInterface $user
     *
     * @return bool
     */
    private function canManageApiTokens(ContactInterface $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }
        return $user->hasRole(Contact::ROLE_MANAGE_TOKENS);
    }
}
