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

namespace Tests\Core\Notification\Domain\Model;

use Centreon\Domain\Common\Assertion\AssertionException;
use Core\Notification\Domain\Model\NewNotification;
use Core\Notification\Domain\Model\TimePeriod;

beforeEach(function (): void {
    $this->name = 'notification-name';
    $this->timePeriod = new TimePeriod(1, '');
    $this->isActivated = false;
});

it('should return properly set notification instance', function (): void {
    $notification = new NewNotification($this->name, $this->timePeriod, $this->isActivated);

    expect($notification->getName())->toBe($this->name)
        ->and($notification->getTimePeriod())->toBe($this->timePeriod)
        ->and($notification->isActivated())->toBe(false);
});

it('should trim the "name" field', function (): void {
    $notification = new NewNotification(
        $nameWithSpaces = '  my-name  ',
        $this->timePeriod,
        $this->isActivated
    );

    expect($notification->getName())->toBe(trim($nameWithSpaces));
});

it('should throw an exception when notification name is empty', function (): void {
    new NewNotification('',
        $this->timePeriod,
        $this->isActivated
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::notEmptyString('NewNotification::name')
        ->getMessage()
);

it('should throw an exception when notification name is too long', function (): void {
    new NewNotification(
        str_repeat('a', NewNotification::MAX_NAME_LENGTH + 1),
        $this->timePeriod,
        $this->isActivated
    );
})->throws(
    \Assert\InvalidArgumentException::class,
    AssertionException::maxLength(
        str_repeat('a', NewNotification::MAX_NAME_LENGTH + 1),
        NewNotification::MAX_NAME_LENGTH + 1,
        NewNotification::MAX_NAME_LENGTH,
        'NewNotification::name'
    )->getMessage()
);
