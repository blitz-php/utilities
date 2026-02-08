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

use ArrayAccess;
use ArrayIterator;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Contracts\Support\Enumerable;
use BlitzPHP\Traits\EnumeratesValues;
use BlitzPHP\Traits\Macroable;
use BlitzPHP\Utilities\Exceptions\ItemNotFoundException;
use BlitzPHP\Utilities\Exceptions\MultipleItemsFoundException;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\String\Stringable;
use Closure;
use InvalidArgumentException;
use stdClass;
use Traversable;
use UnitEnum;

/**
 * Collection d'éléments
 *
 * Cette classe fournit une interface fluide pour manipuler des tableaux de données.
 * Elle implémente plusieurs interfaces pour un maximum de compatibilité.
 *
 * @template TKey de array-key
 *
 * @template-covariant TValue
 *
 * @implements \ArrayAccess<TKey, TValue>
 * @implements \BlitzPHP\Contracts\Support\Enumerable<TKey, TValue>
 */
class Collection implements ArrayAccess, Enumerable
{
    /**
     * Utilise le trait EnumeratesValues
     *
     * @use \BlitzPHP\Traits\EnumeratesValues<TKey, TValue>
     */
    use EnumeratesValues;

    use Macroable;

    /**
     * Les éléments contenus dans la collection.
     *
     * @var array<TKey, TValue>
     */
    protected array $items = [];

    /**
     * Crée une nouvelle collection.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue>|null $items
     */
    public function __construct($items = [])
    {
        $this->items = $this->getArrayableItems($items);
    }

    /**
     * {@inheritDoc}
     *
     * @return static<int, int|string>
     */
    public static function range($from, $to, $step = 1)
    {
        return new static(range($from, $to, $step));
    }

    /**
     * {@inheritDoc}
     */
    public function all(): array
    {
        return $this->items;
    }

    /**
     * Obtient une collection paresseuse pour les éléments de cette collection.
     *
     * @return LazyCollection<TKey, TValue>
     */
    public function lazy(): LazyCollection
    {
        return new LazyCollection($this->items);
    }

    /**
     * {@inheritDoc}
     */
    public function median($key = null)
    {
        $values = (isset($key) ? $this->pluck($key) : $this)
            ->reject(static fn ($item) => null === $item)
            ->sort()->values();

        if (0 === $count = $values->count()) {
            return;
        }

        $middle = intdiv($count, 2);

        if ($count % 2) {
            return $values->get($middle);
        }

        return static::make([$values->get($middle - 1), $values->get($middle)])->average();
    }

    /**
     * {@inheritDoc}
     */
    public function mode($key = null): ?array
    {
        if ($this->count() === 0) {
            return null;
        }

        $collection = isset($key) ? $this->pluck($key) : $this;

        $counts = new static();

        $collection->each(static fn ($value) => $counts[$value] = isset($counts[$value]) ? $counts[$value] + 1 : 1);

        $sorted = $counts->sort();

        $highestValue = $sorted->last();

        return $sorted->filter(static fn ($value) => $value === $highestValue)->sort()->keys()->all();
    }

    /**
     * {@inheritDoc}
     */
    public function collapse()
    {
        return new static(Arr::collapse($this->items));
    }

    /**
     * Réduit la collection d'éléments en un seul tableau tout en préservant ses clés.
     *
     * @return static<mixed, mixed>
     */
    public function collapseWithKeys()
    {
        if (! $this->items) {
            return new static();
        }

        $results = [];

        foreach ($this->items as $key => $values) {
            if ($values instanceof Collection) {
                $values = $values->all();
            } elseif (! is_array($values)) {
                continue;
            }

            $results[$key] = $values;
        }

        if ($results === []) {
            return new static();
        }

        return new static(array_replace(...$results));
    }

