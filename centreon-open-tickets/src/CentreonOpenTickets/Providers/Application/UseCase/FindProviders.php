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

namespace CentreonOpenTickets\Providers\Application\UseCase;

use Centreon\Domain\Contact\Interfaces\ContactInterface;
use Centreon\Domain\Log\LoggerTrait;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Centreon\Infrastructure\RequestParameters\RequestParametersTranslatorException;
use CentreonOpenTickets\Providers\Application\Exception\ProviderException;
use CentreonOpenTickets\Providers\Application\Repository\ReadProviderRepositoryInterface;
use CentreonOpenTickets\Providers\Domain\Model\Provider;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\ForbiddenResponse;

final class FindProviders
{
    use LoggerTrait;
    public const ROLE_CONFIGURATION_OPEN_TICKETS = 'ROLE_CONFIGURATION_NOTIFICATIONS_RULES_RW';

    /**
     * @param ContactInterface $contact
     * @param RequestParametersInterface $requestParameters
     * @param ReadProviderRepositoryInterface $repository
     */
    public function __construct(
        private ContactInterface $contact,
        private RequestParametersInterface $requestParameters,
        private ReadProviderRepositoryInterface $repository
    ) {
    }

    /**
     * @param FindProvidersPresenterInterface $presenter
     */
    public function __invoke(FindProvidersPresenterInterface $presenter): void
    {
        if (! $this->contact->hasTopologyRole(self::ROLE_CONFIGURATION_OPEN_TICKETS)) {
            $this->error(
                "User doesn't have sufficient rights to get ticket providers information",
                [
                    'user_id' => $this->contact->getId(),
                ]
            );
            $presenter->presentResponse(
                new ForbiddenResponse(ProviderException::listingNotAllowed()->getMessage())
            );

            return;
        }

        try {
            $providers = $this->repository->findAll($this->requestParameters);
            $presenter->presentResponse($this->createResponse($providers));
        } catch (RequestParametersTranslatorException $ex) {
            $presenter->presentResponse(new ErrorResponse($ex->getMessage()));
            $this->error($ex->getMessage(), ['trace' => $ex->getTraceAsString()]);
        } catch (\Throwable $exception) {
            $presenter->presentResponse(new ErrorResponse(ProviderException::errorWhileListingProviders()));
            $this->error($exception->getMessage(), ['trace' => $exception->getTraceAsString()]);
        }
    }

    /**
     * @param Provider[] $providers
     *
     * @return FindProvidersResponse
     */
    private function createResponse(array $providers): FindProvidersResponse
    {
        $response = new FindProvidersResponse();
        $response->providers = array_map(
            static fn (Provider $provider): ProviderDto => new ProviderDto(
                id: $provider->getId(),
                name: $provider->getName(),
                type: $provider->getType(),
                isActivated: $provider->isActivated()
            ),
            $providers
        );

        return $response;
    }
}
