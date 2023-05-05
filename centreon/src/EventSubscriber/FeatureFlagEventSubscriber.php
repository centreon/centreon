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

namespace EventSubscriber;

use Core\Common\Infrastructure\FeatureFlags;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This event subscriber is dedicated for the Feature Flag system.
 *
 * @see FeatureFlags
 */
class FeatureFlagEventSubscriber implements EventSubscriberInterface
{
    /**
     * @param FeatureFlags $featureFlags
     */
    public function __construct(
        private readonly FeatureFlags $featureFlags
    ) {
    }

    /**
     * Returns an array of event names this subscriber wants to listen to.
     *
     * @return mixed[] The event names to listen to
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [
                ['defineFeaturesInAttributes', 40],
            ],
        ];
    }

    /**
     * We inject in the request a TRUE attribute for each enabled feature of the feature flags system.
     *
     * @param RequestEvent $event
     */
    public function defineFeaturesInAttributes(RequestEvent $event): void
    {
        foreach ($this->featureFlags->getEnabled() as $name) {
            $event->getRequest()->attributes->set('feature.' . $name, true);
        }
    }
}