    /**
     * {@inheritDoc}
     */
    public function contains($key, mixed $operator = null, mixed $value = null): bool
    {
        if (func_num_args() === 1) {
            if ($this->useAsCallable($key)) {
                $placeholder = new stdClass();

                return $this->first($key, $placeholder) !== $placeholder;
            }

            return in_array($key, $this->items, true);
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

        return in_array($key, $this->items, true);
    }

    /**
     * {@inheritDoc}
     */
    public function doesntContain(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        return ! $this->contains(...func_get_args());
    }

    /**
     * Détermine si un élément n'est pas contenu dans l'énumérable, en utilisant une comparaison stricte.
     */
    public function doesntContainStrict(mixed $key, mixed $operator = null, mixed $value = null): bool
    {
        return ! $this->containsStrict(...func_get_args());
    }

    /**
     * {@inheritDoc}
     */
    public function crossJoin(...$lists): static
    {
        return new static(Arr::crossJoin(
            $this->items,
            ...array_map($this->getArrayableItems(...), $lists)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function diff($items): static
    {
        return new static(array_diff($this->items, $this->getArrayableItems($items)));
    }

    /**
     * {@inheritDoc}
     */
    public function diffUsing($items, callable $callback): static
    {
        return new static(array_udiff($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * {@inheritDoc}
     */
    public function diffAssoc($items): static
    {
        return new static(array_diff_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * {@inheritDoc}
     */
    public function diffAssocUsing($items, callable $callback): static
    {
        return new static(array_diff_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * {@inheritDoc}
     */
    public function diffKeys($items): static
    {
        return new static(array_diff_key($this->items, $this->getArrayableItems($items)));
    }

    /**
     * {@inheritDoc}
     */
    public function diffKeysUsing($items, callable $callback): static
    {
        return new static(array_diff_ukey($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * {@inheritDoc}
     */
    public function duplicates($callback = null, bool $strict = false): static
    {
        $items = $this->map($this->valueRetriever($callback));

        $uniqueItems = $items->unique(null, $strict);

        $compare = $this->duplicateComparator($strict);

        $duplicates = new static();

        foreach ($items as $key => $value) {
            if ($uniqueItems->isNotEmpty() && $compare($value, $uniqueItems->first())) {
                $uniqueItems->shift();
            } else {
                $duplicates[$key] = $value;
            }
        }

        return $duplicates;
    }

    /**
     * {@inheritDoc}
     */
    public function duplicatesStrict($callback = null): static
    {
        return $this->duplicates($callback, true);
    }

    /**
     * Obtient la fonction de comparaison pour détecter les doublons.
     *
     * @param bool $strict Indique si la comparaison doit être stricte
     *
     * @return callable(TValue, TValue): bool
     */
    protected function duplicateComparator(bool $strict): Closure
    {
        if ($strict) {
            return static fn ($a, $b) => $a === $b;
        }

        return static fn ($a, $b) => $a === $b;
    }

    /**
     * {@inheritDoc}
     */
    public function except($keys): static
    {
        if (null === $keys) {
            return new static($this->items);
        }

        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        } elseif (! is_array($keys)) {
            $keys = func_get_args();
        }

        return new static(Arr::except($this->items, $keys));
    }

    /**
     * {@inheritDoc}
     */
    public function filter(?callable $callback = null): static
    {
        if ($callback) {
            return new static(Arr::where($this->items, $callback));
        }

        return new static(array_filter($this->items));
    }

    /**
     * {@inheritDoc}
     */
    public function first(?callable $callback = null, $default = null)
    {
        return Arr::first($this->items, $callback, $default);
    }

    /**
     * {@inheritDoc}
     *
     * @return static<int, mixed>
     */
    public function flatten(float|int $depth = INF): static
    {
        return new static(Arr::flatten($this->items, $depth));
    }

    /**
     * {@inheritDoc}
     */
    public function flip(): static
    {
        return new static(array_flip($this->items));
    }

    /**
     * Supprime un élément de la collection par clé.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TKey>|TKey $keys Les clés à supprimer
     */
    public function forget($keys): self
    {
        foreach ($this->getArrayableItems($keys) as $key) {
            $this->offsetUnset($key);
        }

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function get(int|string|null $key, mixed $default = null): mixed
    {
        if (array_key_exists($key ?? '', $this->items)) {
            return $this->items[$key];
        }

        return Helpers::value($default);
    }

    /**
     * Obtient un élément de la collection par clé ou l'ajoute à la collection s'il n'existe pas.
     *
     * @template TGetOrPutValue
     *
     * @param mixed                                      $key   La clé de l'élément
     * @param (Closure(): TGetOrPutValue)|TGetOrPutValue $value La valeur ou le callback pour la valeur
     *
     * @return TGetOrPutValue|TValue
     */
    public function getOrPut(mixed $key, mixed $value): mixed
    {
        if (array_key_exists($key ?? '', $this->items)) {
            return $this->items[$key ?? ''];
        }

        $this->offsetSet($key, $value = Helpers::value($value));

        return $value;
    }

    /**
     * {@inheritDoc}
     */
    public function groupBy($groupBy, $preserveKeys = false): static
    {
        if (! $this->useAsCallable($groupBy) && is_array($groupBy)) {
            $nextGroups = $groupBy;

            $groupBy = array_shift($nextGroups);
        }

        $groupBy = $this->valueRetriever($groupBy);

        $results = [];

        foreach ($this->items as $key => $value) {
            $groupKeys = $groupBy($value, $key);

            if (! is_array($groupKeys)) {
                $groupKeys = [$groupKeys];
            }

            foreach ($groupKeys as $groupKey) {
                $groupKey = match (true) {
                    is_bool($groupKey)              => (int) $groupKey,
                    $groupKey instanceof UnitEnum   => Helpers::enumValue($groupKey),
                    $groupKey instanceof Stringable => (string) $groupKey,
                    $groupKey === null              => (string) $groupKey,
                    default                         => $groupKey,
                };

                if (! array_key_exists($groupKey, $results)) {
                    $results[$groupKey] = new static();
                }

                $results[$groupKey]->offsetSet($preserveKeys ? $key : null, $value);
            }
        }

        $result = new static($results);

        if (! empty($nextGroups)) {
            return $result->map->groupBy($nextGroups, $preserveKeys);
        }

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function keyBy($keyBy): static
    {
        $keyBy = $this->valueRetriever($keyBy);

        $results = [];

        foreach ($this->items as $key => $item) {
            $resolvedKey = Helpers::enumValue($keyBy($item, $key));

            if (is_object($resolvedKey)) {
                $resolvedKey = (string) $resolvedKey;
            }

            $results[$resolvedKey] = $item;
        }

        return new static($results);
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, TKey>|TKey $key
     */
    public function has(mixed $key): bool
    {
        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (! array_key_exists($value, $this->items)) {
                return false;
            }
        }

        return true;
    }

    /**
     * {@inheritDoc}
     *
     * @param array<array-key, TKey>|TKey $key
     */
    public function hasAny(mixed $key): bool
    {
        if ($this->isEmpty()) {
            return false;
        }

        $keys = is_array($key) ? $key : func_get_args();

        foreach ($keys as $value) {
            if (array_key_exists($value, $this->items)) {
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
        if ($this->useAsCallable($value)) {
            return implode($glue ?? '', $this->map($value)->all());
        }

        $first = $this->first();

        if (is_array($first) || (is_object($first) && ! $first instanceof Stringable)) {
            return implode($glue ?? '', $this->pluck($value)->all());
        }

        return implode($value ?? '', $this->items);
    }

    /**
     * {@inheritDoc}
     */
    public function intersect($items): static
    {
        return new static(array_intersect($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersecte la collection avec les éléments donnés, en utilisant un callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int                            $callback
     */
    public function intersectUsing($items, callable $callback): static
    {
        return new static(array_uintersect($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * Croise la collection avec les éléments donnés avec une vérification d'index supplémentaire.
     *
     * @param Arrayable<TKey, TValue>|iterable<TKey, TValue> $items
     */
    public function intersectAssoc($items): static
    {
        return new static(array_intersect_assoc($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Intersecte la collection avec les éléments donnés avec une vérification d'index supplémentaire, en utilisant un callback.
     *
     * @param Arrayable<array-key, TValue>|iterable<array-key, TValue> $items
     * @param callable(TValue, TValue): int                            $callback
     */
    public function intersectAssocUsing($items, callable $callback): static
    {
        return new static(array_intersect_uassoc($this->items, $this->getArrayableItems($items), $callback));
    }

    /**
     * {@inheritDoc}
     */
    public function intersectByKeys($items): static
    {
        return new static(array_intersect_key(
            $this->items,
            $this->getArrayableItems($items)
        ));
    }

    /**
     * {@inheritDoc}
     */
    public function isEmpty(): bool
    {
        return empty($this->items);
    }

    /**
     * {@inheritDoc}
     *
     * @param (callable(TValue, TKey): bool)|null $callback
     */
    public function containsOneItem(?callable $callback = null): bool
    {
        if ($callback) {
            return $this->filter($callback)->count() === 1;
        }

        return $this->count() === 1;
    }

    /**
     * {@inheritDoc}
     */
    public function join(string $glue, string $finalGlue = ''): string
    {
        if ($finalGlue === '') {
            return $this->implode($glue);
        }

        if (0 === $count = $this->count()) {
            return '';
        }

        if ($count === 1) {
            return $this->last();
        }

        $collection = new static($this->items);

        $finalItem = $collection->pop();

        return $collection->implode($glue) . $finalGlue . $finalItem;
    }

    /**
     * {@inheritDoc}
     */
    public function keys()
    {
        return new static(array_keys($this->items));
    }

    /**
     * {@inheritDoc}
     */
    public function last(?callable $callback = null, $default = null): mixed
    {
        return Arr::last($this->items, $callback, $default);
    }

    /**
     * {@inheritDoc}
     */
    public function pluck($value, $key = null): static
    {
        return new static(Arr::pluck($this->items, $value, $key));
    }

    /**
     * {@inheritDoc}
     */
    public function map(callable $callback): static
    {
        return new static(Arr::map($this->items, $callback));
    }

    /**
     * {@inheritDoc}
     */
    public function mapToDictionary(callable $callback): static
    {
        $dictionary = [];

        foreach ($this->items as $key => $item) {
            $pair = $callback($item, $key);

            $key = key($pair);

            $value = reset($pair);

            if (! isset($dictionary[$key])) {
                $dictionary[$key] = [];
            }

            $dictionary[$key][] = $value;
        }

        return new static($dictionary);
    }

    /**
     * {@inheritDoc}
     */
    public function mapWithKeys(callable $callback)
    {
        return new static(Arr::mapWithKeys($this->items, $callback));
    }

    /**
     * {@inheritDoc}
     */
    public function merge($items): static
    {
        return new static(array_merge($this->items, $this->getArrayableItems($items)));
    }

    /**
     * {@inheritDoc}
     */
    public function mergeRecursive($items): static
    {
        return new static(array_merge_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * Multiplie les éléments de la collection par le multiplicateur.
     *
     * @param int $multiplier Le multiplicateur
     */
    public function multiply(int $multiplier): static
    {
        $new = new static();

        for ($i = 0; $i < $multiplier; $i++) {
            $new->push(...$this->items);
        }

        return $new;
    }

    /**
     * {@inheritDoc}
     */
    public function combine($values): static
    {
        return new static(array_combine($this->all(), $this->getArrayableItems($values)));
    }

    /**
     * {@inheritDoc}
     */
    public function union($items): static
    {
        return new static($this->items + $this->getArrayableItems($items));
    }

    /**
     * {@inheritDoc}
     */
    public function nth(int $step, int $offset = 0): static
    {
        $new = [];

        $position = 0;

        foreach ($this->slice($offset)->items as $item) {
            if ($position % $step === 0) {
                $new[] = $item;
            }

            $position++;
        }

        return new static($new);
    }

    /**
     * {@inheritDoc}
     */
    public function only($keys): static
    {
        if (null === $keys) {
            return new static($this->items);
        }

        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::only($this->items, $keys));
    }

    /**
     * Sélectionne des valeurs spécifiques des éléments dans la collection.
     *
     * @param array<array-key, TKey>|Enumerable<array-key, TKey>|string|null $keys
     */
    public function select($keys): static
    {
        if (null === $keys) {
            return new static($this->items);
        }

        if ($keys instanceof Enumerable) {
            $keys = $keys->all();
        }

        $keys = is_array($keys) ? $keys : func_get_args();

        return new static(Arr::select($this->items, $keys));
    }

    /**
     * Obtient et supprime les N derniers éléments de la collection.
     *
     * @param int $count Le nombre d'éléments à dépiler
     *
     * @return ($count is 1 ? TValue|null : static<int, TValue>)
     */
    public function pop(int $count = 1)
    {
        if ($count < 1) {
            return new static();
        }

        if ($count === 1) {
            return array_pop($this->items);
        }

        if ($this->isEmpty()) {
            return new static();
        }

        $results = [];

        $collectionCount = $this->count();

        foreach (range(1, min($count, $collectionCount)) as $item) {
            $results[] = array_pop($this->items);
        }

        return new static($results);
    }

    /**
     * Ajoute un élément au début de la collection.
     *
     * @param mixed $value La valeur à ajouter
     * @param mixed $key   La clé optionnelle
     */
    public function prepend(mixed $value, mixed $key = null): self
    {
        $this->items = Arr::prepend($this->items, ...func_get_args());

        return $this;
    }

    /**
     * Ajoute un élément à la fin de la collection.
     *
     * @param mixed ...$values Les valeurs à ajouter
     */
    public function push(...$values): self
    {
        foreach ($values as $value) {
            $this->items[] = $value;
        }

        return $this;
    }

    /**
     * Ajoute un ou plusieurs éléments au début de la collection.
     *
     * @param mixed ...$values Les valeurs à ajouter
     */
    public function unshift(...$values): self
    {
        array_unshift($this->items, ...$values);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function concat(iterable $source): static
    {
        $result = new static($this);

        foreach ($source as $item) {
            $result->push($item);
        }

        return $result;
    }

    /**
     * Obtient et supprime un élément de la collection.
     *
     * @template TPullDefault
     *
     * @param string                                 $key     La clé de l'élément
     * @param (Closure(): TPullDefault)|TPullDefault $default La valeur par défaut
     *
     * @return TPullDefault|TValue
     */
    public function pull(string $key, mixed $default = null): mixed
    {
        return Arr::pull($this->items, $key, $default);
    }

    /**
     * Place un élément dans la collection par clé.
     *
     * @param int|string $key   La clé
     * @param mixed      $value La valeur
     */
    public function put(int|string $key, mixed $value): self
    {
        $this->offsetSet($key, $value);

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function random($number = null, bool $preserveKeys = false)
    {
        if (null === $number) {
            return Arr::random($this->items);
        }

        if (is_callable($number)) {
            return new static(Arr::random($this->items, $number($this), $preserveKeys));
        }

        return new static(Arr::random($this->items, $number, $preserveKeys));
    }

    /**
     * {@inheritDoc}
     */
    public function replace($items): static
    {
        return new static(array_replace($this->items, $this->getArrayableItems($items)));
    }

    /**
     * {@inheritDoc}
     */
    public function replaceRecursive($items): static
    {
        return new static(array_replace_recursive($this->items, $this->getArrayableItems($items)));
    }

    /**
     * {@inheritDoc}
     */
    public function reverse(): static
    {
        return new static(array_reverse($this->items, true));
    }

    /**
     * {@inheritDoc}
     */
    public function search($value, bool $strict = false)
    {
        if (! $this->useAsCallable($value)) {
            return array_search($value, $this->items, $strict);
        }

        foreach ($this->items as $key => $item) {
            if ($value($item, $key)) {
                return $key;
            }
        }

        return false;
    }

    /**
     * Obtient l'élément précédant l'élément donné.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     *
     * @return TValue|null
     */
    public function before($value, bool $strict = false)
    {
        $key = $this->search($value, $strict);

        if ($key === false) {
            return null;
        }

        $position = ($keys = $this->keys())->search($key);

        if ($position === 0) {
            return null;
        }

        return $this->get($keys->get($position - 1));
    }

    /**
     * Obtient l'élément suivant l'élément donné.
     *
     * @param (callable(TValue,TKey): bool)|TValue $value
     *
     * @return TValue|null
     */
    public function after($value, bool $strict = false)
    {
        $key = $this->search($value, $strict);

        if ($key === false) {
            return null;
        }

        $position = ($keys = $this->keys())->search($key);

        if ($position === $keys->count() - 1) {
            return null;
        }

        return $this->get($keys->get($position + 1));
    }

    /**
     * Obtient et supprime les N premiers éléments de la collection.
     *
     * @param int $count Le nombre d'éléments à décaler
     *
     * @return static<int, TValue>|TValue|null
     *
     * @throws InvalidArgumentException
     */
    public function shift(int $count = 1)
    {
        if ($count < 0) {
            throw new InvalidArgumentException('Le nombre d\'éléments décalés ne peut pas être inférieur à zéro.');
        }

        if ($this->isEmpty()) {
            return null;
        }

        if ($count === 0) {
            return new static();
        }

        if ($count === 1) {
            return array_shift($this->items);
        }

        $results = [];

        $collectionCount = $this->count();

        foreach (range(1, min($count, $collectionCount)) as $item) {
            $results[] = array_shift($this->items);
        }

        return new static($results);
    }

    /**
     * {@inheritDoc}
     */
    public function shuffle(?int $seed = null): static
    {
        return new static(Arr::shuffle($this->items, $seed));
    }

    /**
     * {@inheritDoc}
     */
    public function sliding(int $size = 2, int $step = 1): static
    {
        $chunks = floor(($this->count() - $size) / $step) + 1;

        return static::times($chunks, fn ($number) => $this->slice(($number - 1) * $step, $size));
    }

    /**
     * {@inheritDoc}
     */
    public function skip(int $count): static
    {
        return $this->slice($count);
    }

    /**
     * {@inheritDoc}
     */
    public function skipUntil($value): static
    {
        return new static($this->lazy()->skipUntil($value)->all());
    }

    /**
     * {@inheritDoc}
     */
    public function skipWhile($value): static
    {
        return new static($this->lazy()->skipWhile($value)->all());
    }

    /**
     * {@inheritDoc}
     */
    public function slice(int $offset, ?int $length = null)
    {
        return new static(array_slice($this->items, $offset, $length, true));
    }

    /**
     * {@inheritDoc}
     */
    public function split(int $numberOfGroups): static
    {
        if ($this->isEmpty()) {
            return new static();
        }

        $groups = new static();

        $groupSize = floor($this->count() / $numberOfGroups);

        $remain = $this->count() % $numberOfGroups;

        $start = 0;

        for ($i = 0; $i < $numberOfGroups; $i++) {
            $size = $groupSize;

            if ($i < $remain) {
                $size++;
            }

            if ($size) {
                $groups->push(new static(array_slice($this->items, $start, $size)));

                $start += $size;
            }
        }

        return $groups;
    }

    /**
     * {@inheritDoc}
     */
    public function splitIn(int $numberOfGroups)
    {
        return $this->chunk(ceil($this->count() / $numberOfGroups));
    }

    /**
     * {@inheritDoc}
     *
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public function sole($key = null, mixed $operator = null, mixed $value = null): mixed
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        $items = $this->unless($filter === null)->filter($filter);

        $count = $items->count();

        if ($count === 0) {
            throw new ItemNotFoundException('Aucun élément trouvé.');
        }

        if ($count > 1) {
            throw new MultipleItemsFoundException($count, 'Plusieurs éléments trouvés.');
        }

        return $items->first();
    }

    /**
     * {@inheritDoc}
     *
     * @throws ItemNotFoundException
     */
    public function firstOrFail($key = null, mixed $operator = null, mixed $value = null): mixed
    {
        $filter = func_num_args() > 1
            ? $this->operatorForWhere(...func_get_args())
            : $key;

        $placeholder = new stdClass();

        $item = $this->first($filter, $placeholder);

        if ($item === $placeholder) {
            throw new ItemNotFoundException('Aucun élément trouvé.');
        }

        return $item;
    }

    /**
     * {@inheritDoc}
     */
    public function chunk(int $size, bool $preserveKeys = true): static
    {
        if ($size <= 0) {
            return new static();
        }

        $chunks = [];

        foreach (array_chunk($this->items, $size, $preserveKeys) as $chunk) {
            $chunks[] = new static($chunk);
        }

        return new static($chunks);
    }

    /**
     * {@inheritDoc}
     */
    public function chunkWhile(callable $callback): static
    {
        return new static(
            $this->lazy()->chunkWhile($callback)->mapInto(static::class)
        );
    }

    /**
     * {@inheritDoc}
     *
     * @param (callable(TValue, TValue): int)|int|null $callback
     */
    public function sort($callback = null): static
    {
        $items = $this->items;

        $callback && is_callable($callback)
            ? uasort($items, $callback)
            : asort($items, $callback ?? SORT_REGULAR);

        return new static($items);
    }

    /**
     * {@inheritDoc}
     */
    public function sortDesc(int $options = SORT_REGULAR): static
    {
        $items = $this->items;

        arsort($items, $options);

        return new static($items);
    }

    /**
     * {@inheritDoc}
     */
    public function sortBy($callback, int $options = SORT_REGULAR, bool $descending = false): static
    {
        if (is_array($callback) && ! is_callable($callback)) {
            return $this->sortByMany($callback, $options);
        }

        $results = [];

        $callback = $this->valueRetriever($callback);

        // Nous allons d'abord parcourir les éléments et obtenir le comparateur à partir d'une fonction de callback qui nous a été donnée.
        // Ensuite, nous allons trier les valeurs renvoyées et récupérer toutes les valeurs correspondantes pour les clés triées de ce tableau.
        foreach ($this->items as $key => $value) {
            $results[$key] = $callback($value, $key);
        }

        $descending ? arsort($results, $options)
            : asort($results, $options);

        // Une fois que nous avons trié toutes les clés du tableau, nous les parcourons en boucle et récupérons le modèle correspondant afin de pouvoir définir la liste des éléments sous-jacents sur la version triée.
        // Ensuite, nous renverrons simplement l'instance de collection.
        foreach (array_keys($results) as $key) {
            $results[$key] = $this->items[$key];
        }

        return new static($results);
    }

    /**
     * Trie la collection à l'aide de plusieurs comparaisons.
     *
     * @param array<array-key, array{string, string}|(callable(TValue, TKey): mixed)|(callable(TValue, TValue): mixed)|string> $comparisons
     * @param int                                                                                                              $options     Options de tri
     */
    protected function sortByMany(array $comparisons = [], int $options = SORT_REGULAR): static
    {
        $items = $this->items;

        uasort($items, static function ($a, $b) use ($comparisons, $options) {
            foreach ($comparisons as $comparison) {
                $comparison = Arr::wrap($comparison);

                $prop = $comparison[0];

                $ascending = Arr::get($comparison, 1, true) === true
                             || Arr::get($comparison, 1, true) === 'asc';

                if (! is_string($prop) && is_callable($prop)) {
                    $result = $prop($a, $b);
                } else {
                    $values = [Helpers::dataGet($a, $prop), Helpers::dataGet($b, $prop)];

                    if (! $ascending) {
                        $values = array_reverse($values);
                    }

                    if (($options & SORT_FLAG_CASE) === SORT_FLAG_CASE) {
                        if (($options & SORT_NATURAL) === SORT_NATURAL) {
                            $result = strnatcasecmp($values[0], $values[1]);
                        } else {
                            $result = strcasecmp($values[0], $values[1]);
                        }
                    } else {
                        $result = match ($options) {
                            SORT_NUMERIC       => (int) ($values[0]) <=> (int) ($values[1]),
                            SORT_STRING        => strcmp($values[0], $values[1]),
                            SORT_NATURAL       => strnatcmp((string) $values[0], (string) $values[1]),
                            SORT_LOCALE_STRING => strcoll($values[0], $values[1]),
                            default            => $values[0] <=> $values[1],
                        };
                    }
                }

                if ($result === 0) {
                    continue;
                }

                return $result;
            }
        });

        return new static($items);
    }

    /**
     * {@inheritDoc}
     */
    public function sortByDesc($callback, int $options = SORT_REGULAR): static
    {
        if (is_array($callback) && ! is_callable($callback)) {
            foreach ($callback as $index => $key) {
                $comparison = Arr::wrap($key);

                $comparison[1] = 'desc';

                $callback[$index] = $comparison;
            }
        }

        return $this->sortBy($callback, $options, true);
    }

    /**
     * {@inheritDoc}
     */
    public function sortKeys(int $options = SORT_REGULAR, bool $descending = false): static
    {
        $items = $this->items;

        $descending ? krsort($items, $options) : ksort($items, $options);

        return new static($items);
    }

    /**
     * {@inheritDoc}
     */
    public function sortKeysDesc(int $options = SORT_REGULAR): static
    {
        return $this->sortKeys($options, true);
    }

    /**
     * {@inheritDoc}
     */
    public function sortKeysUsing(callable $callback): static
    {
        $items = $this->items;

        uksort($items, $callback);

        return new static($items);
    }

    /**
     * Extrait une portion du tableau de collection sous-jacent.
     *
     * @param int                      $offset      Début de l'extraction
     * @param int|null                 $length      Longueur de l'extraction
     * @param array<array-key, TValue> $replacement Éléments de remplacement
     */
    public function splice(int $offset, ?int $length = null, array $replacement = []): static
    {
        if (func_num_args() === 1) {
            return new static(array_splice($this->items, $offset));
        }

        return new static(array_splice($this->items, $offset, $length, $this->getArrayableItems($replacement)));
    }

    /**
     * {@inheritDoc}
     */
    public function take(int $limit): static
    {
        if ($limit < 0) {
            return $this->slice($limit, abs($limit));
        }

        return $this->slice(0, $limit);
    }

    /**
     * {@inheritDoc}
     */
    public function takeUntil($value): static
    {
        return new static($this->lazy()->takeUntil($value)->all());
    }

    /**
     * {@inheritDoc}
     */
    public function takeWhile($value): static
    {
        return new static($this->lazy()->takeWhile($value)->all());
    }

    /**
     * Transforme chaque élément de la collection à l'aide d'un callback.
     *
     * @param callable(TValue, TKey): TValue $callback
     */
    public function transform(callable $callback): self
    {
        $this->items = $this->map($callback)->all();

        return $this;
    }

    /**
     * {@inheritDoc}
     */
    public function dot(): static
    {
        return new static(Arr::dot($this->all()));
    }

    /**
     * {@inheritDoc}
     */
    public function undot(): static
    {
        return new static(Arr::undot($this->all()));
    }

    /**
     * {@inheritDoc}
     */
    public function unique($key = null, bool $strict = false): static
    {
        if (null === $key && $strict === false) {
            return new static(array_unique($this->items, SORT_REGULAR));
        }

        $callback = $this->valueRetriever($key);

        $exists = [];

        return $this->reject(static function ($item, $key) use ($callback, $strict, &$exists) {
            if (in_array($id = $callback($item, $key), $exists, $strict)) {
                return true;
            }

            $exists[] = $id;
        });
    }

    /**
     * {@inheritDoc}
     */
    public function values()
    {
        return new static(array_values($this->items));
    }

    /**
     * {@inheritDoc}
     */
    public function zip($items): static
    {
        $arrayableItems = array_map(fn ($items) => $this->getArrayableItems($items), func_get_args());

        $params = array_merge([static fn () => new static(func_get_args()), $this->items], $arrayableItems);

        return new static(array_map(...$params));
    }

    /**
     * {@inheritDoc}
     */
    public function pad(int $size, mixed $value): static
    {
        return new static(array_pad($this->items, $size, $value));
    }

    /**
     * Obtient un itérateur pour les éléments.
     *
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->items);
    }

    /**
     * {@inheritDoc}
     */
    public function count(): int
    {
        return count($this->items);
    }

    /**
     * {@inheritDoc}
     */
    public function countBy($countBy = null): static
    {
        return new static($this->lazy()->countBy($countBy)->all());
    }

    /**
     * Ajoute un élément à la collection.
     *
     * @param TValue $item
     */
    public function add(mixed $item): self
    {
        $this->items[] = $item;

        return $this;
    }

    /**
     * Obtient une instance de collection Support de base à partir de cette collection.
     *
     * @return Collection<TKey, TValue>
     */
    public function toBase(): self
    {
        return new self($this);
    }

    /**
     * Détermine si un élément existe à une position donnée.
     *
     * @param TKey $key
     */
    public function offsetExists(mixed $key): bool
    {
        return isset($this->items[$key]);
    }

    /**
     * Obtient un élément se trouvant à une position donnée.
     *
     * @param TKey $key
     *
     * @return ?TValue
     */
    public function offsetGet(mixed $key): mixed
    {
        return $this->items[$key] ?? null;
    }

    /**
     * Définit l'élément à une position donnée.
     *
     * @param ?TKey  $key
     * @param TValue $value
     */
    public function offsetSet(mixed $key, mixed $value): void
    {
        if (null === $key) {
            $this->items[] = $value;
        } else {
            $this->items[$key] = $value;
        }
    }

    /**
     * Supprime l'élément à une position donnée.
     *
     * @param TKey $key
     */
    public function offsetUnset(mixed $key): void
    {
        unset($this->items[$key]);
    }
}
