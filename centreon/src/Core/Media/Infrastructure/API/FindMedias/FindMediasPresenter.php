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

namespace Core\Media\Infrastructure\API\FindMedias;

use Centreon\Domain\Log\Logger;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\ExceptionLogger\ExceptionLogger;
use Core\Infrastructure\Common\Api\HttpUrlTrait;
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;
use Core\Media\Application\UseCase\FindMedias\FindMediasPresenterInterface;
use Core\Media\Application\UseCase\FindMedias\FindMediasResponse;

class FindMediasPresenter extends AbstractPresenter implements FindMediasPresenterInterface
{
    use HttpUrlTrait;
    private const IMG_FOLDER_PATH = '/img/media/';

    public function __construct(
        private readonly RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
    ) {
        parent::__construct($presenterFormatter);
    }

    public function presentResponse(FindMediasResponse|ResponseStatusInterface $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                ExceptionLogger::create()->log($response->getException(), $response->getContext());
            } elseif ($response instanceof ForbiddenResponse) {
                Logger::create()->warning("User doesn't have sufficient rights to list media", $response->getContext());
            }
            $this->setResponseStatus($response);

            return;
        }

        $result = [];
        foreach ($response->medias as $dto) {
            $result[] = [
                'id' => $dto->id,
                'name' => $dto->filename,
                'directory' => $dto->directory,
                'md5' => $dto->md5,
                'url' => $this->getBaseUri() . self::IMG_FOLDER_PATH . $dto->directory . '/' . $dto->filename,
            ];
        }
        $this->present([
            'result' => $result,
            'meta' => $this->requestParameters->toArray(),
        ]);
    }
}
