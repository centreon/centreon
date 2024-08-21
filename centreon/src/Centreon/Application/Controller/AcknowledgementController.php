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

use Centreon\Domain\Acknowledgement\Acknowledgement;
use Centreon\Domain\Acknowledgement\AcknowledgementService;
use Centreon\Domain\Acknowledgement\Interfaces\AcknowledgementServiceInterface;
use Centreon\Domain\Contact\Contact;
use Centreon\Domain\Entity\EntityValidator;
use Centreon\Domain\Exception\EntityNotFoundException;
use Centreon\Domain\Monitoring\Resource as ResourceEntity;
use Centreon\Domain\Option\Interfaces\OptionServiceInterface;
use Centreon\Domain\Option\Option;
use Centreon\Domain\RequestParameters\Interfaces\RequestParametersInterface;
use Core\Security\AccessGroup\Application\Repository\ReadAccessGroupRepositoryInterface;
use FOS\RestBundle\Context\Context;
use FOS\RestBundle\View\View;
use JMS\Serializer\Exception\ValidationFailedException;
use JMS\Serializer\SerializerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Used to manage all requests of hosts acknowledgements.
 */
class AcknowledgementController extends AbstractController
{
    private const ACKNOWLEDGE_RESOURCES_PAYLOAD_VALIDATION_FILE
        = __DIR__ . '/../../../../config/json_validator/latest/Centreon/Acknowledgement/AcknowledgeResources.json';
    private const DISACKNOWLEDGE_RESOURCES_PAYLOAD_VALIDATION_FILE
        = __DIR__ . '/../../../../config/json_validator/latest/Centreon/Acknowledgement/DisacknowledgeResources.json';

    private const
        DEFAULT_ACKNOWLEDGEMENT_STICKY = 'monitoring_ack_sticky',
        DEFAULT_ACKNOWLEDGEMENT_PERSISTENT = 'monitoring_ack_persistent',
        DEFAULT_ACKNOWLEDGEMENT_NOTIFY = 'monitoring_ack_notify',
        DEFAULT_ACKNOWLEDGEMENT_WITH_SERVICES = 'monitoring_ack_svc',
        DEFAULT_ACKNOWLEDGEMENT_FORCE_ACTIVE_CHECKS = 'monitoring_ack_active_checks';

    public function __construct(
        private AcknowledgementServiceInterface $acknowledgementService,
        private ReadAccessGroupRepositoryInterface $readAccessGroupRepository,
        private OptionServiceInterface $optionService,
    ) {
    }

