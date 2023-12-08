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

namespace Tests\Core\User\Application\UseCase\FindCurrentUserParameters;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Menu\Model\Page;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Configuration\User\Exception\UserException;
use Core\Dashboard\Domain\Model\DashboardRights;
use Core\Dashboard\Domain\Model\Role\DashboardGlobalRole;
use Core\User\Application\UseCase\FindCurrentUserParameters\FindCurrentUserParameters;
use Core\User\Application\UseCase\FindCurrentUserParameters\FindCurrentUserParametersResponse;
use Core\User\Domain\Model\UserInterfaceDensity;
use Core\User\Domain\Model\UserTheme;
use Core\User\Infrastructure\Model\UserInterfaceDensityConverter;
use Core\User\Infrastructure\Model\UserThemeConverter;
use Tests\Core\User\Infrastructure\API\FindCurrentUserParameters\FindCurrentUserParametersPresenterStub;

beforeEach(function (): void {
    $this->presenter = new FindCurrentUserParametersPresenterStub();
    $this->useCase = new FindCurrentUserParameters(
        $this->contact = $this->createMock(ContactInterface::class),
        $this->rights = $this->createMock(DashboardRights::class)
    );
    $this->randomInt = static fn(): int => random_int(1, 1_000_000);
    $this->randomBool = static fn(): bool => (bool) random_int(0, 1);
    $this->randomString = static fn(): string => 'panel-' . mb_substr(md5(random_bytes(10)), 0, 6);
});

it(
    'should present an ErrorResponse when an exception is thrown',
    function (): void {
        $this->contact->method('getId')->willThrowException($ex = new \Exception());

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)
            ->toBeInstanceOf(ErrorResponse::class)
            ->and($this->presenter->data->getMessage())
            ->toBe(UserException::errorWhileSearchingForUser($ex)->getMessage());
    }
);

it(
    'should present a valid response when the user is retrieved',
    function (): void {
        $rand

            = $this->contact->method('hasRole')->willReturn($isExportButtonEnabled = true);
        $this->contact->method('getId')->willReturn($id = ($this->randomInt)());
        $this->contact->method('getName')->willReturn($name = ($this->randomString)());
        $this->contact->method('getAlias')->willReturn($alias = ($this->randomString)());
        $this->contact->method('getEmail')->willReturn($email = ($this->randomString)());
        $this->contact->method('getTimezone')->willReturn($timezone = new \DateTimeZone('UTC'));
        $this->contact->method('getLocale')->willReturn($locale = ($this->randomString)());
        $this->contact->method('isAdmin')->willReturn($isAdmin = ($this->randomBool)());
        $this->contact->method('isUsingDeprecatedPages')->willReturn($useDeprecatedPages = ($this->randomBool)());
        $this->contact->method('getTheme')
            ->willReturn(UserThemeConverter::toString($theme = UserTheme::Dark));
        $this->contact->method('getUserInterfaceDensity')
            ->willReturn(UserInterfaceDensityConverter::toString($uiDensity = UserInterfaceDensity::Extended));
        $this->contact->method('getDefaultPage')->willReturn($page = $this->createMock(Page::class));

        $page->method('getRedirectionUri')->willReturn($defaultPage = ($this->randomString)());

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(FindCurrentUserParametersResponse::class)
            ->and($this->presenter->data->id)->toBe($id)
            ->and($this->presenter->data->name)->toBe($name)
            ->and($this->presenter->data->alias)->toBe($alias)
            ->and($this->presenter->data->email)->toBe($email)
            ->and($this->presenter->data->timezone)->toBe($timezone->getName())
            ->and($this->presenter->data->locale)->toBe($locale)
            ->and($this->presenter->data->isAdmin)->toBe($isAdmin)
            ->and($this->presenter->data->useDeprecatedPages)->toBe($useDeprecatedPages)
            ->and($this->presenter->data->isExportButtonEnabled)->toBe($isExportButtonEnabled)
            ->and($this->presenter->data->theme)->toBe($theme)
            ->and($this->presenter->data->userInterfaceDensity)->toBe($uiDensity)
            ->and($this->presenter->data->defaultPage)->toBe($defaultPage)
            ->and($this->presenter->data->dashboardPermissions->hasViewerRole)->toBeFalse()
            ->and($this->presenter->data->dashboardPermissions->hasCreatorRole)->toBeFalse()
            ->and($this->presenter->data->dashboardPermissions->hasAdminRole)->toBeFalse()
            ->and($this->presenter->data->dashboardPermissions->globalRole)->toBeNull();
    }
);

it(
    'should present a valid dashboard permission dto in the response',
    function (DashboardGlobalRole $globalRole): void {
        $this->rights->method('hasViewerRole')->willReturn($hasViewerRole = ($this->randomBool)());
        $this->rights->method('hasCreatorRole')->willReturn($hasCreatorRole = ($this->randomBool)());
        $this->rights->method('hasAdminRole')->willReturn($hasAdminRole = ($this->randomBool)());
        $this->rights->method('getGlobalRole')
            ->willReturn($globalRole);

        ($this->useCase)($this->presenter);

        expect($this->presenter->data)->toBeInstanceOf(FindCurrentUserParametersResponse::class)
            ->and($this->presenter->data->dashboardPermissions->hasViewerRole)->toBe($hasViewerRole)
            ->and($this->presenter->data->dashboardPermissions->hasCreatorRole)->toBe($hasCreatorRole)
            ->and($this->presenter->data->dashboardPermissions->hasAdminRole)->toBe($hasAdminRole)
            ->and($this->presenter->data->dashboardPermissions->globalRole)->toBe($globalRole);
    }
)->with(
    iterator_to_array(
        (static function (): \Generator {
            foreach (DashboardGlobalRole::cases() as $role) {
                yield $role->name => [$role];
            }
        })()
    )
);
