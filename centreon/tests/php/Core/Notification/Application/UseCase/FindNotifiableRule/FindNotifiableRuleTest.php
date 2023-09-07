<?php

/*
 * Copyright 2005 - 2022 Centreon (https://www.centreon.com/)
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 * http://www.apache.org/licenses/LICENSE-2.0
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

namespace Tests\Core\Notification\Application\UseCase\FindNotifiableRule;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Core\Notification\Application\UseCase\FindNotifiableRule\FindNotifiableRule;
use Core\Notification\Application\Repository\ReadNotificationRepositoryInterface;

beforeEach(function (): void {
    $this->presenter = new FindNotifiableRulePresenterStub();
    $this->useCase = new FindNotifiableRule(
        $this->readNotificationRepository = $this->createMock(ReadNotificationRepositoryInterface::class),
        $this->contact = $this->createMock(ContactInterface::class),
    );

    // TODO
});

it(
    'should TODO',
    function (): void {
        // TODO
        throw new \Exception();
    }
);
