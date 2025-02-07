<?php

declare(strict_types=1);

namespace FlowTest\Collection;

use Flow\Domain\Common\Collection\Collection;
use Flow\Domain\Common\Exception\CollectionException;
use Flow\Infrastructure\Collection\GroupCollection;
use PHPUnit\Framework\TestCase;

/**
 * Class
 *
 * @class   CollectionTest
 * @package FlowTest\Collection
 */
class CollectionTest extends TestCase
{
    /**
     * @var Collection
     */
    private $collection;

    public function setUp(): void
    {
        $this->collection = $this->makeConcreteCollection();
    }

    /**
     * Doit vider la collection
     *
     * @test
     */
    public function clear(): void
    {
        $this->collection->clear();
        self::assertEquals(0, $this->collection->length());
    }

    /**
     * Doit retourner le nombre d'élément dans la collection
     *
     * @test
     */
    public function length(): void
    {
        self::assertEquals(2, $this->collection->length());
    }

    /**
     * Doit retourner true si la collection est vide
     *
     * @test
     */
    public function testIsEmpty(): void
    {
        self::assertTrue($this->collection->clear()->isEmpty());
    }

    /**
     * Doit retourner false si la collection est non vide
     *
     * @test
     */
    public function testIsNotEmpty(): void
    {
        self::assertFalse($this->collection->isEmpty());
    }

    /**
     * renvoie true si l'item existe dans la collection
     *
     * @test
     */
    public function contains(): void
    {
        self::assertTrue($this->collection->contains($this->getExistingItem()));
    }

    /**
     * renvoie false si l'item n'existe pas dans la collection
     *
     * @test
     */
    public function notContains(): void
    {
        self::assertFalse($this->collection->contains($this->getUnexistingItem()));
    }

    /**
     * Si la clé existe, doit renvoyer l'item en question
     *
     * @test
     */
    public function getItemExist(): void
    {
        self::assertEquals($this->getExistingItem(), $this->collection->get(1));
    }

    /**
     * Si la clé n'existe pas doit lancer une exception
     *
     * @test
     */
    public function getItemNotExist(): void
    {
        self::expectException(CollectionException::class);
        $this->collection->get(3);
    }

    /**
     * Doit renvoyer true si la clé existe dans la collection
     *
     * @test
     */
    public function has(): void
    {
        self::assertTrue($this->collection->has(1));
    }

    /**
     * Doit renvoyer false si la clé n'existe pas dans la collection
     *
     * @test
     */
    public function hasNot(): void
    {
        self::assertFalse($this->collection->has(3));
    }

    /**
     * Doit renvoyer sous forme de tableau les clés de la collection
     *
     * @test
     */
    public function keys(): void
    {
        $keys = $this->collection->keys();
        self::assertEquals([1, 2], $keys);
    }

    /**
     * Vérifie que la collection contient bien les items initiaux et ceux des autres collections
     *
     * @test
     */
    public function mergeWith(): void
    {
        $newCollections = $this->newCollectionsForMerge();
        $this->collection->mergeWith($newCollections[0], $newCollections[1]);
        self::assertEquals(6, $this->collection->length());
        self::assertEquals([1, 2, 3, 4, 5, 6], $this->collection->keys());
    }

    /**
     * Doit renvoyer le tableau d'items de la collection
     *
     * @test
     */
    public function all(): void
    {
        self::assertCount(2, $this->collection->all());
        self::assertIsArray($this->collection->all());
    }

    /**
     * Doit ajouter un nouvel item à la collection
     *
     * @test
     */
    public function add(): void
    {
        $this->collection->add(3, $this->getUnexistingItem());
        self::assertEquals(3, $this->collection->length());
        self::assertEquals([1, 2, 3], $this->collection->keys());
    }

    /**
     * ne doit pas ajouter l'item et doit renvoyer une exception
     *
     * @test
     */
    public function addExistingKey(): void
    {
        self::expectException(CollectionException::class);
        $this->collection->add(2, $this->getExistingItem());
    }

