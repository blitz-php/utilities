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
 * Classe Fluent pour manipuler des données de manière expressive
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
     * Constructeur
     *
     * @param iterable<TKey, TValue> $attributes Attributs initiaux
     */
    public function __construct($attributes = [])
    {
        $this->fill($attributes);
    }

    /**
     * Crée une nouvelle instance fluent
     *
     * @param iterable<TKey, TValue> $attributes Attributs initiaux
     */
    public static function make($attributes = []): static
    {
        return new static($attributes);
    }

    /**
     * Récupère un attribut de l'instance
     *
     * @template TGetDefault
     *
     * @param TKey                                 $key     Clé de l'attribut
     * @param (Closure(): TGetDefault)|TGetDefault $default Valeur par défaut si la clé n'existe pas
     *
     * @return TGetDefault|TValue
     */
    public function get($key, $default = null): mixed
    {
        return Helpers::dataGet($this->attributes, $key, $default);
    }

    /**
     * Définit un attribut sur l'instance fluent en utilisant la notation "point"
     *
     * @param TKey   $key   Clé de l'attribut
     * @param TValue $value Valeur à définir
     *
     * @return $this
     */
    public function set($key, $value): self
    {
        Helpers::dataSet($this->attributes, $key, $value);

        return $this;
    }

    /**
     * Remplit l'instance fluent avec un tableau d'attributs
     *
     * @param iterable<TKey, TValue> $attributes Attributs à ajouter
     *
     * @return $this
     */
    public function fill(iterable $attributes): self
    {
        foreach ($attributes as $key => $value) {
            $this->attributes[$key] = $value;
        }

        return $this;
    }

    /**
     * Récupère un attribut de l'instance fluent
     *
     * @param string $key     Clé de l'attribut
     * @param mixed  $default Valeur par défaut si la clé n'existe pas
     *
     * @return mixed Valeur de l'attribut
     */
    public function value(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $this->attributes)) {
            return $this->attributes[$key];
        }

        return Helpers::value($default);
    }

    /**
     * Récupère la valeur de la clé donnée comme une nouvelle instance Fluent
     *
     * @param string $key     Clé de l'attribut
     * @param mixed  $default Valeur par défaut si la clé n'existe pas
     *
     * @return static Nouvelle instance Fluent
     */
    public function scope(string $key, mixed $default = null): static
    {
        return new static((array) $this->get($key, $default));
    }

    /**
     * Récupère tous les attributs de l'instance fluent
     *
     * @param mixed $keys Clés spécifiques à récupérer (optionnel)
     *
     * @return array Attributs demandés
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
     * Récupère les données de l'instance fluent
     *
     * @param string|null $key     Clé spécifique (optionnel)
     * @param mixed       $default Valeur par défaut (optionnel)
     *
     * @return mixed Données demandées
     */
    protected function data(?string $key = null, mixed $default = null): mixed
    {
        return $this->get($key, $default);
    }

    /**
     * Récupère les attributs de l'instance
     *
     * @return array<TKey, TValue> Attributs
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Convertit l'instance en tableau
     *
     * @return array<TKey, TValue> Tableau d'attributs
     */
    public function toArray(): array
    {
        return $this->attributes;
    }

    /**
     * Convertit l'instance en format sérialisable JSON
     *
     * @return array<TKey, TValue> Tableau sérialisable
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Convertit l'instance en JSON
     *
     * @param int $options Options de json_encode
     *
     * @return string JSON encodé
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->jsonSerialize(), $options);
    }

    /**
     * Convertit l'instance fluent en JSON formaté (pretty print)
     *
     * @param int $options Options de json_encode
     *
     * @return string JSON formaté
     */
    public function toPrettyJson(int $options = 0): string
    {
        return $this->toJson(JSON_PRETTY_PRINT | $options);
    }

    /**
     * Détermine si l'instance fluent est vide
     *
     * @return bool True si vide, false sinon
     */
    public function isEmpty(): bool
    {
        return empty($this->attributes);
    }

    /**
     * Détermine si l'instance fluent n'est pas vide
     *
     * @return bool True si non vide, false sinon
     */
    public function isNotEmpty(): bool
    {
        return ! $this->isEmpty();
    }

    /**
     * Vérifie si une valeur existe à la position donnée
     *
     * @param TKey $offset Position à vérifier
     *
     * @return bool True si existe, false sinon
     */
    public function offsetExists($offset): bool
    {
        return isset($this->attributes[$offset]);
    }

    /**
     * Récupère une valeur à la position donnée
     *
     * @param TKey $offset Position à récupérer
     *
     * @return TValue|null Valeur à la position ou null
     */
    public function offsetGet($offset): mixed
    {
        return $this->value($offset);
    }

    /**
     * Modifie une valeur à la position donnée
     *
     * @param TKey   $offset Position à modifier
     * @param TValue $value  Nouvelle valeur
     */
    public function offsetSet($offset, $value): void
    {
        $this->attributes[$offset] = $value;
    }

    /**
     * Supprime une valeur à la position donnée
     *
     * @param TKey $offset Position à supprimer
     */
    public function offsetUnset($offset): void
    {
        unset($this->attributes[$offset]);
    }

    /**
     * Récupère un itérateur pour les attributs
     *
     * @return ArrayIterator<TKey, TValue> Itérateur sur les attributs
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->attributes);
    }

    /**
     * Appel dynamique d'une méthode pour modifier un attribut
     *
     * @param TKey              $method     Nom de la méthode
     * @param array{0: ?TValue} $parameters Paramètres de la méthode
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
     * Récupération dynamique d'un attribut
     *
     * @param TKey $key Clé de l'attribut
     *
     * @return TValue|null Valeur de l'attribut
     */
    public function __get($key)
    {
        return $this->value($key);
    }

    /**
     * Modification dynamique d'une valeur de l'attribut
     *
     * @param TKey   $key   Clé de l'attribut
     * @param TValue $value Nouvelle valeur
     */
    public function __set($key, $value): void
    {
        $this->offsetSet($key, $value);
    }

    /**
     * Vérifie dynamiquement si l'attribut existe
     *
     * @param TKey $key Clé de l'attribut
     *
     * @return bool True si l'attribut existe
     */
    public function __isset($key): bool
    {
        return $this->offsetExists($key);
    }

    /**
     * Suppression dynamique d'attributs
     *
     * @param TKey $key Clé de l'attribut à supprimer
     */
    public function __unset($key): void
    {
        $this->offsetUnset($key);
    }
}
