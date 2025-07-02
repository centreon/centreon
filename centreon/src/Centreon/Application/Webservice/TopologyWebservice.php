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

namespace Centreon\Application\Webservice;

use App\Kernel;
use Centreon\Application\DataRepresenter\Response;
use Centreon\Application\DataRepresenter\Topology\NavigationList;
use Centreon\Domain\Entity\Topology;
use Centreon\Domain\Repository\TopologyRepository;
use Centreon\Infrastructure\Webservice;
use Centreon\ServiceProvider;
use Core\Common\Infrastructure\FeatureFlags;
use OpenApi\Annotations as OA;

/**
 * @OA\Tag(name="centreon_topology", description="Web Service for Topology")
 */
class TopologyWebservice extends Webservice\WebServiceAbstract implements
    Webservice\WebserviceAutorizePublicInterface
{
    /** @var int */
    private const POLLER_PAGE = 60901;

    private ?FeatureFlags $featureFlags = null;

    /**
     * @throws \Exception
     */
    public function __construct()
    {
        $featureFlags = Kernel::createForWeb()->getContainer()->get(FeatureFlags::class);
        parent::__construct();

        if (! ($featureFlags instanceof FeatureFlags)) {
            throw new \Exception('Unable to retrieve the FeatureFlags service');
        }

        $this->featureFlags = $featureFlags;
    }

    /**
     * List of required services
     *
     * @return array
     */
    public static function dependencies(): array
    {
        return [
            ServiceProvider::CENTREON_DB_MANAGER,
        ];
    }

    /**
     * Name of web service object
     *
     * @return string
     */
    public static function getName(): string
    {
        return 'centreon_topology';
    }

    /**
     * @OA\Get(
     *   path="/internal.php?object=centreon_topology&action=getTopologyByPage",
     *   description="Get topology object by page id",
     *   tags={"centreon_topology"},
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_topology"},
     *          default="centreon_topology"
     *       ),
     *       description="the name of the API object class",
     *       required=true
     *   ),
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *       @OA\Schema(
     *          type="string",
     *          enum={"getTopologyByPage"},
     *          default="getTopologyByPage"
     *       ),
     *       description="the name of the action in the API class",
     *       required=true
     *   ),
     *   @OA\Parameter(
     *       in="query",
     *       name="topology_page",
     *       @OA\Schema(
     *          type="string"
     *       ),
     *       description="Page ID for topology",
     *       required=false
     *   ),
     * )
     * @throws \RestBadRequestException
     * @return array
     */
    public function getGetTopologyByPage(): array
    {
        if (! isset($_GET['topology_page']) || ! $_GET['topology_page']) {
            throw new \RestBadRequestException('You need to send \'topology_page\' in the request.');
        }

        $topologyID = (int) $_GET['topology_page'];
        $statement = $this->pearDB->prepare('SELECT * FROM `topology` WHERE `topology_page` = :id');
        $statement->execute([':id' => $topologyID]);
        $result = $statement->fetch();

        if (! $result) {
            throw new \RestBadRequestException('No topology found.');
        }

        return $result;
    }

    /**
     * @OA\Get(
     *   path="/internal.php?object=centreon_topology&action=navigationList",
     *   description="Get list of menu items by acl",
     *   tags={"centreon_topology"},
     *   @OA\Parameter(
     *       in="query",
     *       name="object",
     *       @OA\Schema(
     *          type="string",
     *          enum={"centreon_topology"},
     *          default="centreon_topology"
     *       ),
     *       description="the name of the API object class",
     *       required=true
     *   ),
     *   @OA\Parameter(
     *       in="query",
     *       name="action",
     *       @OA\Schema(
     *          type="string",
     *          enum={"navigationList"},
     *          default="navigationList"
     *       ),
     *       description="the name of the action in the API class",
     *       required=true
     *   ),
     *   @OA\Parameter(
     *       in="query",
     *       name="reactOnly",
     *       @OA\Schema(
     *          type="integer"
     *       ),
     *       description="fetch react only list(value 1) or full list",
     *       required=false
     *   ),
     *   @OA\Parameter(
     *       in="query",
     *       name="forActive",
     *       @OA\Schema(
     *          type="integer"
     *       ),
     *       description="represent values for active check",
     *       required=false
     *   )
     * )
     * @throws \RestBadRequestException
     */
    public function getNavigationList()
    {
        $user = $this->getDi()[ServiceProvider::CENTREON_USER];

        if (empty($user)) {
            throw new \RestBadRequestException('User not found in session. Please relog.');
        }

        /** @var TopologyRepository $repoTopology */
        $repoTopology = $this->getDi()[ServiceProvider::CENTREON_DB_MANAGER]
            ->getRepository(TopologyRepository::class);

        $dbResult = $repoTopology->getTopologyList($user);

        if ($this->isPollerWizardAccessible($user)) {
            $dbResult[] = $this->createPollerWizardTopology();
        }

        /** @var array<array{name: string, color: string, icon: string}> $navConfig */
        $navConfig = $this->getDi()[ServiceProvider::YML_CONFIG]['navigation'];
        $enabledFeatureFlags = $this->featureFlags?->getEnabled() ?? [];
        $result = new NavigationList($dbResult, $navConfig, $enabledFeatureFlags);

        return new Response($result, true);
    }

    /**
     * @param \CentreonUser $user
     * @return bool
     */
    private function isPollerWizardAccessible(\CentreonUser $user): bool
    {
        $userTopologyAccess = $user->access->getTopology();

        return
            isset($userTopologyAccess[self::POLLER_PAGE])
            && (int) $userTopologyAccess[self::POLLER_PAGE] === \CentreonACL::ACL_ACCESS_READ_WRITE;
    }

    /**
     * @return Topology
     */
    private function createPollerWizardTopology(): Topology
    {
        $topology = new Topology();
        $topology->setTopologyUrl('/poller-wizard/1');
        $topology->setTopologyPage('60959');
        $topology->setTopologyParent(self::POLLER_PAGE);
        $topology->setTopologyName('Poller Wizard Page');
        $topology->setTopologyShow('0');
        $topology->setIsReact('1');

        return $topology;
    }
}
