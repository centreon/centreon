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

namespace Core\Contact\Infrastructure\Api\FindContactTemplates;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Application\Common\UseCase\AbstractPresenter;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\ExceptionHandler;
use Core\Contact\Application\UseCase\FindContactTemplates\{
    FindContactTemplatesPresenterInterface,
    FindContactTemplatesResponse
};
use Core\Infrastructure\Common\Presenter\PresenterFormatterInterface;

class FindContactTemplatesPresenter extends AbstractPresenter implements FindContactTemplatesPresenterInterface
{
    use LoggerTrait;

    /**
     * @param RequestParametersInterface $requestParameters
     * @param PresenterFormatterInterface $presenterFormatter
     * @param ContactInterface $user
     * @param ExceptionHandler $exceptionHandler
     */
    public function __construct(
        private RequestParametersInterface $requestParameters,
        protected PresenterFormatterInterface $presenterFormatter,
        private readonly ContactInterface $user,
        private readonly ExceptionHandler $exceptionHandler,

    ) {
        parent::__construct($presenterFormatter);
    }

    /**
     * @param FindContactTemplatesResponse|ResponseStatusInterface $response
     */
    public function presentResponse(ResponseStatusInterface|FindContactTemplatesResponse $response): void
    {
        if ($response instanceof ResponseStatusInterface) {
            if ($response instanceof ErrorResponse && ! is_null($response->getException())) {
                $this->exceptionHandler->log($response->getException());
            } elseif ($response instanceof ForbiddenResponse) {
                $this->error('User doesn\'t have sufficient rights to list contact templates', [
                    'user_id' => $this->user->getId(),
                ]);;
            }
            $this->setResponseStatus($response);

            return;
        }

        $this->present([
            'result' => $response->contactTemplates,
            'meta' => $this->requestParameters->toArray(),
        ]);
    }
}
