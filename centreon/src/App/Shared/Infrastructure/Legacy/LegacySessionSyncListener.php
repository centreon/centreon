<?php

/*
 * Copyright 2005 - 2026 Centreon (https://www.centreon.com/)
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

namespace App\Shared\Infrastructure\Legacy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;

#[AsEventListener(priority: 1024)]
final readonly class LegacySessionSyncListener
{
    public function __construct(
        #[Autowire(service: 'session.storage.factory.native')]
        private SessionStorageFactoryInterface $sessionStorageFactory,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        // If session is already started by legacy, attach it to Symfony
        if (session_status() === PHP_SESSION_ACTIVE) {
            $request = $event->getRequest();

            if (! $request->hasSession()) {
                $session = new Session($this->sessionStorageFactory->createStorage($request));
                $request->setSession($session);
            }
        }
    }
}
