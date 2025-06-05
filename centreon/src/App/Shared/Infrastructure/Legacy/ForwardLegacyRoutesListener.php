<?php

declare(strict_types=1);

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
 */

namespace App\Shared\Infrastructure\Legacy;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

#[AsEventListener(priority: 32)]
final readonly class ForwardLegacyRoutesListener
{
    public function __construct(
        #[Autowire(service: 'router_listener')]
        private RouterListener $routerListener,
        private LegacyKernelWrapper $legacyKernel,
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        try {
            // try to handle the request using the current app
            $this->routerListener->onKernelRequest($event);
        } catch (NotFoundHttpException $e) {
            // if the route is not found in the current app, it may
            // be available in the legacy. Therefore, handle the request
            // using the legacy app
            $response = $this->legacyKernel->handle($event->getRequest(), $event->getRequestType(), catch: true);

            // if the route has been found in the legacy app, use
            // the legacy response
            if (404 !== $response->getStatusCode()) {
                $event->setResponse($response);

                return;
            }

            // otherwise, throw the original exception
            throw $e;
        }
    }
}