    /**
     * Entry point to find the hosts acknowledgements.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findHostsAcknowledgements(RequestParametersInterface $requestParameters): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();
        $hostsAcknowledgements = $this->acknowledgementService
            ->filterByContact($this->getUser())
            ->findHostsAcknowledgements();

        $context = (new Context())->setGroups(Acknowledgement::SERIALIZER_GROUPS_HOST);

        return $this->view(
            [
                'result' => $hostsAcknowledgements,
                'meta' => $requestParameters->toArray(),
            ]
        )->setContext($context);
    }

    /**
     * Entry point to find acknowledgements linked to a host.
     *
     * @param RequestParametersInterface $requestParameters
     * @param int $hostId
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findAcknowledgementsByHost(
        RequestParametersInterface $requestParameters,
        int $hostId
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();
        $hostsAcknowledgements = $this->acknowledgementService
            ->filterByContact($this->getUser())
            ->findAcknowledgementsByHost($hostId);

        $context = (new Context())->setGroups(Acknowledgement::SERIALIZER_GROUPS_HOST);

        return $this->view(
            [
                'result' => $hostsAcknowledgements,
                'meta' => $requestParameters->toArray(),
            ]
        )->setContext($context);
    }

    /**
     * Entry point to find the services acknowledgements.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findServicesAcknowledgements(RequestParametersInterface $requestParameters): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();
        $servicesAcknowledgements = $this->acknowledgementService
            ->filterByContact($this->getUser())
            ->findServicesAcknowledgements();
        $context = (new Context())->setGroups(Acknowledgement::SERIALIZER_GROUPS_SERVICE);

        return $this->view(
            [
                'result' => $servicesAcknowledgements,
                'meta' => $requestParameters->toArray(),
            ]
        )->setContext($context);
    }

    /**
     * Entry point to find acknowledgements linked to a service.
     *
     * @param RequestParametersInterface $requestParameters
     * @param int $hostId
     * @param int $serviceId
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findAcknowledgementsByService(
        RequestParametersInterface $requestParameters,
        int $hostId,
        int $serviceId
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();
        $servicesAcknowledgements = $this->acknowledgementService
            ->filterByContact($this->getUser())
            ->findAcknowledgementsByService($hostId, $serviceId);
        $context = (new Context())->setGroups(Acknowledgement::SERIALIZER_GROUPS_SERVICE);

        return $this->view(
            [
                'result' => $servicesAcknowledgements,
                'meta' => $requestParameters->toArray(),
            ]
        )->setContext($context);
    }

    /**
     * Entry point to find acknowledgements linked to a meta service.
     *
     * @param RequestParametersInterface $requestParameters
     * @param int $metaId
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findAcknowledgementsByMetaService(
        RequestParametersInterface $requestParameters,
        int $metaId
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();
        $metaServicesAcknowledgements = $this->acknowledgementService
            ->filterByContact($this->getUser())
            ->findAcknowledgementsByMetaService($metaId);
        $context = (new Context())->setGroups(Acknowledgement::SERIALIZER_GROUPS_SERVICE);

        return $this->view(
            [
                'result' => $metaServicesAcknowledgements,
                'meta' => $requestParameters->toArray(),
            ]
        )->setContext($context);
    }

    /**
     * Entry point to add multiple host acknowledgements.
     *
     * @param Request $request
     * @param EntityValidator $entityValidator
     * @param SerializerInterface $serializer
     *
     * @throws \Exception
     *
     * @return View
     */
    public function addHostAcknowledgements(
        Request $request,
        EntityValidator $entityValidator,
        SerializerInterface $serializer
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_HOST_ACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        /**
         * @var Acknowledgement[] $acknowledgements
         */
        $acknowledgements = $serializer->deserialize(
            (string) $request->getContent(),
            'array<' . Acknowledgement::class . '>',
            'json'
        );

        $this->acknowledgementService->filterByContact($contact);

        foreach ($acknowledgements as $acknowledgement) {
            $errors = $entityValidator->validate(
                $acknowledgement,
                null,
                AcknowledgementService::VALIDATION_GROUPS_ADD_HOST_ACKS
            );

            if ($errors->count() > 0) {
                throw new ValidationFailedException($errors);
            }

            try {
                $this->acknowledgementService->addHostAcknowledgement($acknowledgement);
            } catch (EntityNotFoundException $e) {
                continue;
            }
        }

        return $this->view();
    }

    /**
     * Entry point to add multiple service acknowledgements.
     *
     * @param Request $request
     * @param EntityValidator $entityValidator
     * @param SerializerInterface $serializer
     *
     * @throws \Exception
     *
     * @return View
     */
    public function addServiceAcknowledgements(
        Request $request,
        EntityValidator $entityValidator,
        SerializerInterface $serializer
    ): View {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_ACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        /**
         * @var Acknowledgement[] $acknowledgements
         */
        $acknowledgements = $serializer->deserialize(
            (string) $request->getContent(),
            'array<' . Acknowledgement::class . '>',
            'json'
        );

        $this->acknowledgementService->filterByContact($contact);

        foreach ($acknowledgements as $acknowledgement) {
            $errors = $entityValidator->validate(
                $acknowledgement,
                null,
                AcknowledgementService::VALIDATION_GROUPS_ADD_SERVICE_ACKS
            );

            if ($errors->count() > 0) {
                throw new ValidationFailedException($errors);
            }

            try {
                $this->acknowledgementService->addServiceAcknowledgement($acknowledgement);
            } catch (EntityNotFoundException $e) {
                continue;
            }
        }

        return $this->view();
    }

    /**
     * Entry point to add a host acknowledgement.
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
    public function addHostAcknowledgement(
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
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_HOST_ACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Error when decoding your sent data');
        }

        $errors = $entityValidator->validateEntity(
            Acknowledgement::class,
            $payload,
            AcknowledgementService::VALIDATION_GROUPS_ADD_HOST_ACK,
            false // To avoid error message for missing fields
        );

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors);
        }

        /**
         * @var Acknowledgement $acknowledgement
         */
        $acknowledgement = $serializer->deserialize(
            (string) $request->getContent(),
            Acknowledgement::class,
            'json'
        );
        $acknowledgement->setResourceId($hostId);

        $this->acknowledgementService
            ->filterByContact($contact)
            ->addHostAcknowledgement($acknowledgement);

