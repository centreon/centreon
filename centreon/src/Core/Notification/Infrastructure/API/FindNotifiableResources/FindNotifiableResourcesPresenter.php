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

namespace Core\Notification\Infrastructure\API\FindNotifiableResources;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Notification\Application\UseCase\FindNotifiableResources\{
    FindNotifiableResourcesPresenterInterface as PresenterInterface,
    FindNotifiableResourcesResponse
};

class FindNotifiableResourcesPresenter extends AbstractPresenter implements PresenterInterface
{
    /**
     * @inheritDoc
     */
    public function presentResponse(FindNotifiableResourcesResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof FindNotifiableResourcesResponse) {
            $result = [];
            foreach ($response->notifiableResources as $notifiableResource) {
                $result[] = [
                    'notification_id' => $notifiableResource->notificationId,
                    'hosts' => $notifiableResource->hosts,
                ];
            }
            $this->present([
                'uid' => $response->uid,
                'result' => $result,
            ]);
        } else {
            $this->setResponseStatus($response);
        }
    }
}
