<?php

declare(strict_types=1);

namespace Tests\Core\Common\Domain\Collection;

use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\ValueObject\LiteralString;

beforeEach(function () {
    $this->collection = new ObjectCollectionStub();
});

it('clear collection', function () {
    $this->collection->clear();
    expect($this->collection->length())->toBe(0);
});

it('get length', function () {
    $this->collection->add(1, new \stdClass());
    $this->collection->add(2, new \stdClass());
    expect($this->collection->length())->toBe(2);
});

it('if empty must be return true', function () {
    expect($this->collection->isEmpty())->toBeTrue();
});

it('must to be return false if collection is empty', function () {
    $this->collection->add(1, new \stdClass());
    expect($this->collection->isEmpty())->toBeFalse();
});

it('return true if item exists', function () {
    $item = new \stdClass();
    $this->collection->add(1, $item);
    expect($this->collection->contains($item))->toBeTrue();
});

it('return false if item does not exist', function () {
    $item = new \stdClass();
    expect($this->collection->contains($item))->toBeFalse();
});

it('if the key exists, item will be returned', function () {
    $item = new \stdClass();
    $this->collection->add(1, $item);
    expect($this->collection->get(1))->toBe($item);
});

it('if the key does not exist, a CollectionException will be thrown', function () {
    $this->collection->get(3);
})->throws(CollectionException::class);

it('if the key exists, return true', function () {
    $this->collection->add(1, new \stdClass());
    expect($this->collection->has(1))->toBeTrue();
});

it('if the key does not exist, return false', function () {
    expect($this->collection->has(3))->toBeFalse();
});

it('return the keys of the collection as an array', function () {
    $this->collection->add(1, new \stdClass());
    $this->collection->add(2, new \stdClass());
    expect($this->collection->keys())->toEqual([1, 2]);
});

it('return position of an item of a collection', function () {
    $item = new \stdClass();
    $this->collection->add('foo', $item);
    expect($this->collection->indexOf($item))->toBe(0);
});

it('sort a collection by values', function () {
    $class1 = new \stdClass();
    $class1->value = 'foo';
    $class2 = new \stdClass();
    $class2->value = 'bar';

    $orderedArray = [$class2, $class1];

    $this->collection->add(1, $class1);
    $this->collection->add(2,  $class2);

    $this->collection->sortByValues(function($a, $b) use ($orderedArray){
        return array_search($a, $orderedArray) <=> array_search($b, $orderedArray);
    });
    expect($this->collection->get(0))->toBe($class2)
        ->and($this->collection->get(1))->toBe($class1);
});

it('sort a collection by keys', function () {
    $class1 = new \stdClass();
    $class1->value = 'foo';
    $class2 = new \stdClass();
    $class2->value = 'bar';

    $orderedArray = ['b' => 1, 'a' => 2];

    $this->collection->add('a', $class1);
    $this->collection->add('b',  $class2);

    $this->collection->sortByKeys(function($a, $b) use ($orderedArray){
        $indexA = $orderedArray[$a];
        $indexB = $orderedArray[$b];
        return $indexA <=> $indexB;
    });
    expect($this->collection->indexOf($class1))->toBe(1)
        ->and($this->collection->indexOf($class2))->toBe(0);
});

it('test filter on values', function () {
    $class1 = new \stdClass();
    $class1->value = 'foo';
    $class2 = new \stdClass();
    $class2->value = 'bar';
    $this->collection->add(1, $class1);
    $this->collection->add(2,  $class2);

    $filtered = $this->collection->filterOnValue(fn($class) => $class->value === 'foo');
    expect($filtered->length())->toBe(1)
        ->and($filtered->get(1))->toBe($class1);
});

it('test filter on keys', function () {
    $class1 = new \stdClass();
    $class1->value = 'foo';
    $class2 = new \stdClass();
    $class2->value = 'bar';
    $this->collection->add(1, $class1);
    $this->collection->add(2,  $class2);

    $filtered = $this->collection->filterOnKey(fn($key) => $key === 1);
    expect($filtered->length())->toBe(1)
        ->and($filtered->get(1))->toBe($class1);
});

it('test filter on values and keys', function () {
    $class1 = new \stdClass();
    $class1->value = 'foo';
    $class2 = new \stdClass();
    $class2->value = 'bar';
    $this->collection->add(1, $class1);
    $this->collection->add(2,  $class2);

    $filtered = $this->collection->filterOnValueKey(fn($class, $key) => $class->value === 'foo' && $key === 1);
    expect($filtered->length())->toBe(1)
        ->and($filtered->get(1))->toBe($class1);
});

it('test merge object collections', function () {
    $collection1 = new ObjectCollectionStub();
    $collection1->add(3, new \stdClass());
    $collection1->add(4, new \stdClass());

    $collection2 = new ObjectCollectionStub();
    $collection2->add(5, new \stdClass());
    $collection2->add(6, new \stdClass());

    $this->collection->mergeWith($collection1, $collection2);
    expect($this->collection->length())->toBe(4)
        ->and($this->collection->keys())->toEqual([3, 4, 5, 6]);
});

it('return the array of items', function () {
    $this->collection->add(1, new \stdClass());
    $this->collection->add(2, new \stdClass());
    expect($this->collection->toArray())->toBeArray()->toHaveCount(2);
});

it('add an item at the collection (add)', function () {
    $this->collection->add(1, new \stdClass());
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('the item must not to be added, a CollectionException should be thrown (add)', function () {
    $this->collection->add(1, new \stdClass());
    $this->collection->add(1, new \stdClass());
})->throws(CollectionException::class);

it(
    'the item must not to be added, a CollectionException should be thrown if the item is not good class (add)',
    function () {
        $this->collection->add(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('the item must to be added (put)', function () {
    $this->collection->put(1, new \stdClass());
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('the item must not to be added with put because the key exists (put)', function () {
    $this->collection->add(1, new \stdClass());
    $this->collection->put(1, new \stdClass());
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it(
    'the item must not to be added, a CollectionException should be thrown if the item is not the good class (put)',
    function () {
        $this->collection->put(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('must to remove an item and return true', function () {
    $this->collection->add(1, new \stdClass());
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeTrue();
});

it('must not to remove an item and return false', function () {
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeFalse();
});

it('return an iterator', function () {
    $this->collection->add(1, new \stdClass());
    $items = iterator_to_array($this->collection->getIterator());
    expect($items)->toEqual($this->collection->toArray());
});

it('json serialize', function () {
    $this->collection->add(1, new \stdClass());
    $this->collection->add(2, new \stdClass());
    expect($this->collection->jsonSerialize())->toBe([
        1 => get_object_vars(new \stdClass()),
        2 => get_object_vars(new \stdClass())
    ]);
});

it('json encode', function () {
    $this->collection->add(1, new \stdClass());
    $this->collection->add(2, new \stdClass());
    expect($this->collection->toJson())->toBe(json_encode($this->collection->jsonSerialize()));
});