        return $this->view();
    }

    /**
     * Entry point to add a service acknowledgement.
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
    public function addServiceAcknowledgement(
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
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_ACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Error when decoding your sent data');
        }

        $errors = $entityValidator->validateEntity(
            Acknowledgement::class,
            $payload,
            AcknowledgementService::VALIDATION_GROUPS_ADD_SERVICE_ACK,
            false // To show errors on not expected fields
        );

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors);
        }

        /**
         * @var Acknowledgement $acknowledgement
         */
        $acknowledgement = $serializer->deserialize(
            (string) $request->getContent(),
            Acknowledgement::class,
            'json'
        );
        $acknowledgement
            ->setParentResourceId($hostId)
            ->setResourceId($serviceId);

        $this->acknowledgementService
            ->filterByContact($contact)
            ->addServiceAcknowledgement($acknowledgement);

        return $this->view();
    }

    /**
     * Entry point to add a service acknowledgement.
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
    public function addMetaServiceAcknowledgement(
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
        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_ACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $payload = json_decode((string) $request->getContent(), true);

        if (! is_array($payload)) {
            throw new \InvalidArgumentException('Error when decoding your sent data');
        }

        $errors = $entityValidator->validateEntity(
            Acknowledgement::class,
            $payload,
            AcknowledgementService::VALIDATION_GROUPS_ADD_SERVICE_ACK,
            false // To show errors on not expected fields
        );

        if ($errors->count() > 0) {
            throw new ValidationFailedException($errors);
        }

        /**
         * @var Acknowledgement $acknowledgement
         */
        $acknowledgement = $serializer->deserialize(
            (string) $request->getContent(),
            Acknowledgement::class,
            'json'
        );
        $acknowledgement
            ->setResourceId($metaId);

        $this->acknowledgementService
            ->filterByContact($contact)
            ->addMetaServiceAcknowledgement($acknowledgement);

        return $this->view();
    }

    /**
     * Entry point to disacknowledge an acknowledgement.
     *
     * @param int $hostId Host id for which we want to cancel the acknowledgement
     *
     * @throws \Exception
     *
     * @return View
     */
    public function disacknowledgeHost(int $hostId): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();

        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_HOST_DISACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $this->acknowledgementService
            ->filterByContact($contact)
            ->disacknowledgeHost($hostId);

        return $this->view();
    }

    /**
     * Entry point to remove a service acknowledgement.
     *
     * @param int $hostId Host id linked to service
     * @param int $serviceId Service Id for which we want to cancel the acknowledgement
     *
     * @throws \Exception
     *
     * @return View
     */
    public function disacknowledgeService(int $hostId, int $serviceId): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();

        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_DISACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $this->acknowledgementService
            ->filterByContact($contact)
            ->disacknowledgeService($hostId, $serviceId);

        return $this->view();
    }

    /**
     * Entry point to remove a metaservice acknowledgement.
     *
     * @param int $metaId ID of the metaservice
     *
     * @throws \Exception
     *
     * @return View
     */
    public function disacknowledgeMetaService(int $metaId): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /**
         * @var Contact $contact
         */
        $contact = $this->getUser();

        if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_DISACKNOWLEDGEMENT)) {
            return $this->view(null, Response::HTTP_UNAUTHORIZED);
        }

        $this->acknowledgementService
            ->filterByContact($contact)
            ->disacknowledgeMetaService($metaId);

        return $this->view();
    }

    /**
     * Entry point to find one acknowledgement.
     *
     * @param int $acknowledgementId Acknowledgement id to find
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findOneAcknowledgement(int $acknowledgementId): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        $acknowledgement = $this->acknowledgementService
            ->filterByContact($this->getUser())
            ->findOneAcknowledgement($acknowledgementId);

        if ($acknowledgement !== null) {
            $context = (new Context())
                ->setGroups(Acknowledgement::SERIALIZER_GROUPS_SERVICE)
                ->enableMaxDepth();

            return $this->view($acknowledgement)->setContext($context);
        }

            return View::create(null, Response::HTTP_NOT_FOUND, []);
    }

    /**
     * Entry point to find all acknowledgements.
     *
     * @param RequestParametersInterface $requestParameters
     *
     * @throws \Exception
     *
     * @return View
     */
    public function findAcknowledgements(RequestParametersInterface $requestParameters): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /** @var Contact $user */
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

        $acknowledgements = $this->acknowledgementService
            ->filterByContact($this->getUser())
            ->findAcknowledgements();

        $context = (new Context())->setGroups(Acknowledgement::SERIALIZER_GROUPS_SERVICE);

        return $this->view(
            [
                'result' => $acknowledgements,
                'meta' => $requestParameters->toArray(),
            ]
        )->setContext($context);
    }

    /**
     * Entry point to bulk disacknowledge resources (hosts and services).
     *
     * @param Request $request
     *
     * @return View
     */
    public function massDisacknowledgeResources(Request $request): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /** @var Contact $contact */
        $contact = $this->getUser();

        if (false === $contact->isAdmin()) {
            $accessGroups = $this->readAccessGroupRepository->findByContact($contact);
            $accessGroupIds = array_map(
                fn($accessGroup) => $accessGroup->getId(),
                $accessGroups
            );

            if (false === $this->readAccessGroupRepository->hasAccessToResources($accessGroupIds)) {
                return $this->view(null, Response::HTTP_FORBIDDEN);
            }
        }

        /**
         * Validate POST data for disacknowledge resources.
         */
        $payload = $this->validateAndRetrieveDataSent($request, self::DISACKNOWLEDGE_RESOURCES_PAYLOAD_VALIDATION_FILE);

        $this->acknowledgementService->filterByContact($contact);

        $disacknowledgement = $this->createDisacknowledgementFromPayload($payload);

        foreach ($payload['resources'] as $resourcePayload) {
            $resource = $this->createResourceFromPayload($resourcePayload);

            // start disacknowledgement process
            try {
                if ($this->hasDisackRightsForResource($contact, $resource)) {
                    if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_DISACKNOWLEDGEMENT)) {
                        $disacknowledgement->setWithServices(false);
                    }

                    $this->acknowledgementService->disacknowledgeResource(
                        $resource,
                        $disacknowledgement
                    );
                }
            } catch (EntityNotFoundException $e) {
                // don't stop process if a resource is not found
                continue;
            }
        }

        return $this->view(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Entry point to bulk acknowledge resources (hosts and services).
     *
     * @param Request $request
     *
     * @throws \Exception
     *
     * @return View
     */
    public function massAcknowledgeResources(Request $request): View
    {
        $this->denyAccessUnlessGrantedForApiRealtime();

        /** @var Contact $contact */
        $contact = $this->getUser();

        if (false === $contact->isAdmin()) {
            $accessGroups = $this->readAccessGroupRepository->findByContact($contact);
            $accessGroupIds = array_map(
                fn($accessGroup) => $accessGroup->getId(),
                $accessGroups
            );

            if (false === $this->readAccessGroupRepository->hasAccessToResources($accessGroupIds)) {
                return $this->view(null, Response::HTTP_FORBIDDEN);
            }
        }

        /**
         * Validate POST data for acknowledge resources.
         */
        $payload = $this->validateAndRetrieveDataSent($request, self::ACKNOWLEDGE_RESOURCES_PAYLOAD_VALIDATION_FILE);
        $acknowledgement = $this->createAcknowledgementFromPayload($payload);

        $this->acknowledgementService->filterByContact($contact);

        foreach ($payload['resources'] as $resourcePayload) {
            $resource = $this->createResourceFromPayload($resourcePayload);
            // start acknowledgement process
            try {
                if ($this->hasAckRightsForResource($contact, $resource)) {
                    if (! $contact->isAdmin() && ! $contact->hasRole(Contact::ROLE_SERVICE_ACKNOWLEDGEMENT)) {
                        $acknowledgement->setWithServices(false);
                    }

                    $this->acknowledgementService->acknowledgeResource(
                        $resource,
                        $acknowledgement
                    );
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $this->view(null, Response::HTTP_NO_CONTENT);
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

    /**
     * @param array<string, mixed> $payload
     *
     * @return Acknowledgement
     */
    private function createAcknowledgementFromPayload(array $payload): Acknowledgement
    {
        $acknowledgement = new Acknowledgement();

        if (isset($payload['acknowledgement']['comment'])) {
            $acknowledgement->setComment($payload['acknowledgement']['comment']);
        }

        $options = $this->optionService->findSelectedOptions([
            self::DEFAULT_ACKNOWLEDGEMENT_PERSISTENT,
            self::DEFAULT_ACKNOWLEDGEMENT_STICKY,
            self::DEFAULT_ACKNOWLEDGEMENT_NOTIFY,
            self::DEFAULT_ACKNOWLEDGEMENT_WITH_SERVICES,
            self::DEFAULT_ACKNOWLEDGEMENT_FORCE_ACTIVE_CHECKS
        ]);

        $isAcknowledgementPersistent = $acknowledgement->isPersistentComment();
        $isAcknowledgementSticky = $acknowledgement->isSticky();
        $isAcknowledgementNotify = $acknowledgement->isNotifyContacts();
        $isAcknowledgementWithServices = $acknowledgement->isWithServices();
        $isAcknowledgementForceActiveChecks = $acknowledgement->doesForceActiveChecks();
        foreach ($options as $option) {
            switch ($option->getName()) {
                case self::DEFAULT_ACKNOWLEDGEMENT_PERSISTENT:
                    $isAcknowledgementPersistent = (int) $option->getValue() === 1;
                    break;
                case self::DEFAULT_ACKNOWLEDGEMENT_STICKY:
                    $isAcknowledgementSticky = (int) $option->getValue() === 1;
                    break;
                case self::DEFAULT_ACKNOWLEDGEMENT_NOTIFY:
                    $isAcknowledgementNotify = (int) $option->getValue() === 1;
                    break;
                case self::DEFAULT_ACKNOWLEDGEMENT_WITH_SERVICES:
                    $isAcknowledgementWithServices = (int) $option->getValue() === 1;
                    break;
                case self::DEFAULT_ACKNOWLEDGEMENT_FORCE_ACTIVE_CHECKS:
                    $isAcknowledgementForceActiveChecks = (int) $option->getValue() === 1;
                    break;
                default:
                    break;
            }
        }

        isset($payload['acknowledgement']['with_services'])
            ? $acknowledgement->setWithServices($payload['acknowledgement']['with_services'])
            : $acknowledgement->setWithServices($isAcknowledgementWithServices);

        isset($payload['acknowledgement']['force_active_checks'])
            ? $acknowledgement->setForceActiveChecks($payload['acknowledgement']['force_active_checks'])
            : $acknowledgement->setForceActiveChecks($isAcknowledgementForceActiveChecks);

        isset($payload['acknowledgement']['is_notify_contacts'])
            ? $acknowledgement->setNotifyContacts($payload['acknowledgement']['is_notify_contacts'])
            : $acknowledgement->setNotifyContacts($isAcknowledgementNotify);

        isset($payload['acknowledgement']['is_persistent_comment'])
            ? $acknowledgement->setPersistentComment($payload['acknowledgement']['is_persistent_comment'])
            : $acknowledgement->setPersistentComment($isAcknowledgementPersistent);

        isset($payload['acknowledgement']['is_sticky'])
            ? $acknowledgement->setSticky($payload['acknowledgement']['is_sticky'])
            : $acknowledgement->setSticky($isAcknowledgementSticky);

        return $acknowledgement;
    }

    /**
     * @param array<string, mixed> $payload
     *
     * @return Acknowledgement
     */
    private function createDisacknowledgementFromPayload(array $payload): Acknowledgement
    {
        $disacknowledgement = new Acknowledgement();
        if (isset($payload['disacknowledgement']['with_services'])) {
            $disacknowledgement->setWithServices($payload['disacknowledgement']['with_services']);
        }

        return $disacknowledgement;
    }

    /**
     * Check if the resource can be acknowledged.
     *
     * @param Contact $contact
     * @param ResourceEntity $resource
     *
     * @return bool
     */
    private function hasAckRightsForResource(Contact $contact, ResourceEntity $resource): bool
    {
        if ($contact->isAdmin()) {
            return true;
        }

        $hasRights = false;

        $hasRights = match ($resource->getType()) {
            ResourceEntity::TYPE_HOST => $contact->hasRole(Contact::ROLE_HOST_ACKNOWLEDGEMENT),
            ResourceEntity::TYPE_SERVICE, ResourceEntity::TYPE_META => $contact->hasRole(Contact::ROLE_SERVICE_ACKNOWLEDGEMENT),
            default => $hasRights,
        };

        return $hasRights;
    }

    /**
     * Check if the resource can be disacknowledged.
     *
     * @param Contact $contact
     * @param ResourceEntity $resource
     *
     * @return bool
     */
    private function hasDisackRightsForResource(Contact $contact, ResourceEntity $resource): bool
    {
        if ($contact->isAdmin()) {
            return true;
        }

        $hasRights = false;

        $hasRights = match ($resource->getType()) {
            ResourceEntity::TYPE_HOST => $contact->hasRole(Contact::ROLE_HOST_DISACKNOWLEDGEMENT),
            ResourceEntity::TYPE_SERVICE, ResourceEntity::TYPE_META => $contact->hasRole(Contact::ROLE_SERVICE_DISACKNOWLEDGEMENT),
            default => $hasRights,
        };

        return $hasRights;
    }
}
