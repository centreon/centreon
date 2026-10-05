<?php

/*
 * Copyright 2005 - 2025 Centreon (https://www.centreon.com/)
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

namespace Tests\App\MonitoringConfiguration\Infrastructure\Legacy;

use App\MonitoringConfiguration\Infrastructure\Legacy\CapturingDeployServicesPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\NoContentResponse;
use Core\Application\Common\UseCase\NotFoundResponse;
use PHPUnit\Framework\TestCase;

/** The legacy use case never throws, so this presenter is where a failure is observed. */
final class CapturingDeployServicesPresenterTest extends TestCase
{
    public function testItReportsNoFailureBeforeAnythingIsPresented(): void
    {
        self::assertNull((new CapturingDeployServicesPresenter())->failureMessage());
    }

    /**
     * Legacy answers "no content" with no templates, or nothing left to create. Both succeed.
     */
    public function testItTreatsNoContentAsASuccess(): void
    {
        $presenter = new CapturingDeployServicesPresenter();

        $presenter->presentResponse(new NoContentResponse());

        self::assertNull($presenter->failureMessage());
    }

    public function testItCapturesAnErrorResponse(): void
    {
        $presenter = new CapturingDeployServicesPresenter();

        $presenter->presentResponse(new ErrorResponse('deployment blew up'));

        self::assertSame('deployment blew up', $presenter->failureMessage());
    }

    /**
     * Host invisible to the legacy connection, or missing role: must not pass for a success.
     */
    public function testItCapturesANotFoundResponse(): void
    {
        $presenter = new CapturingDeployServicesPresenter();

        $presenter->presentResponse(new NotFoundResponse('Host'));

        self::assertNotNull($presenter->failureMessage());
    }

    public function testItCapturesAForbiddenResponse(): void
    {
        $presenter = new CapturingDeployServicesPresenter();

        $presenter->presentResponse(new ForbiddenResponse('nope'));

        self::assertSame('nope', $presenter->failureMessage());
    }
}
