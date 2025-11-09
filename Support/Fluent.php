<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\Support;

use ArrayAccess;
use ArrayIterator;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Contracts\Support\Jsonable;
use BlitzPHP\Traits\Conditionable;
use BlitzPHP\Traits\Macroable;
use BlitzPHP\Traits\Support\InteractsWithData;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\Iterable\Arr;
use Closure;
use JsonSerializable;
use Traversable;

/**
 * Definition des colonnes de la struture de migrations
 *
 * @template TKey of array-key
 * @template TValue
 *
 * @implements \BlitzPHP\Contracts\Support\Arrayable<TKey, TValue>
 * @implements \ArrayAccess<TKey, TValue>
 *
 * @credit 		<a href="https://laravel.com">Laravel - Illuminate\Support\Fluent</a>
 */
class Fluent implements Arrayable, ArrayAccess, Jsonable, JsonSerializable
{
    use Conditionable, InteractsWithData, Macroable {
        __call as macroCall;
    }

    /**
     * Tous les attributs de l'instance fluent.
     *
     * @var array<TKey, TValue>
     */
    protected $attributes = [];

    /**
     * @param iterable<TKey, TValue> $attributes
     */
    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Create a new fluent instance.
     *
     * @param iterable<TKey, TValue> $attributes
     */
    public static function make($attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * Recupere un attribut de l'instance.
     *
     * @template TGetDefault
     *
     * @param TKey                                 $key
     * @param (Closure(): TGetDefault)|TGetDefault $default
     *
     * @return TGetDefault|TValue
     */
    public function get($key, $default = null)
    {
        return Helpers::dataGet($this->attributes, $key, $default);
    }

    /**
     * Set an attribute on the fluent instance using "dot" notation.
     *
     * @param TKey   $key
     * @param TValue $value
     */
    public function set($key, $value): self
    {
        Helpers::dataSet($this->attributes, $key, $value);

        return $this;
    }

    /**
     * Fill the fluent instance with an array of attributes.
     *
     * @param iterable<TKey, TValue> $attributes
     */
    public function fill(iterable $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Get an attribute from the fluent instance.
     */
    public function value(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return Helpers::value($default);
    }

    /**
     * Get the value of the given key as a new Fluent instance.
     */
    public function scope(string $key, mixed $default = null): static
    {
        return new static((array) $this->get($key, $default));
    }

    /**
     * Get all of the attributes from the fluent instance.
     */
    public function all(mixed $keys = null): array
    {
        $data = $this->data();

        if (! $keys) {
            return $data;
        }

        $results = [];

        foreach (is_array($keys) ? $keys : func_get_args() as $key) {
            Arr::set($results, $key, Arr::get($data, $key));
        }

        return $results;
    }

    /**
     * Get data from the fluent instance.
     */
    protected function data(?string $key = null, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * Recupere les attributs de l'instance
     *
     * @return array<TKey, TValue>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Converti l'instance en tableeau.
     *
     * @return array<TKey, TValue>
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Converti l'instance en JSON serialisable.
     *
     * @return array<TKey, TValue>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convertie l'instance en json.
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convert the fluent instance to pretty print formatted JSON.
     */
    public function toPrettyJson(int $options = 0): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | $options);
    }

    /**
     * Determine if the fluent instance is empty.
     */
    public function isEmpty(): bool
    {
        return empty($this->attributes);
    }

    /**
     * Determine if the fluent instance is not empty.
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Verifie si une valeur existe a la position donnee
     *
     * @param TKey $offset
     */
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Recupere une valeur a la position donnee.
     *
     * @param TKey $offset
     *
     * @return TValue|null
     */
    public function offsetGet($offset): mixed
    {
        return $this->value($offset);
    }

    /**
     * Modifie une valeur a la position donnee.
     *
     * @param TKey   $offset
     * @param TValue $value
     */
    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Supprime une valeur a la position donnee.
     *
     * @param TKey $offset
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Get an iterator for the attributes.
     *
     * @return ArrayIterator<TKey, TValue>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }

    /**
     * Appel dynamique d'une methode pour modifier un attribut.
     *
     * @param TKey              $method
     * @param array{0: ?TValue} $parameters
     *
     * @return $this
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        $this->attributes[$method] = count($parameters) > 0 ? reset($parameters) : true;

        return $this;
    }

    /**
     * Recuperation dynamique d'un attribut
     *
     * @param TKey $key
     *
     * @return TValue|null
     */
    public function __get($key)
    {
        return $this->value($key);
    }

    /**
     * Modification dynamique d'une valeur de l'attribue.
     *
     * @param TKey   $key
     * @param TValue $value
     */
    public function __set($key, $value): void
    {
        $this->offsetSet($key, $value);
    }

    /**
     * Verifie dynamiquement si l'attribut existe
     *
     * @param TKey $key
     */
    public function __isset($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Suppression dynamique d'attributs
     *
     * @param TKey $key
     */
    public function __unset($key): void
    {
        $this->offsetUnset($key);
    }
}