    /**
     * ne doit pas ajouter l'item et doit renvoyer une exception
     * si l'item n'est pas une instance de la classe attendue
     *
     * @test
     */
    public function addOtherObject(): void
    {
        self::expectException(CollectionException::class);
        $this->collection->add(3, $this->getItemOtherObject());
    }

    /**
     * Doit ajouter un nouvel item à la collection si l'item n'existe pas
     *
     * @test
     */
    public function put(): void
    {
        $this->collection->put(3, $this->getUnexistingItem());
        self::assertEquals(3, $this->collection->length());
        self::assertEquals([1, 2, 3], $this->collection->keys());
    }

    /**
     * Doit remplacer un item si la clé existe déjà
     *
     * @test
     */
    public function putExistingItem(): void
    {
        $this->collection->put(2, $this->getExistingItem());
        self::assertEquals(2, $this->collection->length());
        self::assertEquals([1, 2], $this->collection->keys());
    }

    /**
     * ne doit pas ajouter l'item et doit renvoyer une exception
     * si l'item n'est pas une instance de la classe attendue
     *
     * @test
     */
    public function putOtherObject(): void
    {
        self::expectException(CollectionException::class);
        $this->collection->put(3, $this->getItemOtherObject());
    }

    /**
     * Doit retirer un item de la collection et renvoyer true
     *
     * @test
     */
    public function remove(): void
    {
        $result = $this->collection->remove(1);
        self::assertEquals(1, $this->collection->length());
        self::assertEquals([2], $this->collection->keys());
        self::assertTrue($result);
    }

    /**
     * Ne doit rien retirer et renvoyer false
     *
     * @test
     */
    public function removeUnexistingItem(): void
    {
        $result = $this->collection->remove(3);
        self::assertEquals(2, $this->collection->length());
        self::assertEquals([1, 2], $this->collection->keys());
        self::assertFalse($result);
    }

    /**
     * S'assure que getIterator renvoie bien un générateur qui génère les items de la collection
     *
     * @test
     */
    public function getIterator(): void
    {
        $items = iterator_to_array($this->collection->getIterator());
        self::assertEquals($this->collection->all(), $items);
    }

    /**
     * La classe Collection étant abstraite on utilise une classe enfant pour les tests
     *
     * @return Collection
     * @throws CollectionException
     */
    private function makeConcreteCollection(): Collection
    {
        $item1 = new \E5Group();
        $item1->id_group = 1;
        $item1->name_group = "Group 1";

        $item2 = new \E5Group();
        $item2->id_group = 1;
        $item2->name_group = "Group 2";

        return new GroupCollection([1 => $item1, 2 => $item2]);
    }

    /**
     * @return Collection[]
     * @throws CollectionException
     */
    private function newCollectionsForMerge(): array
    {
        $item3 = new \E5Group();
        $item3->id_group = 3;
        $item3->name_group = "Group 3";

        $item4 = new \E5Group();
        $item4->id_group = 4;
        $item4->name_group = "Group 4";

        $item5 = new \E5Group();
        $item5->id_group = 5;
        $item5->name_group = "Group 5";

        $item6 = new \E5Group();
        $item6->id_group = 6;
        $item6->name_group = "Group 6";
        return [
            new GroupCollection([3 => $item3, 4 => $item4]),
            new GroupCollection([5 => $item5, 6 => $item6])
        ];
    }

    /**
     * @return object
     */
    private function getExistingItem(): object
    {
        $group = new \E5Group();
        $group->id_group = 1;
        $group->name_group = "Group 1";
        return $group;
    }

    /**
     * @return object
     */
    private function getUnexistingItem(): object
    {
        $group = new \E5Group();
        $group->id_group = 3;
        $group->name_group = "Group 3";
        return $group;
    }

    /**
     * @return object
     */
    private function getItemOtherObject(): object
    {
        $user = new \E5User();
        return $user;
    }
}
