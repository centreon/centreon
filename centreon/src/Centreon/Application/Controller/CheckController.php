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

namespace Centreon\Application\Controller;

use Centreon\Domain\Check\Check;
use Centreon\Domain\Check\CheckException;
use Centreon\Domain\Check\Interfaces\CheckServiceInterface;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Entity\EntityValidator;
use Centreon\Domain\Exception\EntityNotFoundException;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use FOS\RestBundle\View\View;
use JMS\Serializer\DeserializationContext;
use JMS\Serializer\Exception\ValidationFailedException;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Used to manage all requests to schedule checks on hosts and services.
 */
class CheckController extends AbstractController
{
    // Groups for serialization
    public const CHECK_RESOURCES_PAYLOAD_VALIDATION_FILE
        = __DIR__ . '/../../../../config/json_validator/latest/Centreon/Check/AddChecks.json';
    public const SERIALIZER_GROUPS_HOST = ['check_host'];
    public const SERIALIZER_GROUPS_SERVICE = ['check_service'];
    public const SERIALIZER_GROUPS_HOST_ADD = ['check_host', 'check_host_add'];

    /**
     * CheckController constructor.
     *
     * @param CheckServiceInterface $checkService
     */
    public function __construct(
        private CheckServiceInterface $checkService,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepository
    ) {
    }

    /**
     * Entry point to check multiple hosts.
     *
     * @param Request $request
     * @param EntityValidator $entityValidator
     * @param SerializerInterface $serializer
     *
     * @throws \Exception
     *
     * @return View
     */
    public function checkHosts(
        Request $request,
        EntityValidator $entityValidator,
        SerializerInterface $serializer
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_HOST_CHECK)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $context = DeserializationContext::create()->setGroups(self::SERIALIZER_GROUPS_HOST_ADD);

        /**
         * @var Check[] $checks
         */
        $checks = $serializer->deserialize(
            (string) $request->getContent(),
            'array<' . Check::class . '>',
            'json',
            $context
        );

        $this->checkService->filterByContact($contact);

        $checkTime = new \DateTime();
        foreach ($checks as $check) {
            $check->setCheckTime($checkTime);

            $errors = $entityValidator->validate(
                $check,
                null,
                Check::VALIDATION_GROUPS_HOST_CHECK
            );

            if ($errors->count() > 0) {
                throw new ValidationFailedException($errors);
            }

            try {
                $this->checkService->checkHost($check);
            } catch (EntityNotFoundException $e) {
                continue;
            }
        }

