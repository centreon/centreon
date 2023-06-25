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

use Core\User\Application\UseCase\FindCurrentUserParameters\Response\DashboardPermissionsResponseDto;
use Core\User\Domain\Model\UserInterfaceDensity;
use Core\User\Domain\Model\UserTheme;

final class FindCurrentUserParametersResponse
{
    public UserTheme $theme;

    public UserInterfaceDensity $userInterfaceDensity;

    public function __construct(
        public int $id = 0,
        public string $name = '',
        public string $alias = '',
        public string $email = '',
        public string $timezone = '',
        public ?string $locale = null,
        public bool $isAdmin = false,
        public bool $useDeprecatedPages = false,
        public bool $isExportButtonEnabled = false,
        ?UserTheme $theme = null,
        ?UserInterfaceDensity $userInterfaceDensity = null,
        public ?string $defaultPage = null,
        public DashboardPermissionsResponseDto $dashboardPermissions = new DashboardPermissionsResponseDto(),
    ) {
        $this->theme = $theme ?? UserTheme::Light;
        $this->userInterfaceDensity = $userInterfaceDensity ?? UserInterfaceDensity::Compact;
    }
}
