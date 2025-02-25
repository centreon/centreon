<?php

declare(strict_types=1);

namespace Tests\Core\Common\Domain\Collection;

use Core\Common\Domain\Collection\StringCollection;
use Core\Common\Domain\Exception\CollectionException;

beforeEach(function () {
    $this->collection = new StringCollection();
});

it('test StringCollection : clear collection', function () {
    $this->collection->clear();
    expect($this->collection->length())->toBe(0);
});

it('test StringCollection : get length', function () {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->length())->toBe(2);
});

it('test StringCollection : if empty must be return true', function () {
    expect($this->collection->isEmpty())->toBeTrue();
});

it('test StringCollection : must to be return false if collection is empty', function () {
    $this->collection->add(1, 'foo');
    expect($this->collection->isEmpty())->toBeFalse();
});

it('test StringCollection : return true if item exists', function () {
    $item = 'foo';
    $this->collection->add(1, $item);
    expect($this->collection->contains($item))->toBeTrue();
});

it('test StringCollection : return true if item does not exist', function () {
    $item = 'foo';
    expect($this->collection->contains($item))->toBeFalse();
});

it('test StringCollection : if the key exists, item will be returned', function () {
    $item = 'foo';
    $this->collection->add(1, $item);
    expect($this->collection->get(1))->toBe($item);
});

it('test StringCollection : if the key does not exist, a CollectionException will be thrown', function () {
    $this->collection->get(3);
})->throws(CollectionException::class);

it('test StringCollection : if the key exists, return true', function () {
    $this->collection->add(1, 'foo');
    expect($this->collection->has(1))->toBeTrue();
});

it('test StringCollection : if the key does not exist, return false', function () {
    expect($this->collection->has(3))->toBeFalse();
});

it('test StringCollection : return the keys of the collection as an array', function () {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->keys())->toEqual([1, 2]);
});

it('test StringCollection : test merge', function () {
    $collection1 = new StringCollection();
    $collection1->add(3, 'foo');
    $collection1->add(4, 'bar');

    $collection2 = new StringCollection();
    $collection2->add(5, 'foo');
    $collection2->add(6, 'bar');

    $this->collection->mergeWith($collection1, $collection2);
    expect($this->collection->length())->toBe(4)
        ->and($this->collection->keys())->toEqual([3, 4, 5, 6]);
});

it('test StringCollection : return the array of items', function () {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->all())->toBeArray()->toHaveCount(2);
});

it('test StringCollection : add an item at the collection (add)', function () {
    $this->collection->add(1, 'foo');
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('test StringCollection : the item must not to be added, a CollectionException should be thrown (add)', function () {
    $this->collection->add(1, 'foo');
    $this->collection->add(1, 'foo');
})->throws(CollectionException::class);

it(
    'test StringCollection : the item must not to be added, a CollectionException should be thrown if the item is not good class (add)',
    function () {
        $this->collection->add(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('test StringCollection : the item must to be added (put)', function () {
    $this->collection->put(1, 'foo');
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('test StringCollection : the item must not to be added with put because the key exists (put)', function () {
    $this->collection->add(1, 'foo');
    $this->collection->put(1, 'foo');
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it(
    'test StringCollection : the item must not to be added, a CollectionException should be thrown if the item is not a string (put)',
    function () {
        $this->collection->put(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('test StringCollection : must to remove an item and return true', function () {
    $this->collection->add(1, 'foo');
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeTrue();
});

it('test StringCollection : must not to remove an item and return false', function () {
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeFalse();
});

it('test StringCollection : return an iterator', function () {
    $this->collection->add(1, 'foo');
    $items = iterator_to_array($this->collection->getIterator());
    expect($items)->toEqual($this->collection->all());
});

it('test StringCollection : json serialize', function () {
    $this->collection->add('key1', 'foo');
    $this->collection->add('key2', 'bar');
    expect($this->collection->jsonSerialize())->toEqual(['key1' => 'foo', 'key2' => 'bar']);
});

it('test StringCollection : json encode', function () {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->toJson())->toBe(json_encode($this->collection->jsonSerialize()));
});
