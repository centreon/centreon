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

namespace Core\HostGroup\Infrastructure\API\UpdateHostGroup;

use Centreon\Application\Controller\AbstractController;
use Core\Application\Common\UseCase\ResponseStatusInterface;
use Core\Common\Infrastructure\FeatureFlags;
use Core\HostGroup\Application\UseCase\UpdateHostGroup\UpdateHostGroup;
use Core\HostGroup\Infrastructure\Voters\HostGroupVoters;
use Core\Infrastructure\Common\Api\StandardPresenter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted(
    HostGroupVoters::HOSTGROUP_UPDATE,
    null,
    'Your are not allowed to edit host groups',
    Response::HTTP_FORBIDDEN
)]
final class UpdateHostGroupController extends AbstractController
{
    public function __construct(private readonly bool $isCloudPlatform)
    {
    }

    /**
     * @param int $hostGroupId
     * @param UpdateHostGroupInput $request
     * @param UpdateHostGroup $useCase
     * @param StandardPresenter $presenter
     *
     * @return Response
     */
    public function __invoke(
        int $hostGroupId,
        #[MapRequestPayload()] UpdateHostGroupInput $request,
        UpdateHostGroup $useCase,
        StandardPresenter $presenter,
    ): Response {
        $response = $useCase(UpdateHostGroupRequestTransformer::transform(
            $request,
            $hostGroupId,
            $this->isCloudPlatform
        ));

        if ($response instanceof ResponseStatusInterface) {
            return $this->createResponse($response);
        }

        return JsonResponse::fromJsonString($presenter->present($response, ['groups' => ['HostGroup:Add'], 'is_cloud_platform' => $this->isCloudPlatform]));
    }
}
