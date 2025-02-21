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

namespace Core\HostGroup\Infrastructure\API\AddHostGroup;

use Centreon\Application\Controller\AbstractController;
use Centreon\Domain\Log\LoggerTrait;
use Core\Application\Common\UseCase\ErrorResponse;
use Core\Application\Common\UseCase\InvalidArgumentResponse;
use Core\Common\Infrastructure\FeatureFlags;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroup;
use Core\HostGroup\Application\UseCase\AddHostGroup\AddHostGroupRequest;
use Core\HostGroup\Infrastructure\Voters\HostGroupVoters;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Serializer\Exception\ExceptionInterface;

#[IsGranted(
    HostGroupVoters::HOSTGROUP_ADD,
    null,
    'You are not allowed to add host groups',
    Response::HTTP_FORBIDDEN
)]
final class AddHostGroupController extends AbstractController
{
    use LoggerTrait;

    /**
     *
     * @param AddHostGroupInput $request
     * @param AddHostGroup $useCase
     * @param StandardPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        #[MapRequestPayload()] AddHostGroupInput $request,
        AddHostGroup $useCase,
        StandardPresenter $presenter
    ): Response {
        dd($request);
        $response = $useCase(AddHostGroupRequestTransformer::transform($request));

        return JsonResponse::fromJsonString($presenter->present($response));
    }
}
