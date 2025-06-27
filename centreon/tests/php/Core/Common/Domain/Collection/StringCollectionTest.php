<?php

declare(strict_types=1);

namespace Tests\Core\Common\Domain\Collection;

use Core\Common\Domain\Collection\StringCollection;
use Core\Common\Domain\Exception\CollectionException;

beforeEach(function (): void {
    $this->collection = new StringCollection();
});

it('clear collection', function (): void {
    $this->collection->clear();
    expect($this->collection->length())->toBe(0);
});

it('get length', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->length())->toBe(2);
});

it('if empty must be return true', function (): void {
    expect($this->collection->isEmpty())->toBeTrue();
});

it('must to be return false if collection is empty', function (): void {
    $this->collection->add(1, 'foo');
    expect($this->collection->isEmpty())->toBeFalse();
});

it('return true if item exists', function (): void {
    $item = 'foo';
    $this->collection->add(1, $item);
    expect($this->collection->contains($item))->toBeTrue();
});

it('return false if item does not exist', function (): void {
    $item = 'foo';
    expect($this->collection->contains($item))->toBeFalse();
});

it('if the key exists, item will be returned', function (): void {
    $item = 'foo';
    $this->collection->add(1, $item);
    expect($this->collection->get(1))->toBe($item);
});

it('if the key does not exist, a CollectionException will be thrown', function (): void {
    $this->collection->get(3);
})->throws(CollectionException::class);

it('if the key exists, return true', function (): void {
    $this->collection->add(1, 'foo');
    expect($this->collection->has(1))->toBeTrue();
});

it('if the key does not exist, return false', function (): void {
    expect($this->collection->has(3))->toBeFalse();
});

it('return the keys of the collection as an array', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->keys())->toEqual([1, 2]);
});

it('return position of an item of a collection', function (): void {
    $item = 'foo';
    $this->collection->add('bar', $item);
    expect($this->collection->indexOf($item))->toBe(0);
});

it('sort a collection by values', function (): void {
    $item = 'foo';
    $item2 = 'bar';

    $orderedArray = [$item2, $item];

    $this->collection->add(1, $item);
    $this->collection->add(2,  $item2);

    $this->collection->sortByValues(function($a, $b) use ($orderedArray){
        return array_search($a, $orderedArray) <=> array_search($b, $orderedArray);
    });
    expect($this->collection->get(0))->toBe($item2)
        ->and($this->collection->get(1))->toBe($item);
});

it('sort a collection by keys', function (): void {
    $item = 'foo';
    $item2 = 'bar';

    $orderedArray = ['b' => 1, 'a' => 2];

    $this->collection->add('a', $item);
    $this->collection->add('b',  $item2);

    $this->collection->sortByKeys(function($a, $b) use ($orderedArray){
        $indexA = $orderedArray[$a];
        $indexB = $orderedArray[$b];
        return $indexA <=> $indexB;
    });
    expect($this->collection->indexOf($item))->toBe(1)
        ->and($this->collection->indexOf($item2))->toBe(0);
});

it('test filter on values', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');

    $filtered = $this->collection->filterOnValue(fn($value) => $value === 'foo');
    expect($filtered->length())->toBe(1)
        ->and($filtered->keys())->toEqual([1])
        ->and($filtered->get(1))->toBe('foo');
});

it('test filter on keys', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');

    $filtered = $this->collection->filterOnKey(fn($key) => $key === 1);
    expect($filtered->length())->toBe(1)
        ->and($filtered->keys())->toEqual([1])
        ->and($filtered->get(1))->toBe('foo');
});

it('test filter on values and keys', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');

    $filtered = $this->collection->filterOnValueKey(fn($value, $key) => $value === 'foo' && $key === 1);
    expect($filtered->length())->toBe(1)
        ->and($filtered->keys())->toEqual([1])
        ->and($filtered->get(1))->toBe('foo');
});

it('test merge string collections', function (): void {
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

it('return the array of items', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->toArray())->toBeArray()->toHaveCount(2);
});

it('add an item at the collection (add)', function (): void {
    $this->collection->add(1, 'foo');
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('the item must not to be added, a CollectionException should be thrown (add)', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(1, 'foo');
})->throws(CollectionException::class);

it(
    'the item must not to be added, a CollectionException should be thrown if the item is not good class (add)',
    function (): void {
        $this->collection->add(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('the item must to be added (put)', function (): void {
    $this->collection->put(1, 'foo');
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it('the item must not to be added with put because the key exists (put)', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->put(1, 'foo');
    expect($this->collection->length())->toBe(1)
        ->and($this->collection->keys())->toEqual([1]);
});

it(
    'the item must not to be added, a CollectionException should be thrown if the item is not a string (put)',
    function (): void {
        $this->collection->put(1, new \DateTime());
    }
)->throws(CollectionException::class);

it('must to remove an item and return true', function (): void {
    $this->collection->add(1, 'foo');
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeTrue();
});

it('must not to remove an item and return false', function (): void {
    $result = $this->collection->remove(1);
    expect($this->collection->length())->toBe(0)
        ->and($this->collection->keys())->toEqual([])
        ->and($result)->toBeFalse();
});

it('return an iterator', function (): void {
    $this->collection->add(1, 'foo');
    $items = iterator_to_array($this->collection->getIterator());
    expect($items)->toEqual($this->collection->toArray());
});

it('json serialize', function (): void {
    $this->collection->add('key1', 'foo');
    $this->collection->add('key2', 'bar');
    expect($this->collection->jsonSerialize())->toEqual(['key1' => 'foo', 'key2' => 'bar']);
});

it('json encode', function (): void {
    $this->collection->add(1, 'foo');
    $this->collection->add(2, 'bar');
    expect($this->collection->toJson())->toBe(json_encode($this->collection->jsonSerialize()));
});
