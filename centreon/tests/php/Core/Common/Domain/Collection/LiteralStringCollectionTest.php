<?php

declare(strict_types=1);

namespace Tests\Core\Common\Domain\Collection;

use Core\Common\Domain\Collection\LiteralStringCollection;
use Core\Common\Domain\Exception\CollectionException;
use Core\Common\Domain\ValueObject\LiteralString;

beforeEach(function () {
    $this->collection = new LiteralStringCollection();
});

it('test LiteralStringCollection : clear collection', function () {
    $this->collection->clear();
    expect($this->collection->length())->toBe(0);
});

it('test LiteralStringCollection : get length', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $this->collection->add(2, new LiteralString('bar'));
    expect($this->collection->length())->toBe(2);
});

it('test LiteralStringCollection : if empty must be return true', function () {
    expect($this->collection->isEmpty())->toBeTrue();
});

it('test LiteralStringCollection : must to be return false if collection is empty', function () {
    $this->collection->add(1, new LiteralString('foo'));
    expect($this->collection->isEmpty())->toBeFalse();
});

it('test LiteralStringCollection : return true if item exists', function () {
    $item = new LiteralString('foo');
    $this->collection->add(1, $item);
    expect($this->collection->contains($item))->toBeTrue();
});

it('test LiteralStringCollection : return true if item does not exist', function () {
    $item = new LiteralString('foo');
    expect($this->collection->contains($item))->toBeFalse();
});

it('test LiteralStringCollection : if the key exists, item will be returned', function () {
    $item = new LiteralString('foo');
    $this->collection->add(1, $item);
    expect($this->collection->get(1))->toBe($item);
});

it('test LiteralStringCollection : if the key does not exist, a CollectionException will be thrown', function () {
    $this->collection->get(3);
})->throws(CollectionException::class);

it('test LiteralStringCollection : if the key exists, return true', function () {
    $this->collection->add(1, new LiteralString('foo'));
    expect($this->collection->has(1))->toBeTrue();
});

it('test LiteralStringCollection : if the key does not exist, return false', function () {
    expect($this->collection->has(3))->toBeFalse();
});

it('test LiteralStringCollection : return the keys of the collection as an array', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $this->collection->add(2, new LiteralString('bar'));
    expect($this->collection->keys())->toEqual([1, 2]);
});

it('test LiteralStringCollection : test merge', function () {
    $collection1 = new LiteralStringCollection();
    $collection1->add(3, new LiteralString('foo'));
    $collection1->add(4, new LiteralString('bar'));

    $collection2 = new LiteralStringCollection();
    $collection2->add(5, new LiteralString('foo'));
    $collection2->add(6, new LiteralString('bar'));

    $this->collection->mergeWith($collection1, $collection2);
    expect($this->collection->length())->toBe(4)
        ->and($this->collection->keys())->toEqual([3, 4, 5, 6]);
});

it('test LiteralStringCollection : return the array of items', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $this->collection->add(2, new LiteralString('bar'));
    expect($this->collection->all())->toBeArray()->toHaveCount(2);
});

it('test LiteralStringCollection : add an item at the collection (add)', function () {
    $this->collection->add(1, new LiteralString('foo'));
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('test LiteralStringCollection : the item must not to be added, a CollectionException should be thrown (add)', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $this->collection->add(1, new LiteralString('bar'));
})->throws(CollectionException::class);

it(
    'test LiteralStringCollection : the item must not to be added, a CollectionException should be thrown if the item is not good class (add)',
    function () {
        $this->collection->add(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('test LiteralStringCollection : the item must to be added (put)', function () {
    $this->collection->put(1, new LiteralString('foo'));
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('test LiteralStringCollection : the item must not to be added with put because the key exists (put)', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $this->collection->put(1, new LiteralString('foo'));
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it(
    'test LiteralStringCollection : the item must not to be added, a CollectionException should be thrown if the item is not the good class (put)',
    function () {
        $this->collection->put(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('test LiteralStringCollection : must to remove an item and return true', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeTrue();
});

it('test LiteralStringCollection : must not to remove an item and return false', function () {
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeFalse();
});

it('test LiteralStringCollection : return an iterator', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $items = iterator_to_array($this->collection->getIterator());
    expect($items)->toEqual($this->collection->all());
});

it('test LiteralStringCollection : json serialize', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $this->collection->add(2, new LiteralString('bar'));
    expect($this->collection->jsonSerialize())->toBe([1 => 'foo', 2 => 'bar']);
});

it('test LiteralStringCollection : json encode', function () {
    $this->collection->add(1, new LiteralString('foo'));
    $this->collection->add(2, new LiteralString('bar'));
    expect($this->collection->toJson())->toBe(json_encode($this->collection->jsonSerialize()));
});
