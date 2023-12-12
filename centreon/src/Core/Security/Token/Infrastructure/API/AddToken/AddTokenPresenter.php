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

namespace Core\Security\Token\Infrastructure\API\AddToken;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\CreatedResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Infrastructure\Common\Presenter\PresenterTrait;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenPresenterInterface;
use Core\Security\Token\Application\UseCase\AddToken\AddTokenResponse;

class AddTokenPresenter extends AbstractPresenter implements AddTokenPresenterInterface
{
    use PresenterTrait;

    /**
     * @inheritDoc
     */
    public function presentResponse(ResponseStatusInterface|AddTokenResponse $data): void
    {
        if ($data instanceof ResponseStatusInterface) {
            $this->setResponseStatus($data);
        } else {
            $this->present(
                new CreatedResponse(
                    null,
                    [
                        'name' => $data->name,
                        'user' => [
                            'id' => $data->userId,
                            'name' => $data->userName,
                        ],
                        'creator' => [
                            'id' => $data->creatorId,
                            'name' => $data->creatorName,
                        ],
                        'token' => $data->token,
                        'creation_date' => $this->formatDateToIso8601($data->creationDate),
                        'expiration_date' => $this->formatDateToIso8601($data->expirationDate),
                        'is_revoked' => $data->isRevoked,
                    ]
                )
            );

            // Note: not setting location as required route doesn't exist yet
        }
    }
}
