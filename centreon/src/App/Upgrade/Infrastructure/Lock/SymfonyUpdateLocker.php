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

namespace App\Upgrade\Infrastructure\Lock;

use App\Upgrade\Application\UpdateLocker;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\LockInterface;

final class SymfonyUpdateLocker implements UpdateLocker
{
    private const LOCK_NAME = 'update-centreon';

    private LockInterface $lock;

    public function __construct(
        LockFactory $lockFactory,
    ) {
        $this->lock = $lockFactory->createLock(self::LOCK_NAME);
    }

    public function lock(): bool
    {
        return $this->lock->acquire();
    }

    public function unlock(): void
    {
        $this->lock->release();
    }
}
