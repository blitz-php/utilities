<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\Iterable;

use ArrayIterator;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Contracts\Support\CanBeEscapedWhenCastToString;
use BlitzPHP\Contracts\Support\Enumerable;
use BlitzPHP\Traits\EnumeratesValues;
use BlitzPHP\Traits\Macroable;
use BlitzPHP\Utilities\Helpers;
use Closure;
use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use Generator;
use InvalidArgumentException;
use IteratorAggregate;
use stdClass;
use Traversable;

/**
 * @template TKey of array-key
 *
 * @template-covariant TValue
 *
 * @implements \BlitzPHP\Contracts\Support\Enumerable<TKey, TValue>
 *
 * @credit <a href="https://laravel.com">Laravel - Illuminate\Support\LazyCollection</a>
 */
class LazyCollection implements CanBeEscapedWhenCastToString, Enumerable
{
    /**
     * @use \BlitzPHP\Traits\EnumeratesValues<TKey, TValue>
     */
    use EnumeratesValues;

    use Macroable;

    /**
     * The source from which to generate items.
     *
     * @var array<TKey, TValue>|(Closure(): Generator<TKey, TValue, mixed, void>)|static
     */
    public $source;

    /**
     * Create a new lazy collection instance.
     *
     * @param array<TKey, TValue>|Arrayable<TKey, TValue>|(Closure(): Generator<TKey, TValue, mixed, void>)|iterable<TKey, TValue>|self<TKey, TValue>|null $source
     */
    public function __construct($source = null)
    {
        if ($source instanceof Closure || $source instanceof self) {
            $this->source = $source;
        } elseif (null === $source) {
            $this->source = static::empty();
        } elseif ($source instanceof Generator) {
            throw new InvalidArgumentException(
                'Les générateurs ne doivent pas être transmis directement à LazyCollection. Au lieu de cela, passez une fonction de générateur.'
            );
        } else {
            $this->source = $this->getArrayableItems($source);
        }
    }

    /**
     *{@inheritDoc}
     *
     * @template TMakeKey of array-key
     * @template TMakeValue
     *
     * @param array<TMakeKey, TMakeValue>|Arrayable<TMakeKey, TMakeValue>|(Closure(): Generator<TMakeKey, TMakeValue, mixed, void>)|iterable<TMakeKey, TMakeValue>|self<TMakeKey, TMakeValue>|null $items
     *
     * @return static<TMakeKey, TMakeValue>
     */
    public static function make($items = []): static
    {
        return new static($items);
    }

