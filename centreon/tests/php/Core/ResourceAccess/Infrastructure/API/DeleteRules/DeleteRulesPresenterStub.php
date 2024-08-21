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

namespace Tests\Core\ResourceAccess\Infrastructure\API\DeleteRules;

use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\MultiStatusResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\ResourceAccess\Application\UseCase\DeleteRules\DeleteRulesPresenterInterface;
use Core\ResourceAccess\Application\UseCase\DeleteRules\DeleteRulesResponse;
use Core\ResourceAccess\Application\UseCase\DeleteRules\DeleteRulesStatusResponse;
use Core\ResourceAccess\Domain\Model\ResponseCode;
use Symfony\Component\HttpFoundation\Response;

class DeleteRulesPresenterStub extends AbstractPresenter implements DeleteRulesPresenterInterface
{
    private const HREF = 'centreon/api/latest/administration/resource-access/rules/';

    /** @var ResponseStatusInterface */
    public ResponseStatusInterface $response;

    public function presentResponse(DeleteRulesResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof DeleteRulesResponse) {
            $multiStatusResponse = [
                'results' => array_map(fn(DeleteRulesStatusResponse $dto) => [
                    'self' => self::HREF . $dto->id,
                    'status' => $this->enumToIntConverter($dto->status),
                    'message' => $dto->message,
                ], $response->responseStatuses),
            ];

            $this->response = new MultiStatusResponse($multiStatusResponse);
        } else {
            $this->response = $response;
        }
    }

    /**
     * @param ResponseCode $code
     *
     * @return int
     */
    private function enumToIntConverter(ResponseCode $code): int
    {
        return match ($code) {
            ResponseCode::OK => Response::HTTP_NO_CONTENT,
            ResponseCode::NotFound => Response::HTTP_NOT_FOUND,
            ResponseCode::Error => Response::HTTP_INTERNAL_SERVER_ERROR
        };
    }
}
