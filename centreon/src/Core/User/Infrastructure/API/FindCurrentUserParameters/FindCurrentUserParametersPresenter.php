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

namespace Core\User\Infrastructure\API\FindCurrentUserParameters;

use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Dashboard\Infrastructure\Model\DashboardGlobalRoleConverter;
use Core\Infrastructure\Common\Api\DefaultPresenter;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\User\Application\UseCase\FindCurrentUserParameters\FindCurrentUserParametersPresenterInterface;
use Core\User\Application\UseCase\FindCurrentUserParameters\FindCurrentUserParametersResponse;
use Core\User\Infrastructure\Model\UserInterfaceDensityConverter;
use Core\User\Infrastructure\Model\UserThemeConverter;

class FindCurrentUserParametersPresenter extends DefaultPresenter
implements FindCurrentUserParametersPresenterInterface
{
    public function __construct(
        PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(ResponseStatusInterface|FindCurrentUserParametersResponse $data): void
    {
        if ($data instanceof FindCurrentUserParametersResponse) {
            $array = [
                'id' => $data->id,
                'name' => $data->name,
                'alias' => $data->alias,
                'email' => $data->email,
                'timezone' => $data->timezone,
                'locale' => $data->locale,
                'is_admin' => $data->isAdmin,
                'use_deprecated_pages' => $data->useDeprecatedPages,
                'is_export_button_enabled' => $data->isExportButtonEnabled,
                'can_manage_api_tokens' => $data->canManageApiTokens,
                'theme' => UserThemeConverter::toString($data->theme),
                'user_interface_density' => UserInterfaceDensityConverter::toString($data->userInterfaceDensity),
                'default_page' => $data->defaultPage,
            ];

            $array['dashboard'] = $data->dashboardPermissions->globalRole
                ? [
                    'global_user_role' => DashboardGlobalRoleConverter::toString(
                        $data->dashboardPermissions->globalRole
                    ),
                    'view_dashboards' => $data->dashboardPermissions->hasViewerRole,
                    'create_dashboards' => $data->dashboardPermissions->hasCreatorRole,
                    'administrate_dashboards' => $data->dashboardPermissions->hasAdminRole,
                ]
                : null;

            $this->present($array);
        } else {
            $this->setResponseStatus($data);
        }
    }
}