    /**
     * {@inheritDoc}
     *
     * @return static<int, int>
     */
    public static function range($from, $to, $step = 1): static
    {
        if ($step === 0) {
            throw new InvalidArgumentException('Step value cannot be zero.');
        }

        return new static(static function () use ($from, $to, $step) {
            if ($from <= $to) {
                for (; $from <= $to; $from += abs($step)) {
                    yield $from;
                }
            } else {
                for (; $from >= $to; $from -= abs(($step))) {
                    yield $from;
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        if (is_array($this->source)) {
            return $this->source;
        }

        return iterator_to_array($this->getIterator());
    }

    /**
     * charge tous les éléments dans une nouvelle collection paresseuse soutenue par un tableau.
     *
     * @return static<TKey, TValue>
     */
    public function eager(): static
    {
        return new static($this->all());
    }

    /**
     * Cachez les valeurs telles qu'elles sont énumérées.
     *
     * @return static<TKey, TValue>
     */
    public function remember(): static
    {
        $iterator = $this->getIterator();

        $iteratorIndex = 0;

        $cache = [];

        return new static(static function () use ($iterator, &$iteratorIndex, &$cache) {
            for ($index = 0; true; $index++) {
                if (array_key_exists($index, $cache)) {
                    yield $cache[$index][0] => $cache[$index][1];

                    continue;
                }

                if ($iteratorIndex < $index) {
                    $iterator->next();

                    $iteratorIndex++;
                }

                if (! $iterator->valid()) {
                    break;
                }

                $cache[$index] = [$iterator->key(), $iterator->current()];

                yield $cache[$index][0] => $cache[$index][1];
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function median($key = null)
    {
        return $this->collect()->median($key);
    }

    /**
     * {@inheritDoc}
     */
    public function mode($key = null): ?array
    {
        return $this->collect()->mode($key);
    }

    /**
     * {@inheritDoc}
     */
    public function collapse(): static
    {
        return new static(function () {
            foreach ($this as $values) {
                if (is_array($values) || $values instanceof Enumerable) {
                    foreach ($values as $value) {
                        yield $value;
                    }
                }
            }
        });
    }

    /**
     * Collapse the collection of items into a single array while preserving its keys.
     *
     * @return static<mixed, mixed>
     */
    public function collapseWithKeys(): static
    {
        return new static(function () {
            foreach ($this as $values) {
                if (is_array($values) || $values instanceof Enumerable) {
                    foreach ($values as $key => $value) {
                        yield $key => $value;
                    }
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function contains($key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1 && $this->useAsCallable($key)) {
            $placeholder = new stdClass();

            /** @var callable $key */
            return $this->first($key, $placeholder) !== $placeholder;
        }

        if (func_num_args() === 1) {
            $needle = $key;

            foreach ($this as $value) {
                if ($value === $needle) {
                    return true;
                }
            }

            return false;
        }

        return $this->contains($this->operatorForWhere(...func_get_args()));
    }

    /**
     * {@inheritDoc}
     */
    public function containsStrict($key, mixed $value = null): bool
    {
        if (func_num_args() === 2) {
            return $this->contains(static fn ($item) => Helpers::dataGet($item, $key) === $value);
        }

        if ($this->useAsCallable($key)) {
            return null !== $this->first($key);
        }

        foreach ($this as $item) {
            if ($item === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function doesntContain(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        return ! $this->contains(...func_get_args());
    }

    /**
     * Determine if an item is not contained in the enumerable, using strict comparison.
     */
    public function doesntContainStrict(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        return ! $this->containsStrict(...func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function crossJoin(...$arrays)
    {
        return $this->passthru('crossJoin', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function countBy($countBy = null): static
    {
        $countBy = null === $countBy
            ? $this->identity()
            : $this->valueRetriever($countBy);

        return new static(function () use ($countBy) {
            $counts = [];

            foreach ($this as $key => $value) {
                $group = Helpers::enumValue($countBy($value, $key));

                if (empty($counts[$group])) {
                    $counts[$group] = 0;
                }

                $counts[$group]++;
            }

            yield from $counts;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function diff($items): static
    {
        return $this->passthru('diff', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function diffUsing($items, callable $callback): static
    {
        return $this->passthru('diffUsing', func_get_args());
    }

    /**
     *{@inheritDoc}
     */
    public function diffAssoc($items): static
    {
        return $this->passthru('diffAssoc', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function diffAssocUsing($items, callable $callback): static
    {
        return $this->passthru('diffAssocUsing', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function diffKeys($items): static
    {
        return $this->passthru('diffKeys', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function diffKeysUsing($items, callable $callback): static
    {
        return $this->passthru('diffKeysUsing', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function duplicates($callback = null, $strict = false): static
    {
        return $this->passthru('duplicates', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function duplicatesStrict($callback = null): static
    {
        return $this->passthru('duplicatesStrict', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function except($keys): static
    {
        return $this->passthru('except', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function filter(?callable $callback = null): static
    {
        if (null === $callback) {
            $callback = static fn ($value) => (bool) $value;
        }

        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                if ($callback($value, $key)) {
                    yield $key => $value;
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function first(?callable $callback = null, $default = null)
    {
        $iterator = $this->getIterator();

        if (null === $callback) {
            if (! $iterator->valid()) {
                return Helpers::value($default);
            }

            return $iterator->current();
        }

        foreach ($iterator as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return Helpers::value($default);
    }

    /**
     * {@inheritDoc}
     */
    public function flatten(int $depth = INF): static
    {
        $instance = new static(function () use ($depth) {
            foreach ($this as $item) {
                if (! is_array($item) && ! $item instanceof Enumerable) {
                    yield $item;
                } elseif ($depth === 1) {
                    yield from $item;
                } else {
                    yield from (new static($item))->flatten($depth - 1);
                }
            }
        });

        return $instance->values();
    }

    /**
     * {@inheritDoc}
     */
    public function flip(): static
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $value => $key;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function get(int|string|null $key, mixed $default = null): mixed
    {
        if (null === $key) {
            return null;
        }

        foreach ($this as $outerKey => $outerValue) {
            if ($outerKey === $key) {
                return $outerValue;
            }
        }

        return Helpers::value($default);
    }

    /**
     * {@inheritDoc}
     */
    public function groupBy($groupBy, bool $preserveKeys = false): static
    {
        return $this->passthru('groupBy', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function keyBy($keyBy): static
    {
        return new static(function () use ($keyBy) {
            $keyBy = $this->valueRetriever($keyBy);

            foreach ($this as $key => $item) {
                $resolvedKey = Helpers::enumValue($keyBy($item, $key));

                if (is_object($resolvedKey)) {
                    $resolvedKey = (string) $resolvedKey;
                }

                yield $resolvedKey => $item;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function has(mixed $key): bool
    {
        $keys  = array_flip(is_array($key) ? $key : func_get_args());
        $count = count($keys);

        foreach ($this as $key => $value) {
            if (array_key_exists($key, $keys) && --$count === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function hasAny(mixed $key): bool
    {
        $keys = array_flip(is_array($key) ? $key : func_get_args());

        foreach ($this as $key => $value) {
            if (array_key_exists($key, $keys)) {
                return true;
            }
        }

        return false;
    }

    /**
     * {@inheritDoc}
     */
    public function implode(callable|string $value, ?string $glue = null): string
    {
        return $this->collect()->implode(...func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function intersect($items): static
    {
        return $this->passthru('intersect', func_get_args());
    }

    /**
     * Intersecter la collection avec les éléments donnés, en utilisant le callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int                            $callback
     */
    public function intersectUsing($items, callable $callback): static
    {
        return $this->passthru('intersectUsing', func_get_args());
    }

    /**
     * Croisez la collection avec les éléments donnés avec une vérification d'index supplémentaire.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     */
    public function intersectAssoc($items): static
    {
        return $this->passthru('intersectAssoc', func_get_args());
    }

    /**
     * Intersect the collection with the given items with additional index check, using the callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int
     */
    public function intersectAssocUsing($items, callable $callback): static
    {
        return $this->passthru('intersectAssocUsing', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function intersectByKeys($items): static
    {
        return $this->passthru('intersectByKeys', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return ! $this->getIterator()->valid();
    }

    /**
     * {@inheritDoc}
     */
    public function containsOneItem(): bool
    {
        return $this->take(2)->count() === 1;
    }

    /**
     * {@inheritDoc}
     */
    public function join(string $glue, string $finalGlue = ''): string
    {
        return $this->collect()->join(...func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function keys(): static
    {
        return new static(function () {
            foreach ($this as $key => $value) {
                yield $key;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function last(?callable $callback = null, $default = null): mixed
    {
        $needle = $placeholder = new stdClass();

        foreach ($this as $key => $value) {
            if (null === $callback || $callback($value, $key)) {
                $needle = $value;
            }
        }

        return $needle === $placeholder ? Helpers::value($default) : $needle;
    }

    /**
     * {@inheritDoc}
     */
    public function pluck($value, $key = null): static
    {
        return new static(function () use ($value, $key) {
            [$value, $key] = $this->explodePluckParameters($value, $key);

            foreach ($this as $item) {
                $itemValue = $value instanceof Closure
                        ? $value($item)
                        : Helpers::dataGet($item, $value);

                if (null === $key) {
                    yield $itemValue;
                } else {
                    $itemKey = $key instanceof Closure
                        ? $key($item)
                        : Helpers::dataGet($item, $key);

                    if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                        $itemKey = (string) $itemKey;
                    }

                    yield $itemKey => $itemValue;
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function map(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield $key => $callback($value, $key);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function mapToDictionary(callable $callback): static
    {
        return $this->passthru('mapToDictionary', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function mapWithKeys(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                yield from $callback($value, $key);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function merge($items): static
    {
        return $this->passthru('merge', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function mergeRecursive($items): static
    {
        return $this->passthru('mergeRecursive', func_get_args());
    }

    /**
     * Multiply the items in the collection by the multiplier.
     */
    public function multiply(int $multiplier): static
    {
        return $this->passthru('multiply', func_get_args());
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, TCombineValue>|(callable(): Generator<array-key, TCombineValue>)|IteratorAggregate<array-key, TCombineValue> $values
     */
    public function combine($values): static
    {
        return new static(function () use ($values) {
            $values = $this->makeIterator($values);

            $errorMessage = 'Both parameters should have an equal number of elements';

            foreach ($this as $key) {
                if (! $values->valid()) {
                    trigger_error($errorMessage, E_USER_WARNING);

                    break;
                }

                yield $key => $values->current();

                $values->next();
            }

            if ($values->valid()) {
                trigger_error($errorMessage, E_USER_WARNING);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function union($items): static
    {
        return $this->passthru('union', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function nth(int $step, int $offset = 0): static
    {
        return new static(function () use ($step, $offset) {
            $position = 0;

            foreach ($this->slice($offset) as $item) {
                if ($position % $step === 0) {
                    yield $item;
                }

                $position++;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function only($keys): static
    {
        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        } elseif (null !== $keys) {
            $keys = is_array($keys) ? $keys : func_get_args();
        }

        return new static(function () use ($keys) {
            if (null === $keys) {
                yield from $this;
            } else {
                $keys = array_flip($keys);

                foreach ($this as $key => $value) {
                    if (array_key_exists($key, $keys)) {
                        yield $key => $value;

                        unset($keys[$key]);

                        if (empty($keys)) {
                            break;
                        }
                    }
                }
            }
        });
    }

    /**
     * Select specific values from the items within the collection.
     *
     * @param array<array-key, TKey>|Enumerable<array-key, TKey>|string $keys
     */
    public function select($keys): static
    {
        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        } elseif (null !== $keys) {
            $keys = is_array($keys) ? $keys : func_get_args();
        }

        return new static(function () use ($keys) {
            if (null === $keys) {
                yield from $this;
            } else {
                foreach ($this as $item) {
                    $result = [];

                    foreach ($keys as $key) {
                        if (Arr::accessible($item) && Arr::exists($item, $key)) {
                            $result[$key] = $item[$key];
                        } elseif (is_object($item) && isset($item->{$key})) {
                            $result[$key] = $item->{$key};
                        }
                    }

                    yield $result;
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function concat(iterable $source): static
    {
        return (new static(function () use ($source) {
            yield from $this;

            yield from $source;
        }))->values();
    }

    /**
     * {@inheritDoc}
     *
     * @param int|null $number
     *
     * @return static<int, TValue>|TValue
     */
    public function random($number = null, bool $preserveKeys = false)
    {
        $result = $this->collect()->random(...func_get_args());

        return null === $number ? $result : new static($result);
    }

    /**
     * {@inheritDoc}
     */
    public function replace($items): static
    {
        return new static(function () use ($items) {
            $items = $this->getArrayableItems($items);

            foreach ($this as $key => $value) {
                if (array_key_exists($key, $items)) {
                    yield $key => $items[$key];

                    unset($items[$key]);
                } else {
                    yield $key => $value;
                }
            }

            foreach ($items as $key => $value) {
                yield $key => $value;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function replaceRecursive($items): static
    {
        return $this->passthru('replaceRecursive', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function reverse(): static
    {
        return $this->passthru('reverse', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function search($value, bool $strict = false)
    {
        /** @var (callable(TValue,TKey): bool) $predicate */
        $predicate = $this->useAsCallable($value)
            ? $value
            : static fn ($item) => $strict ? $item === $value : $item === $value;

        foreach ($this as $key => $item) {
            if ($predicate($item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Get the item before the given item.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     *
     * @return TValue|null
     */
    public function before($value, bool $strict = false)
    {
        $previous = null;

        /** @var (callable(TValue,TKey): bool) $predicate */
        $predicate = $this->useAsCallable($value)
            ? $value
            : static fn ($item) => $strict ? $item === $value : $item === $value;

        foreach ($this as $key => $item) {
            if ($predicate($item, $key)) {
                return $previous;
            }

            $previous = $item;
        }

        return null;
    }

    /**
     * Get the item after the given item.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     *
     * @return TValue|null
     */
    public function after($value, bool $strict = false)
    {
        $found = false;

        /** @var (callable(TValue,TKey): bool) $predicate */
        $predicate = $this->useAsCallable($value)
            ? $value
            : static fn ($item) => $strict ? $item === $value : $item === $value;

        foreach ($this as $key => $item) {
            if ($found) {
                return $item;
            }

            if ($predicate($item, $key)) {
                $found = true;
            }
        }

        return null;
    }

    /**
     * {@inheritDoc}
     */
    public function shuffle(?int $seed = null)
    {
        return $this->passthru('shuffle', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sliding(int $size = 2, int $step = 1): static
    {
        return new static(function () use ($size, $step) {
            $iterator = $this->getIterator();

            $chunk = [];

            while ($iterator->valid()) {
                $chunk[$iterator->key()] = $iterator->current();

                if (count($chunk) === $size) {
                    yield (new static($chunk))->tap(static function () use (&$chunk, $step) {
                        $chunk = array_slice($chunk, $step, null, true);
                    });

                    // If the $step between chunks is bigger than each chunk's $size
                    // we will skip the extra items (which should never be in any
                    // chunk) before we continue to the next chunk in the loop.
                    if ($step > $size) {
                        $skip = $step - $size;

                        for ($i = 0; $i < $skip && $iterator->valid(); $i++) {
                            $iterator->next();
                        }
                    }
                }

                $iterator->next();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function skip(int $count): static
    {
        return new static(function () use ($count) {
            $iterator = $this->getIterator();

            while ($iterator->valid() && $count--) {
                $iterator->next();
            }

            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function skipUntil($value): static
    {
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return $this->skipWhile($this->negate($callback));
    }

    /**
     * {@inheritDoc}
     */
    public function skipWhile($value): static
    {
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return new static(function () use ($callback) {
            $iterator = $this->getIterator();

            while ($iterator->valid() && $callback($iterator->current(), $iterator->key())) {
                $iterator->next();
            }

            while ($iterator->valid()) {
                yield $iterator->key() => $iterator->current();

                $iterator->next();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function slice(int $offset, ?int $length = null): static
    {
        if ($offset < 0 || $length < 0) {
            return $this->passthru('slice', func_get_args());
        }

        $instance = $this->skip($offset);

        return null === $length ? $instance : $instance->take($length);
    }

    /**
     * {@inheritDoc}
     */
    public function split(int $numberOfGroups): static
    {
        return $this->passthru('split', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sole($key = null, mixed $operator = null, mixed $value = null): mixed
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        return $this
            ->unless($filter === null)
            ->filter($filter)
            ->take(2)
            ->collect()
            ->sole();
    }

    /**
     * {@inheritDoc}
     */
    public function firstOrFail($key = null, mixed $operator = null, mixed $value = null): mixed
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        return $this
            ->unless($filter === null)
            ->filter($filter)
            ->take(1)
            ->collect()
            ->firstOrFail();
    }

    /**
     * {@inheritDoc}
     */
    public function chunk(int $size, $preserveKeys = true): static
    {
        if ($size <= 0) {
            return static::empty();
        }

        $add = match ($preserveKeys) {
            true  => static fn (array &$chunk, Traversable $iterator) => $chunk[$iterator->key()] = $iterator->current(),
            false => static fn (array &$chunk, Traversable $iterator) => $chunk[] = $iterator->current(),
        };

        return new static(function () use ($size, $add) {
            $iterator = $this->getIterator();

            while ($iterator->valid()) {
                $chunk = [];

                while (true) {
                    $add($chunk, $iterator);

                    if (count($chunk) < $size) {
                        $iterator->next();

                        if (! $iterator->valid()) {
                            break;
                        }
                    } else {
                        break;
                    }
                }

                yield new static($chunk);

                $iterator->next();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function splitIn(int $numberOfGroups): static
    {
        return $this->chunk((int) ceil($this->count() / $numberOfGroups));
    }

    /**
     * {@inheritDoc}
     *
     * @param callable(TValue, TKey, Collection<TKey, TValue>): bool $callback
     *
     * @return static<int, static<TKey, TValue>>
     */
    public function chunkWhile(callable $callback): static
    {
        return new static(function () use ($callback) {
            $iterator = $this->getIterator();

            $chunk = new Collection();

            if ($iterator->valid()) {
                $chunk[$iterator->key()] = $iterator->current();

                $iterator->next();
            }

            while ($iterator->valid()) {
                if (! $callback($iterator->current(), $iterator->key(), $chunk)) {
                    yield new static($chunk);

                    $chunk = new Collection();
                }

                $chunk[$iterator->key()] = $iterator->current();

                $iterator->next();
            }

            if ($chunk->isNotEmpty()) {
                yield new static($chunk);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function sort($callback = null): static
    {
        return $this->passthru('sort', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortDesc(int $options = SORT_REGULAR): static
    {
        return $this->passthru('sortDesc', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        return $this->passthru('sortBy', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR): static
    {
        return $this->passthru('sortByDesc', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        return $this->passthru('sortKeys', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortKeysDesc(int $options = SORT_REGULAR): static
    {
        return $this->passthru('sortKeysDesc', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function sortKeysUsing(callable $callback): static
    {
        return $this->passthru('sortKeysUsing', func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return new static(function () use ($limit) {
                $limit      = abs($limit);
                $ringBuffer = [];
                $position   = 0;

                foreach ($this as $key => $value) {
                    $ringBuffer[$position] = [$key, $value];
                    $position              = ($position + 1) % $limit;
                }

                for ($i = 0, $end = min($limit, count($ringBuffer)); $i < $end; $i++) {
                    $pointer = ($position + $i) % $limit;

                    yield $ringBuffer[$pointer][0] => $ringBuffer[$pointer][1];
                }
            });
        }

        return new static(function () use ($limit) {
            $iterator = $this->getIterator();

            while ($limit--) {
                if (! $iterator->valid()) {
                    break;
                }

                yield $iterator->key() => $iterator->current();

                if ($limit) {
                    $iterator->next();
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function takeUntil($value): static
    {
        /** @var callable(TValue, TKey): bool $callback */
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return new static(function () use ($callback) {
            foreach ($this as $key => $item) {
                if ($callback($item, $key)) {
                    break;
                }

                yield $key => $item;
            }
        });
    }

    /**
     * Prenez les éléments de la collection jusqu'à un moment donné.
     *
     * @param callable(TValue|null, TKey|null): mixed|null $callback
     *
     * @return static<TKey, TValue>
     */
    public function takeUntilTimeout(DateTimeInterface $timeout, ?callable $callback = null): static
    {
        $timeout = $timeout->getTimestamp();

        return new static(function () use ($timeout, $callback) {
            if ($this->now() >= $timeout) {
                if ($callback) {
                    $callback(null, null);
                }

                return;
            }

            foreach ($this as $key => $value) {
                yield $key => $value;

                if ($this->now() >= $timeout) {
                    if ($callback) {
                        $callback($value, $key);
                    }

                    break;
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function takeWhile($value): static
    {
        /** @var callable(TValue, TKey): bool $callback */
        $callback = $this->useAsCallable($value) ? $value : $this->equality($value);

        return $this->takeUntil(static fn ($item, $key) => ! $callback($item, $key));
    }

    /**
     * Pass each item in the collection to the given callback, lazily.
     *
     * @param callable(TValue, TKey): mixed $callback
     *
     * @return static<TKey, TValue>
     */
    public function tapEach(callable $callback): static
    {
        return new static(function () use ($callback) {
            foreach ($this as $key => $value) {
                $callback($value, $key);

                yield $key => $value;
            }
        });
    }

    /**
     * Throttle the values, releasing them at most once per the given seconds.
     *
     * @return static<TKey, TValue>
     */
    public function throttle(float $seconds): static
    {
        return new static(function () use ($seconds) {
            $microseconds = $seconds * 1_000_000;

            foreach ($this as $key => $value) {
                $fetchedAt = $this->preciseNow();

                yield $key => $value;

                $sleep = $microseconds - ($this->preciseNow() - $fetchedAt);

                $this->usleep((int) $sleep);
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function dot(): static
    {
        return $this->passthru('dot', []);
    }

    /**
     * {@inheritDoc}
     */
    public function undot(): static
    {
        return $this->passthru('undot', []);
    }

    /**
     * {@inheritDoc}
     */
    public function unique($key = null, bool $strict = false): static
    {
        $callback = $this->valueRetriever($key);

        return new static(function () use ($callback, $strict) {
            $exists = [];

            foreach ($this as $key => $item) {
                if (! in_array($id = $callback($item, $key), $exists, $strict)) {
                    yield $key => $item;

                    $exists[] = $id;
                }
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function values(): static
    {
        return new static(function () {
            foreach ($this as $item) {
                yield $item;
            }
        });
    }

    /**
     * Run the given callback every time the interval has passed.
     *
     * @return static<TKey, TValue>
     */
    public function withHeartbeat(DateInterval|int $interval, callable $callback): static
    {
        $seconds = is_int($interval) ? $interval : $this->intervalSeconds($interval);

        return new static(function () use ($seconds, $callback) {
            $start = $this->now();

            foreach ($this as $key => $value) {
                $now = $this->now();

                if (($now - $start) >= $seconds) {
                    $callback();

                    $start = $now;
                }

                yield $key => $value;
            }
        });
    }

    /**
     * Get the total seconds from the given interval.
     */
    protected function intervalSeconds(DateInterval $interval): int
    {
        $start = new DateTimeImmutable();

        return $start->add($interval)->getTimestamp() - $start->getTimestamp();
    }

    /**
     * {@inheritDoc}
     */
    public function zip($items): static
    {
        $iterables = func_get_args();

        return new static(function () use ($iterables) {
            $iterators = Collection::make($iterables)
                ->map(fn ($iterable) => $this->makeIterator($iterable))
                ->prepend($this->getIterator());

            while ($iterators->contains->valid()) {
                yield new static($iterators->map->current());

                $iterators->each->next();
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function pad(int $size, mixed $value): static
    {
        if ($size < 0) {
            return $this->passthru('pad', func_get_args());
        }

        return new static(function () use ($size, $value) {
            $yielded = 0;

            foreach ($this as $index => $item) {
                yield $index => $item;

                $yielded++;
            }

            while ($yielded++ < $size) {
                yield $value;
            }
        });
    }

    /**
     * {@inheritDoc}
     */
    public function getIterator(): Traversable
    {
        return $this->makeIterator($this->source);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        if (is_array($this->source)) {
            return count($this->source);
        }

        return iterator_count($this->getIterator());
    }

    /**
     * Créez un itérateur à partir de la source donnée.
     *
     * @template TIteratorKey of array-key
     * @template TIteratorValue
     *
     * @param array<TIteratorKey, TIteratorValue>|(callable(): Generator<TIteratorKey, TIteratorValue>)|IteratorAggregate<TIteratorKey, TIteratorValue> $source
     *
     * @return Traversable<TIteratorKey, TIteratorValue>
     */
    protected function makeIterator($source): Traversable
    {
        if ($source instanceof IteratorAggregate) {
            return $source->getIterator();
        }

        if (is_array($source)) {
            return new ArrayIterator($source);
        }

        if (is_callable($source)) {
            $maybeTraversable = $source();

            return $maybeTraversable instanceof Traversable
                ? $maybeTraversable
                : new ArrayIterator(Arr::wrap($maybeTraversable));
        }

        return new ArrayIterator((array) $source);
    }

    /**
     * Décomposez les arguments "value" et "key" passés à "pluck".
     *
     * @param Closure|list<string>|string      $value
     * @param Closure|list<string>|string|null $key
     *
     * @return array{list<string>,list<string>|null}
     */
    protected function explodePluckParameters($value, $key): array
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_string($key) ? explode('.', $key) : $key;

        return [$value, $key];
    }

    /**
     * Passez cette collection paresseuse via une méthode sur la classe de collection.
     */
    protected function passthru(string $method, array $params): static
    {
        return new static(function () use ($method, $params) {
            yield from $this->collect()->{$method}(...$params);
        });
    }

    /**
     * Obtenez l'heure actuelle.
     */
    protected function now(): int
    {
        return time();
    }

    /**
     * Get the precise current time.
     */
    protected function preciseNow(): float
    {
        return microtime(true) * 1_000_000;
    }

    /**
     * Sleep for the given amount of microseconds.
     */
    protected function usleep(int $microseconds): void
    {
        if ($microseconds <= 0) {
            return;
        }

        usleep($microseconds);
    }
}