        return $this->view();
    }

    /**
     * Entry point to check multiple services.
     *
     * @param Request $request
     * @param EntityValidator $entityValidator
     * @param SerializerInterface $serializer
     *
     * @throws \Exception
     *
     * @return View
     */
    public function checkServices(
        Request $request,
        EntityValidator $entityValidator,
        SerializerInterface $serializer
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_CHECK)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $context = DeserializationContext::create()->setGroups(self::SERIALIZER_GROUPS_SERVICE);

        /**
         * @var Check[] $checks
         */
        $checks = $serializer->deserialize(
            (string) $request->getContent(),
            'array<' . Check::class . '>',
            'json',
            $context
        );

        $this->checkService->filterByContact($contact);

        $checkTime = new \DateTime();
        foreach ($checks as $check) {
            $check->setCheckTime($checkTime);

            $errors = $entityValidator->validate(
                $check,
                null,
                Check::VALIDATION_GROUPS_SERVICE_CHECK
            );

            if ($errors->count() > 0) {
                throw new ValidationFailedException($errors);
            }

            try {
                $this->checkService->checkService($check);
            } catch (EntityNotFoundException $e) {
                continue;
            }
        }

        return $this->view();
    }

    /**
     * Entry point to check a host.
     *
     * @param Request $request
     * @param EntityValidator $entityValidator
     * @param SerializerInterface $serializer
     * @param int $hostId
     *
     * @throws \Exception
     *
     * @return View
     */
    public function checkHost(
        Request $request,
        EntityValidator $entityValidator,
        SerializerInterface $serializer,
        int $hostId
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_HOST_CHECK)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $context = DeserializationContext::create()->setGroups(self::SERIALIZER_GROUPS_HOST_ADD);

        /**
         * @var Check $check
         */
        $check = $serializer->deserialize(
            (string) $request->getContent(),
            Check::class,
            'json',
            $context
        );
        $check
            ->setResourceId($hostId)
            ->setCheckTime(new \DateTime());

        $errors = $entityValidator->validate(
            $check,
            null,
            Check::VALIDATION_GROUPS_HOST_CHECK
        );

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors);
        }

        $this->checkService
            ->filterByContact($contact)
            ->checkHost($check);

        return $this->view();
    }

    /**
     * Entry point to check a service.
     *
     * @param Request $request
     * @param EntityValidator $entityValidator
     * @param SerializerInterface $serializer
     * @param int $hostId
     * @param int $serviceId
     *
     * @throws \Exception
     *
     * @return View
     */
    public function checkService(
        Request $request,
        EntityValidator $entityValidator,
        SerializerInterface $serializer,
        int $hostId,
        int $serviceId
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (
            ! $contact->isAdmin()
            && ! $contact->hasRole(Contact::ROLE_SERVICE_CHECK)
        ) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $context = DeserializationContext::create()->setGroups(self::SERIALIZER_GROUPS_SERVICE);

        /**
         * @var Check $check
         */
        $check = $serializer->deserialize(
            (string) $request->getContent(),
            Check::class,
            'json',
            $context
        );
        $check
            ->setParentResourceId($hostId)
            ->setResourceId($serviceId)
            ->setCheckTime(new \DateTime());

        $errors = $entityValidator->validate(
            $check,
            null,
            Check::VALIDATION_GROUPS_SERVICE_CHECK
        );

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors);
        }

        $this->checkService
            ->filterByContact($contact)
            ->checkService($check);

        return $this->view();
    }

    /**
     * Entry point to check a meta service.
     *
     * @param Request $request
     * @param EntityValidator $entityValidator
     * @param SerializerInterface $serializer
     * @param int $metaId
     *
     * @throws \Exception
     *
     * @return View
     */
    public function checkMetaService(
        Request $request,
        EntityValidator $entityValidator,
        SerializerInterface $serializer,
        int $metaId
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_CHECK)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $context = DeserializationContext::create()->setGroups(self::SERIALIZER_GROUPS_SERVICE);

        /**
         * @var Check $check
         */
        $check = $serializer->deserialize(
            (string) $request->getContent(),
            Check::class,
            'json',
            $context
        );
        $check
            ->setResourceId($metaId)
            ->setCheckTime(new \DateTime());

        $errors = $entityValidator->validate(
            $check,
            null,
            Check::VALIDATION_GROUPS_META_SERVICE_CHECK
        );

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors);
        }

        $this->checkService
            ->filterByContact($contact)
            ->checkMetaService($check);

        return $this->view();
    }

    /**
     * Entry point to check resources.
     *
     * @param Request $request
     *
     * @throws \Exception
     * @throws CheckException
     *
     * @return View
     */
    public function checkResources(Request $request): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $user
         */
        $user = $this->getUser();

        if (false === $user->isAdmin()) {
            $accessGroups = $this->readAccessGroupRepository->findByContact($user);
            $accessGroupIds = array_map(
                fn($accessGroup) => $accessGroup->getId(),
                $accessGroups
            );

            if (false === $this->readAccessGroupRepository->hasAccessToResources($accessGroupIds)) {
                return $this->view(null, Response::HTTP_FORBIDDEN);
            }
        }

        $payload = $this->validateAndRetrieveDataSent($request, self::CHECK_RESOURCES_PAYLOAD_VALIDATION_FILE);
        $check = $this->createCheckFromPayload($payload);

        foreach ($payload['resources'] as $resourcePayload) {
            $resource = $this->createResourceFromPayload($resourcePayload);
            // start check process
            try {
                if ($this->hasCheckRightsForResource($user, $resource)) {
                    $this->checkService
                        ->filterByContact($user)
                        ->checkResource($check, $resource);
                }
            } catch (EntityNotFoundException $e) {
                continue;
            }
        }

        return $this->view();
    }

    /**
     * Check if the resource can be checked by the current user.
     *
     * @param Contact $contact
     * @param ResourceEntity $resource
     *
     * @return bool
     */
    private function hasCheckRightsForResource(Contact $contact, ResourceEntity $resource): bool
    {
        if ($contact->isAdmin()) {
            return true;
        }

        $hasRights = false;

        switch ($resource->getType()) {
            case ResourceEntity::TYPE_HOST:
                $hasRights = $contact->hasRole(Contact::ROLE_HOST_CHECK);
                break;
            case ResourceEntity::TYPE_SERVICE:
            case ResourceEntity::TYPE_META:
                $hasRights = $contact->hasRole(Contact::ROLE_SERVICE_CHECK);
                break;
        }

        return $hasRights;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return Check
     */
    private function createCheckFromPayload(array $payload): Check
    {
        $check = new Check();
        if (isset($payload['check']['is_forced'])) {
            $check->setForced($payload['check']['is_forced']);
        }

        $check->setCheckTime(new \DateTime());

        return $check;
    }

    /**
     * Creates a ResourceEntity with payload sent.
     *
     * @param array<string, mixed> $payload
     *
     * @return ResourceEntity
     */
    private function createResourceFromPayload(array $payload): ResourceEntity
    {
        $resource = (new ResourceEntity())
            ->setType($payload['type'])
            ->setId($payload['id']);

        if ($payload['parent'] !== null) {
            $resource->setParent(
                (new ResourceEntity())
                    ->setId($payload['parent']['id'])
                    ->setType(ResourceEntity::TYPE_HOST)
            );
        }

        return $resource;
    }
}
