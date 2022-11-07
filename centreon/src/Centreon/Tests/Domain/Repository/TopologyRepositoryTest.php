<?php
namespace Centreon\Tests\Domain\Repository;

use PHPUnit\Framework\TestCase;
use \Centreon\Test\Mock\CentreonDB;
use Centreon\Domain\Repository\TopologyRepository;

/**
 * @group Centreon
 * @group ORM-repository
 */
class TopologyRepositoryTest extends TestCase
{
<<<<<<< HEAD
    /**
     * @var ((string|string[][])[]|(string|int[][])[])[]
     */
    protected $datasets = [];

    /**
     * @var TopologyRepository
     */
=======

    protected $datasets = [];
>>>>>>> centreon/dev-21.10.x
    protected $repository;

    protected function setUp(): void
    {
<<<<<<< HEAD
        $db = new CentreonDB();
=======
        $db = new CentreonDB;
>>>>>>> centreon/dev-21.10.x
        $this->datasets = [
            [
                'query' => "SELECT topology_url "
                . "FROM `topology` "
                . "WHERE is_react = '1'",
                'data' => [
                    [
                        'topology_url' => './include/configuration/configGenerate/generateFiles.php'
                    ],
                ],
            ],
            [
                'query' => "SELECT DISTINCT acl_group_topology_relations.acl_topology_id "
                . "FROM acl_group_topology_relations, acl_topology, acl_topology_relations "
                . "WHERE acl_topology_relations.acl_topo_id = acl_topology.acl_topo_id "
                . "AND acl_topology.acl_topo_activate = '1' "
                . "AND acl_group_topology_relations.acl_group_id IN (1) ",
                'data' => [
                    [
                        'acl_topology_id' => 1,
                    ],
                ],
            ],
            [
                'query' => "SELECT topology_topology_id, acl_topology_relations.access_right "
                . "FROM acl_topology_relations, acl_topology "
                . "WHERE acl_topology.acl_topo_activate = '1' "
                . "AND acl_topology.acl_topo_id = acl_topology_relations.acl_topo_id "
<<<<<<< HEAD
                . "AND acl_topology_relations.acl_topo_id = '1' ",
=======
                . "AND acl_topology_relations.acl_topo_id = :acl_topo_id ",
>>>>>>> centreon/dev-21.10.x
                'data' => [
                    [
                        'topology_topology_id' => 1,
                        'access_right' => 0,
                    ],
                    [
                        'topology_topology_id' => 2,
                        'access_right' => 1,
                    ],
                    [
                        'topology_topology_id' => 3,
                        'access_right' => 2,
                    ],
                    [
                        'topology_topology_id' => 4,
                        'access_right' => 3,
                    ],
                ],
            ],
            [
                'query' => "SELECT topology_url "
                . "FROM topology FORCE INDEX (`PRIMARY`) "
                . "WHERE topology_url IS NOT NULL "
                . "AND is_react = '1' "
                . "AND topology_id IN (1, 2, 3, 4) ",
                'data' => [
                    [
                        'topology_url' => './include/configuration/configGenerate/generateFiles.php',
                    ],
                ],
            ],
        ];

        foreach ($this->datasets as $dataset) {
            $db->addResultSet($dataset['query'], $dataset['data']);
            unset($dataset);
        }
<<<<<<< HEAD

=======
        
>>>>>>> centreon/dev-21.10.x
        $this->repository = new TopologyRepository($db);
    }

    /**
     * @covers \Centreon\Domain\Repository\TopologyRepository::getReactTopologiesPerUserWithAcl
     */
    public function testGetReactTopologiesPerUserWithAcl1()
    {
        // with empty $user argument
        $result = $this->repository->getReactTopologiesPerUserWithAcl(null);

        $this->assertEquals([], $result);
        unset($result);
    }

    /**
     * @covers \Centreon\Domain\Repository\TopologyRepository::getReactTopologiesPerUserWithAcl
     */
    public function testGetReactTopologiesPerUserWithAcl2()
    {

        // if user admin
        $user = new class {
<<<<<<< HEAD
            /**
             * @var boolean
             */
=======

>>>>>>> centreon/dev-21.10.x
            public $admin = true;
        };

        $result = $this->repository->getReactTopologiesPerUserWithAcl($user);

        $this->assertEquals(array_values($this->datasets[0]['data'][0]), $result);
    }

    /**
     * @covers \Centreon\Domain\Repository\TopologyRepository::getReactTopologiesPerUserWithAcl
     */
    public function testGetReactTopologiesPerUserWithAcl3()
    {
        // if user non-admin
        $user = new class {
<<<<<<< HEAD
            /**
             * @var boolean
             */
            public $admin = false;

            /**
             * @var mixed
             */
=======

            public $admin = false;
>>>>>>> centreon/dev-21.10.x
            public $access;

            public function __construct()
            {
                $this->access = new class {
<<<<<<< HEAD
                    /**
                     * @return int[]
                     */
                    public function getAccessGroups(): array
=======

                    public function getAccessGroups()
>>>>>>> centreon/dev-21.10.x
                    {
                        return [1];
                    }

<<<<<<< HEAD
                    /**
                     * @return string
                     */
                    public function getAccessGroupsString(): string
=======
                    public function getAccessGroupsString()
>>>>>>> centreon/dev-21.10.x
                    {
                        return '1';
                    }
                };
            }
        };

        $result = $this->repository->getReactTopologiesPerUserWithAcl($user);

        $this->assertEquals(array_values($this->datasets[3]['data'][0]), $result);
    }
}
