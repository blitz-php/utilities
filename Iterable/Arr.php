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

use ArgumentCountError;
use ArrayAccess;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Contracts\Support\Enumerable;
use BlitzPHP\Contracts\Support\Jsonable;
use BlitzPHP\Traits\Macroable;
use BlitzPHP\Utilities\Exceptions\ItemNotFoundException;
use BlitzPHP\Utilities\Exceptions\MultipleItemsFoundException;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\String\Text;
use Closure;
use Exception;
use InvalidArgumentException;
use JsonSerializable;
use Traversable;
use WeakMap;

/**
 * Classe utilitaire pour manipuler les tableaux
 *
 * Fournit des méthodes statiques pour travailler avec des tableaux de manière fluide et expressive.
 */
class Arr
{
    use Macroable;

    public const SORT_ASC  = 1;
    public const SORT_DESC = 2;

    /**
     * Détermine si la valeur donnée est un tableau accessible.
     *
     * @param mixed $value
     */
    public static function accessible($value): bool
    {
        return is_array($value) || $value instanceof ArrayAccess;
    }

    /**
     * Détermine si la valeur est un tableau convertible.
     */
    public static function arrayable(mixed $value): bool
    {
        return is_array($value)
            || $value instanceof Arrayable
            || $value instanceof Traversable
            || $value instanceof Jsonable
            || $value instanceof JsonSerializable;
    }

    /**
     * Ajoute un élément à un tableau en utilisant la notation "point" s'il n'existe pas.
     *
     * @param string|int|float $key
     */
    public static function add(array $array, $key, mixed $value): array
    {
        if (null === static::get($array, $key)) {
            static::set($array, $key, $value);
        }

        return $array;
    }

    /**
     * Obtient un élément de tableau à partir d'un tableau en utilisant la notation "point".
     *
     * @throws InvalidArgumentException
     */
    public static function array(ArrayAccess|array $array, string|int|null $key, ?array $default = null): array
    {
        $value = self::get($array, $key, $default);

        if (! is_array($value)) {
            throw new InvalidArgumentException(
                sprintf('La valeur du tableau pour la clé [%s] doit être un tableau, %s trouvé.', $key, gettype($value))
            );
        }

        return $value;
    }

    /**
     * Obtient un élément booléen à partir d'un tableau en utilisant la notation "point".
     *
     * @throws InvalidArgumentException
     */
    public static function boolean(ArrayAccess|array $array, string|int|null $key, ?bool $default = null): bool
    {
        $value = Arr::get($array, $key, $default);

        if (! is_bool($value)) {
            throw new InvalidArgumentException(
                sprintf('La valeur du tableau pour la clé [%s] doit être un booléen, %s trouvé.', $key, gettype($value))
            );
        }

        return $value;
    }

    /**
     * Teste si un chemin donné existe dans $data.
     * Cette méthode utilise la même syntaxe de chemin que Arr::extract()
     *
     * La vérification des chemins qui pourraient cibler plus d'un élément
     * s'assure qu'au moins un élément correspondant existe.
     *
     * @param array  $data Les données à vérifier
     * @param string $path Le chemin à vérifier
     *
     * @see self::extract()
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::check
     */
    public static function check(array $data, string $path): bool
    {
        $results = self::extract($data, $path);
        if (! is_array($results)) {
            return false;
        }

        return count($results) > 0;
    }

    /**
     * Réduit un tableau de tableaux en un seul tableau.
     */
    public static function collapse(iterable $array): array
    {
        $results = [];

        foreach ($array as $values) {
            if ($values instanceof Collection) {
                $values = $values->all();
            } elseif (! is_array($values)) {
                continue;
            }

            $results[] = $values;
        }

        return array_merge([], ...$results);
    }

    /**
     * Crée un tableau associatif en utilisant `$keyPath` comme chemin pour construire ses clés, et éventuellement
     * `$valuePath` comme chemin pour obtenir les valeurs. Si `$valuePath` n'est pas spécifié, toutes les valeurs seront initialisées
     * à null (utile pour Arr::merge). Vous pouvez éventuellement regrouper les valeurs en fonction de ce qui est obtenu lorsque
     * le chemin est spécifié dans `$groupPath`.
     *
     * @param array  $data      Tableau depuis lequel extraire les clés et valeurs
     * @param string $keyPath   Une chaîne séparée par des points
     * @param string $valuePath Une chaîne séparée par des points
     * @param string $groupPath Une chaîne séparée par des points
     *
     * @return array Tableau combiné
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::combine
     */
    public static function combine(array $data, string $keyPath, ?string $valuePath = null, ?string $groupPath = null): array
    {
        if (empty($data)) {
            return [];
        }

        if (is_array($keyPath)) {
            $format = array_shift($keyPath);
            $keys   = self::format($data, $keyPath, $format);
        } else {
            $keys = self::extract($data, $keyPath);
        }
        if (empty($keys)) {
            return [];
        }

        if (! empty($valuePath) && is_array($valuePath)) {
            $format = array_shift($valuePath);
            $vals   = self::format($data, $valuePath, $format);
        } elseif (! empty($valuePath)) {
            $vals = self::extract($data, $valuePath);
        }
        if (empty($vals)) {
            $vals = array_fill(0, count($keys), null);
        }

        if (count($keys) !== count($vals)) {
            throw new Exception('Arr::combine() a besoin d\'un nombre égal de clés et de valeurs.');
        }

        if ($groupPath !== null) {
            $group = self::extract($data, $groupPath);
            if (! empty($group)) {
                $c = count($keys);

                for ($i = 0; $i < $c; $i++) {
                    if (! isset($group[$i])) {
                        $group[$i] = 0;
                    }
                    if (! isset($out[$group[$i]])) {
                        $out[$group[$i]] = [];
                    }
                    $out[$group[$i]][$keys[$i]] = $vals[$i];
                }

                return $out;
            }
        }
        if (empty($vals)) {
            return [];
        }

        return array_combine($keys, $vals);
    }

    /**
     * Détermine si un tableau contient exactement les clés et valeurs d'un autre.
     *
     * @param array $data   Les données dans lesquelles rechercher
     * @param array $needle Les valeurs à trouver dans $data
     *
     * @return bool true si $data contient $needle, false sinon
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::contains
     */
    public static function contains(array $data, array $needle): bool
    {
        if (empty($data) || empty($needle)) {
            return false;
        }
        $stack = [];

        while (! empty($needle)) {
            $key = key($needle);
            $val = $needle[$key];
            unset($needle[$key]);

            if (array_key_exists($key, $data) && is_array($val)) {
                $next = $data[$key];
                unset($data[$key]);

                if (! empty($val)) {
                    $stack[] = [$val, $next];
                }
            } elseif (! array_key_exists($key, $data) || $data[$key] !== $val) {
                return false;
            }

            if (empty($needle) && ! empty($stack)) {
                [$needle, $data] = array_pop($stack);
            }
        }

        return true;
    }

    /**
     * Effectue un produit cartésien des tableaux donnés, retournant toutes les permutations possibles.
     */
    public static function crossJoin(iterable ...$arrays): array
    {
        $results = [[]];

        foreach ($arrays as $index => $array) {
            $append = [];

            foreach ($results as $product) {
                foreach ($array as $item) {
                    $product[$index] = $item;

                    $append[] = $product;
                }
            }

            $results = $append;
        }

        return $results;
    }

    /**
     * Récupère un élément d'un tableau ou d'un objet en utilisant la notation "point".
     *
     * @param array|int|string|null $key
     *
     * @deprecated 1.9 utilisez Helpers::dataGet à la place
     */
    public static function dataGet(mixed $target, $key, mixed $default = null): mixed
    {
        return Helpers::dataGet($target, $key, $default);
    }

    /**
     * Compte les dimensions d'un tableau.
     * Ne considère que la dimension du premier élément du tableau.
     *
     * Si vous avez un tableau hétérogène ou inégal, pensez à utiliser Hash::maxDimensions()
     * pour obtenir les dimensions du tableau.
     *
     * @param array $data Tableau sur lequel compter les dimensions
     *
     * @return int Le nombre de dimensions dans $data
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::dimensions
     */
    public static function dimensions(array $data): int
    {
        if (empty($data)) {
            return 0;
        }
        reset($data);
        $depth = 1;

        while ($elem = array_shift($data)) {
            if (is_array($elem)) {
                $depth++;
                $data = &$elem;
            } else {
                break;
            }
        }

        return $depth;
    }

    /**
     * Divise un tableau en deux tableaux. Un avec les clés et l'autre avec les valeurs.
     */
    public static function divide(array $array): array
    {
        return [array_keys($array), array_values($array)];
    }

    /**
     * Aplatit un tableau associatif multidimensionnel avec des points.
     */
    public static function dot(iterable $array, string $prepend = ''): array
    {
        $results = [];

        $flatten = function ($data, $prefix) use (&$results, &$flatten): void {
            foreach ($data as $key => $value) {
                $newKey = $prefix.$key;

                if (is_array($value) && ! empty($value)) {
                    $flatten($value, $newKey.'.');
                } else {
                    $results[$newKey] = $value;
                }
            }
        };

        $flatten($array, $prepend);

        return $results;
    }

    /**
     * Convertit un tableau aplati en notation "point" en un tableau étendu.
     */
    public static function undot(iterable $array): array
    {
        $results = [];

        foreach ($array as $key => $value) {
            static::set($results, $key, $value);
        }

        return $results;
    }

    /**
     * Obtient tous les éléments du tableau donné sauf un tableau spécifié de clés.
     *
     * @param array|float|int|string $keys
     */
    public static function except(array $array, $keys): array
    {
        static::forget($array, $keys);

        return $array;
    }

    /**
     * Détermine si la clé donnée existe dans le tableau fourni.
     */
    public static function exists(array|ArrayAccess|Enumerable $array, int|float|string $key): bool
    {
        if ($array instanceof Enumerable) {
            return $array->has($key);
        }

        if ($array instanceof ArrayAccess) {
            return $array->offsetExists($key);
        }

        if (is_float($key)) {
            $key = (string) $key;
        }

        return array_key_exists($key, $array);
    }

    /**
     * Obtient les valeurs d'un tableau correspondant à l'expression $path.
     * L'expression de chemin est une expression séparée par des points, qui peut contenir un ensemble
     * de motifs et d'expressions :
     *
     * - `{n}` Correspond à toute clé numérique ou entier.
     * - `{s}` Correspond à toute clé de chaîne.
     * - `Foo` Correspond à toute clé avec la valeur exacte.
     *
     * Il existe un certain nombre d'opérateurs d'attributs :
     *
     *  - `=`, `!=` Égalité.
     *  - `>`, `<`, `>=`, `<=` Comparaison de valeurs.
     *  - `=/.../` Correspondance de motif d'expression régulière.
     *
     * Étant donné un ensemble de données de tableau User, à partir d'un appel `$User->find('all')` :
     *
     * - `1.User.name` Obtient le nom de l'utilisateur à l'index 1.
     * - `{n}.User.name` Obtient le nom de chaque utilisateur dans l'ensemble d'utilisateurs.
     * - `{n}.User[id]` Obtient le nom de chaque utilisateur avec une clé id.
     * - `{n}.User[id>=2]` Obtient le nom de chaque utilisateur avec une clé id supérieure ou égale à 2.
     * - `{n}.User[username=/^paul/]` Obtient les éléments User avec un nom d'utilisateur correspondant à `^paul`.
     *
     * @param array  $data Les données à extraire
     * @param string $path Le chemin à extraire
     *
     * @return array Un tableau des valeurs extraites. Retourne un tableau vide
     *               s'il n'y a pas de correspondances.
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::extract
     */
    public static function extract(array $data, string $path): array
    {
        if (empty($path)) {
            return $data;
        }

        // Chemins simples.
        if (! preg_match('/[{\[]/', $path)) {
            return (array) self::get($data, $path);
        }

        if (! str_contains($path, '[')) {
            $tokens = explode('.', $path);
        } else {
            $tokens = Text::tokenize($path, '.', '[', ']');
        }

        $_key = '__set_item__';

        $context = [$_key => [$data]];

        foreach ($tokens as $token) {
            $next = [];

            [$token, $conditions] = self::_splitConditions($token);

            foreach ($context[$_key] as $item) {
                foreach ((array) $item as $k => $v) {
                    if (self::_matchToken($k, $token)) {
                        $next[] = $v;
                    }
                }
            }

            // Filtre pour les attributs.
            if ($conditions) {
                $filter = [];

                foreach ($next as $item) {
                    if (is_array($item) && self::_matches($item, $conditions)) {
                        $filter[] = $item;
                    }
                }
                $next = $filter;
            }
            $context = [$_key => $next];
        }

        return $context[$_key];
    }

    /**
     * Développe un tableau plat en un tableau imbriqué.
     *
     * Par exemple, développe un tableau qui a été aplati avec `Hash::flatten()`
     * en un tableau multidimensionnel. Donc, `array('0.Foo.Bar' => 'Far')` devient
     * `array(array('Foo' => array('Bar' => 'Far')))`.
     *
     * @param array  $data      Tableau aplati
     * @param string $separator Le délimiteur utilisé
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::expand
     */
    public static function expand(array $data, string $separator = '.'): array
    {
        $result = [];

        foreach ($data as $flat => $value) {
            $keys  = explode($separator, $flat);
            $keys  = array_reverse($keys);
            $child = [$keys[0] => $value];
            array_shift($keys);

            foreach ($keys as $k) {
                $child = [$k => $child];
            }
            $result = self::merge($result, $child);
        }

        return $result;
    }

    /**
     * Filtre récursivement un ensemble de données.
     *
     * @param array    $data     Soit un tableau à filtrer, soit une valeur lors de l'utilisation d'un callback
     * @param callable $callback Une fonction pour filtrer les données. Par défaut
     *                           `self::_filter()` qui supprime toutes les valeurs vides non nulles.
     *
     * @return array Tableau filtré
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::filter
     */
    public static function filter(array $data, $callback = [self::class, '_filter']): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::filter($v, $callback);
            }
        }

        return array_filter($data, $callback);
    }

    /**
     * Retourne le premier élément d'un tableau passant un test de vérité donné.
	 *
	 * @template TKey
     * @template TValue
     * @template TFirstDefault
     *
     * @param  iterable<TKey, TValue>  $array
     * @param  (callable(TValue, TKey): bool)|null  $callback
     * @param  TFirstDefault|(\Closure(): TFirstDefault)  $default
	 *
     * @return TValue|TFirstDefault
     */
    public static function first(iterable $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if (null === $callback) {
            if (empty($array)) {
                return Helpers::value($default);
            }

            foreach ($array as $item) {
                return $item;
            }

            return Helpers::value($default);
        }

        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return $value;
            }
        }

        return Helpers::value($default);
    }

    /**
     * Aplatit un tableau multidimensionnel en un seul niveau.
     */
    public static function flatten(iterable $array, int|float $depth = INF): array
    {
        $result = [];

        foreach ($array as $item) {
            $item = $item instanceof Collection ? $item->all() : $item;

            if (! is_array($item)) {
                $result[] = $item;
            } else {
                $values = $depth === 1
                    ? array_values($item)
                    : static::flatten($item, $depth - 1);

                foreach ($values as $value) {
                    $result[] = $value;
                }
            }
        }

        return $result;
    }

    /**
     * Réduit un tableau multidimensionnel en une seule dimension, en utilisant un chemin de tableau délimité pour
     * la clé de chaque élément du tableau, c'est-à-dire array(array('Foo' => array('Bar' => 'Far'))) devient
     * array('0.Foo.Bar' => 'Far').)
     *
     * @param array  $data      Tableau à aplatir
     * @param string $separator Chaîne utilisée pour séparer les éléments de clé de tableau dans un chemin, par défaut '.'
     *
     * @credit http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::flatten
     */
    public static function flattenSeparator(array $data, string $separator = '.'): array
    {
        $result = [];
        $stack  = [];
        $path   = null;

        reset($data);

        while (! empty($data)) {
            $key     = key($data);
            $element = $data[$key];
            unset($data[$key]);

            if (is_array($element) && ! empty($element)) {
                if (! empty($data)) {
                    $stack[] = [$data, $path];
                }
                $data = $element;
                reset($data);
                $path .= $key . $separator;
            } else {
                $result[$path . $key] = $element;
            }

            if (empty($data) && ! empty($stack)) {
                [$data, $path] = array_pop($stack);
                reset($data);
            }
        }

        return $result;
    }

    /**
     * Obtient un élément flottant à partir d'un tableau en utilisant la notation "point".
     */
    public static function float(ArrayAccess|array $array, string|int|null $key, ?float $default = null): float
    {
        $value = Arr::get($array, $key, $default);

        if (! is_float($value)) {
            throw new InvalidArgumentException(
                sprintf('La valeur du tableau pour la clé [%s] doit être un flottant, %s trouvé.', $key, gettype($value))
            );
        }

        return $value;
    }

    /**
     * Supprime un ou plusieurs éléments de tableau d'un tableau donné en utilisant la notation "point".
     *
     * @param array                  $array
     * @param array|float|int|string $keys
     */
    public static function forget(&$array, $keys): void
    {
        $original = &$array;

        $keys = (array) $keys;

        if (count($keys) === 0) {
            return;
        }

        foreach ($keys as $key) {
            // si la clé exacte existe au niveau supérieur, supprimez-la
            if (static::exists($array, $key)) {
                unset($array[$key]);

                continue;
            }

            $parts = explode('.', $key);

            // nettoyer avant chaque passe
            $array = &$original;

            while (count($parts) > 1) {
                $part = array_shift($parts);

                if (isset($array[$part]) && static::accessible($array[$part])) {
                    $array = &$array[$part];
                } else {
                    continue 2;
                }
            }

            unset($array[array_shift($parts)]);
        }
    }

    /**
     * Retourne une série formatée de valeurs extraites de `$data`, en utilisant
     * `$format` comme format et `$paths` comme valeurs à extraire.
     *
     * Usage :
     *
     * {{{
     * $result = Hash::format($users, array('{n}.User.id', '{n}.User.name'), '%s : %s');
     * }}}
     *
     * La chaîne `$format` peut utiliser toutes les options de format que `vsprintf()` et `sprintf()` font.
     *
     * @param array  $data   Tableau source à partir duquel extraire les données
     * @param array  $paths  Un tableau contenant un ou plusieurs chemins de clé de style Hash::extract()
     * @param string $format Chaîne de format dans laquelle les valeurs seront insérées, voir sprintf()
     *
     * @return array|null Un tableau de chaînes extraites de `$path` et formatées avec `$format`
     *
     * @see http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::format
     * @see sprintf()
     * @see Tableau::extract()
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::format
     */
    public static function format(array $data, array $paths, string $format)
    {
        $extracted = [];
        $count     = count($paths);

        if (! $count) {
            return;
        }

        for ($i = 0; $i < $count; $i++) {
            $extracted[] = self::extract($data, $paths[$i]);
        }
        $out   = [];
        $data  = $extracted;
        $count = count($data[0]);

        $countTwo = count($data);

        for ($j = 0; $j < $count; $j++) {
            $args = [];

            for ($i = 0; $i < $countTwo; $i++) {
                if (array_key_exists($j, $data[$i])) {
                    $args[] = $data[$i][$j];
                }
            }
            $out[] = vsprintf($format, $args);
        }

        return $out;
    }

    /**
     * Obtient le tableau sous-jacent d'éléments à partir de l'argument donné.
     *
     * @template TKey of array-key = array-key
     * @template TValue = mixed
     *
     * @param array<TKey, TValue>|Enumerable<TKey, TValue>|Arrayable<TKey, TValue>|WeakMap<object, TValue>|Traversable<TKey, TValue>|Jsonable|JsonSerializable|object $items
     *
     * @return ($items is WeakMap ? list<TValue> : array<TKey, TValue>)
     *
     * @throws InvalidArgumentException
     */
    public static function from($items)
    {
        return match (true) {
            is_array($items) => $items,
            $items instanceof Enumerable => $items->all(),
            $items instanceof Arrayable => $items->toArray(),
            $items instanceof WeakMap => iterator_to_array($items, false),
            $items instanceof Traversable => iterator_to_array($items),
            $items instanceof Jsonable => json_decode($items->toJson(), true),
            $items instanceof JsonSerializable => (array) $items->jsonSerialize(),
            is_object($items) => (array) $items,
            default => throw new InvalidArgumentException('Les éléments ne peuvent pas être représentés par une valeur scalaire.'),
        };
    }

    /**
     * Obtient un élément d'un tableau en utilisant la notation "point".
     *
     * @param array|ArrayAccess $array Tableau de données sur lequel opérer
     * @param array|int|string  $key   Le chemin recherché. Soit une chaîne
     *                                 séparée par des points, soit un tableau de segments de chemin.
     *
     * @return mixed La valeur récupérée du tableau, ou null.
     */
    public static function get($array, $key, mixed $default = null): mixed
    {
        if (! static::accessible($array)) {
            return Helpers::value($default);
        }

        if (null === $key) {
            return $array;
        }

        if (is_array($key)) {
            $key = implode('.', $key);
        }

        if (static::exists($array, $key)) {
            return $array[$key];
        }

        if (! str_contains($key, '.')) {
            return $array[$key] ?? Helpers::value($default);
        }

        foreach (explode('.', $key) as $segment) {
            if (static::accessible($array) && static::exists($array, $segment)) {
                $array = $array[$segment];
            } else {
                return Helpers::value($default);
            }
        }

        return $array;
    }

    /**
     * Obtient une valeur récursivement d'un tableau en utilisant la notation "point".
     *
     * @return mixed
     */
    public static function getRecursive(?array $data, ?string $key = null)
    {
        if (empty($data)) {
            return null;
        }
        if (empty($key)) {
            return $data;
        }

        $key   = explode('.', $key);
        $count = count($key);

        if ($count === 1) {
            return $data[$key[0]] ?? null;
        }

        $sub_key = $key[1];

        for ($i = 2; $i < $count; $i++) {
            $sub_key .= '.' . $key[$i];
        }

        return self::getRecursive($data[$key[0]] ?? null, $sub_key);
    }

    /**
     * Vérifie si un ou plusieurs éléments existent dans un tableau en utilisant la notation "point".
     */
    public static function has(array|ArrayAccess $array, array|string $keys): bool
    {
        $keys = (array) $keys;

        if (! $array || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            $subKeyArray = $array;

            if (static::exists($array, $key)) {
                continue;
            }

            foreach (explode('.', $key) as $segment) {
                if (static::accessible($subKeyArray) && static::exists($subKeyArray, $segment)) {
                    $subKeyArray = $subKeyArray[$segment];
                } else {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Détermine si toutes les clés existent dans un tableau en utilisant la notation "point".
     */
    public static function hasAll(array|ArrayAccess $array, array|string $keys): bool
    {
        $keys = (array) $keys;

        if (! $array || $keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (! static::has($array, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Détermine si l'une des clés existe dans un tableau en utilisant la notation "point".
     */
    public static function hasAny(array|ArrayAccess $array, array|string $keys): bool
    {
        if (null === $keys) {
            return false;
        }

        $keys = (array) $keys;

        if (! $array) {
            return false;
        }

        if ($keys === []) {
            return false;
        }

        foreach ($keys as $key) {
            if (static::has($array, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si tous les éléments passent le test de vérité donné.
     *
     * @param (callable(mixed, array-key): bool) $callback
     */
    public static function every(iterable $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if (!$callback($value, $key)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Insère $values dans un tableau avec le $path donné. Vous pouvez utiliser
     * `{n}` et `{s}` pour insérer $data plusieurs fois.
     *
     * @param array  $data   Les données dans lesquelles insérer
     * @param string $path   Le chemin où insérer
     * @param array  $values Les valeurs à insérer
     *
     * @return array Les données avec $values insérées
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::insert
     */
    public static function insert(array $data, $path, $values = null)
    {
        if (! str_contains($path, '[')) {
            $tokens = explode('.', $path);
        } else {
            $tokens = Text::tokenize($path, '.', '[', ']');
        }
        if (! str_contains($path, '{') && ! str_contains($path, '[')) {
            return self::_simpleOp('insert', $data, $tokens, $values);
        }

        $token    = array_shift($tokens);
        $nextPath = implode('.', $tokens);

        [$token, $conditions] = self::_splitConditions($token);

        foreach ($data as $k => $v) {
            if (self::_matchToken($k, $token)) {
                if ($conditions && self::_matches($v, $conditions)) {
                    $data[$k] = array_merge($v, $values);

                    continue;
                }
                if (! $conditions) {
                    $data[$k] = self::insert($v, $nextPath, $values);
                }
            }
        }

        return $data;
    }

    /**
     * Obtient un élément entier à partir d'un tableau en utilisant la notation "point".
     *
     * @throws InvalidArgumentException
     */
    public static function integer(ArrayAccess|array $array, string|int|null $key, ?int $default = null): int
    {
        $value = Arr::get($array, $key, $default);

        if (! is_int($value)) {
            throw new InvalidArgumentException(
                sprintf('La valeur du tableau pour la clé [%s] doit être un entier, %s trouvé.', $key, gettype($value))
            );
        }

        return $value;
    }

    /**
     * Détermine si un tableau est associatif.
     *
     * Un tableau est "associatif" s'il n'a pas de clés numériques séquentielles commençant par zéro.
     */
    public static function isAssoc(array $array): bool
    {
        return ! array_is_list($array);
    }

    /**
     * Détermine si un tableau est une liste.
     *
     * Un tableau est une "liste" si toutes les clés du tableau sont des entiers séquentiels
     * commençant par 0 sans espace entre eux.
     */
    public static function isList(array $array): bool
    {
        return array_is_list($array);
    }

    /**
     * Joint tous les éléments en utilisant une chaîne. Les derniers éléments peuvent utiliser une colle séparée.
     */
    public static function join(array $array, string $glue, string $finalGlue = ''): string
    {
        if ($finalGlue === '') {
            return implode($glue, $array);
        }

        if (count($array) === 0) {
            return '';
        }

        if (count($array) === 1) {
            return end($array);
        }

        $finalItem = array_pop($array);

        return implode($glue, $array) . $finalGlue . $finalItem;
    }

    /**
     * Clé un tableau associatif par un champ ou en utilisant un callback.
     *
     * @param array|callable|string $keyBy
     */
    public static function keyBy(array $array, $keyBy): array
    {
        return Collection::make($array)->keyBy($keyBy)->all();
    }

    /**
     * Préfixe les noms de clés d'un tableau associatif.
     */
    public static function prependKeysWith(array $array, string $prependWith): array
    {
        return static::mapWithKeys($array, static fn ($item, $key) => [$prependWith . $key => $item]);
    }

    /**
     * Retourne le dernier élément d'un tableau passant un test de vérité donné.
     *
     * @template TKey
     * @template TValue
     * @template TLastDefault
     *
     * @param iterable<TKey, TValue> $array
     * @param (callable(TValue, TKey): bool)|null $callback
     * @param TLastDefault|(\Closure(): TLastDefault) $default
     *
     * @return TValue|TLastDefault
     */
    public static function last(iterable $array, ?callable $callback = null, mixed $default = null): mixed
    {
        if (null === $callback) {
            return empty($array) ? Helpers::value($default) : end($array);
        }

        return static::first(array_reverse($array, true), $callback, $default);
    }

    /**
     * Exécute une fonction de mappage sur chacun des éléments du tableau.
     */
    public static function map(array $array, callable $callback): array
    {
        $keys = array_keys($array);

        try {
            $items = array_map($callback, $array, $keys);
        } catch (ArgumentCountError) {
            $items = array_map($callback, $array);
        }

        return array_combine($keys, $items);
    }

    /**
     * Exécute une fonction de mappage sur chaque groupe imbriqué d'éléments.
     *
     * @template TKey
     * @template TValue
     *
     * @param array<TKey, array>         $array
     * @param callable(mixed...): TValue $callback
     *
     * @return array<TKey, TValue>
     */
    public static function mapSpread(array $array, callable $callback): array
    {
        return static::map($array, static function ($chunk, $key) use ($callback) {
            $chunk[] = $key;

            return $callback(...$chunk);
        });
    }

    /**
     * Exécute un mappage associatif sur chacun des éléments.
     *
     * Le callback doit retourner un tableau associatif avec une seule paire clé/valeur.
     *
     * @template TKey
     * @template TValue
     * @template TMapWithKeysKey of array-key
     * @template TMapWithKeysValue
     *
     * @param array<TKey, TValue>                                               $array
     * @param callable(TValue, TKey): array<TMapWithKeysKey, TMapWithKeysValue> $callback
     */
    public static function mapWithKeys(array $array, callable $callback): array
    {
        $result = [];

        foreach ($array as $key => $value) {
            $assoc = $callback($value, $key);

            foreach ($assoc as $mapKey => $mapValue) {
                $result[$mapKey] = $mapValue;
            }
        }

        return $result;
    }

    /**
     * Compte les dimensions de *tous* les éléments du tableau. Utile pour trouver le maximum
     * nombre de dimensions dans un tableau mixte.
     *
     * @param array $data Tableau sur lequel compter les dimensions
     *
     * @return int Le nombre maximum de dimensions dans $data
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::maxDimensions
     */
    public static function maxDimensions(array $data): int
    {
        $depth = [];
        if (is_array($data) && reset($data) !== false) {
            foreach ($data as $value) {
                $depth[] = self::dimensions((array) $value) + 1;
            }
        }

        return max($depth);
    }

    /**
     * Cette fonction peut être considérée comme un hybride entre `array_merge` et `array_merge_recursive` de PHP.
     *
     * La différence entre cette méthode et les méthodes intégrées est que si une clé de tableau contient un autre tableau, alors
     * Hash::merge() se comportera de manière récursive (contrairement à `array_merge`). Mais il n'agira pas récursivement pour
     * les clés qui contiennent des valeurs scalaires (contrairement à `array_merge_recursive`).
     *
     * Note : Cette fonction fonctionnera avec un nombre illimité d'arguments et convertira les paramètres non-tableaux en tableaux.
     *
     * @param array $data  Tableau à fusionner
     * @param mixed $merge Tableau à fusionner. L'argument et tous les arguments suivants seront convertis en tableau lors de la fusion
     *
     * @return array Tableau fusionné
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::merge
     */
    public static function merge(array $data, $merge)
    {
        $args   = func_get_args();
        $return = current($args);

        while (($arg = next($args)) !== false) {
            foreach ((array) $arg as $key => $val) {
                if (! empty($return[$key]) && is_array($return[$key]) && is_array($val)) {
                    $return[$key] = self::merge($return[$key], $val);
                } elseif (is_int($key) && isset($return[$key])) {
                    $return[] = $val;
                } else {
                    $return[$key] = $val;
                }
            }
        }

        return $return;
    }

    /**
     * Vérifie si toutes les valeurs du tableau sont numériques
     *
     * @param array $data Le tableau à vérifier
     *
     * @return bool true si les valeurs sont numériques, false sinon
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::numeric
     */
    public static function numeric(array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        return $data === array_filter($data, 'is_numeric');
    }

    /**
     * Obtient un sous-ensemble des éléments du tableau donné.
     */
    public static function only(array $array, array|string $keys): array
    {
        return array_intersect_key($array, array_flip((array) $keys));
    }

    /**
     * Partitionne le tableau en deux tableaux en utilisant le callback donné.
     *
     * @template TKey of array-key
     * @template TValue of mixed
     *
     * @param iterable<TKey, TValue>       $array
     * @param callable(TValue, TKey): bool $callback
     *
     * @return array<int<0, 1>, array<TKey, TValue>>
     */
    public static function partition(iterable $array, callable $callback): array
    {
        $passed = [];
        $failed = [];

        foreach ($array as $key => $item) {
            if ($callback($item, $key)) {
                $passed[$key] = $item;
            } else {
                $failed[$key] = $item;
            }
        }

        return [$passed, $failed];
    }

    /**
     * Extrait un tableau de valeurs d'un tableau.
     *
     * @param array|Closure|int|string|null $value
     * @param array|Closure|string|null     $key
     */
    public static function pluck(iterable $array, $value, $key = null): array
    {
        $results = [];

        [$value, $key] = static::explodePluckParameters($value, $key);

        foreach ($array as $item) {
            $itemValue = $value instanceof Closure
                ? $value($item)
                : Helpers::dataGet($item, $value);

            // Si la clé est "null", nous ajouterons simplement la valeur au tableau et continuerons
            // la boucle. Sinon, nous indexerons le tableau en utilisant la valeur de la clé que nous
            // avons reçue du développeur. Ensuite, nous retournerons le tableau final.
            if (null === $key) {
                $results[] = $itemValue;
            } else {
                $itemKey = $key instanceof Closure
                    ? $key($item)
                    : Helpers::dataGet($item, $key);

                if (is_object($itemKey) && method_exists($itemKey, '__toString')) {
                    $itemKey = (string) $itemKey;
                }

                $results[$itemKey] = $itemValue;
            }
        }

        return $results;
    }

    /**
     * Explose les arguments "value" et "key" passés à "pluck".
     *
     * @param array|Closure|string      $value
     * @param array|Closure|string|null $key
     */
    protected static function explodePluckParameters($value, $key): array
    {
        $value = is_string($value) ? explode('.', $value) : $value;

        $key = is_string($key) ? explode('.', $key) : $key;

        return [$value, $key];
    }

    /**
     * Ajoute un élément au début d'un tableau.
     */
    public static function prepend(array $array, mixed $value, int|string|null $key = null): array
    {
        if (func_num_args() == 2) {
            array_unshift($array, $value);
        } else {
            $array = [$key => $value] + $array;
        }

        return $array;
    }

    /**
     * Obtient une valeur du tableau et la supprime.
     */
    public static function pull(array &$array, int|string $key, mixed $default = null): mixed
    {
        $value = static::get($array, $key, $default);

        static::forget($array, $key);

        return $value;
    }

    /**
     * Ajoute un élément à un tableau en utilisant la notation "point".
     */
    public static function push(ArrayAccess|array &$array, string|int|null $key, mixed ...$values): array
    {
        $target = static::array($array, $key, []);

        array_push($target, ...$values);

        return static::set($array, $key, $target);
    }

    /**
     * Convertit le tableau en une chaîne de requête.
     */
    public static function query(array $array): string
    {
        return http_build_query($array, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * Obtient une ou plusieurs valeurs aléatoires d'un tableau.
     *
     * @throws InvalidArgumentException
     */
    public static function random(array $array, ?int $number = null, bool $preserveKeys = false): mixed
    {
        $requested = null === $number ? 1 : $number;

        $count = count($array);

        if ($requested > $count) {
            throw new InvalidArgumentException(
                "Vous avez demandé {$requested} éléments, mais seulement {$count} éléments sont disponibles."
            );
        }

        if ($array === [] || ($number !== null && $number <= 0)) {
            return null === $number ? null : [];
        }

        $keys = (array) array_rand($array, $number ?? 1);

        if (null === $number) {
            return $array[$keys[0]];
        }

        if ((int) $number === 0) {
            return [];
        }

        $results = [];

        if ($preserveKeys) {
            foreach ($keys as $key) {
                $results[$key] = $array[$key];
            }
        } else {
            foreach ($keys as $key) {
                $results[] = $array[$key];
            }
        }

        return $results;
    }

    /**
     * Filtre le tableau en utilisant la négation du callback donné.
     */
    public static function reject(array $array, callable $callback): array
    {
        return static::where($array, static fn ($value, $key) => ! $callback($value, $key));
    }

    /**
     * Supprime les données correspondant à $path du tableau $data.
     * Vous pouvez utiliser `{n}` et `{s}` pour supprimer plusieurs éléments
     * de $data.
     *
     * @param array  $data Les données sur lesquelles opérer
     * @param string $path Une expression de chemin à utiliser pour supprimer
     *
     * @return array Le tableau modifié
     *
     * @credit CakePHP - http://book.cakephp.org/2.0/en/core-utility-libraries/hash.html#Hash::remove
     */
    public static function remove(array $data, $path)
    {
        if (! str_contains($path, '[')) {
            $tokens = explode('.', $path);
        } else {
            $tokens = Text::tokenize($path, '.', '[', ']');
        }

        if (! str_contains($path, '{') && ! str_contains($path, '[')) {
            return self::_simpleOp('remove', $data, $tokens);
        }

        $token    = array_shift($tokens);
        $nextPath = implode('.', $tokens);

        [$token, $conditions] = self::_splitConditions($token);

        foreach ($data as $k => $v) {
            $match = self::_matchToken($k, $token);
            if ($match && is_array($v)) {
                if ($conditions && self::_matches($v, $conditions)) {
                    unset($data[$k]);

                    continue;
                }
                $data[$k] = self::remove($v, $nextPath);
                if (empty($data[$k])) {
                    unset($data[$k]);
                }
            } elseif ($match) {
                unset($data[$k]);
            }
        }

        return $data;
    }

    /**
     * Sélectionne un tableau de valeurs d'un tableau.
     */
    public static function select(array $array, array|string $keys): array
    {
        $keys = static::wrap($keys);

        return static::map($array, static function ($item) use ($keys) {
            $result = [];

            foreach ($keys as $key) {
                if (Arr::accessible($item) && Arr::exists($item, $key)) {
                    $result[$key] = $item[$key];
                } elseif (is_object($item) && isset($item->{$key})) {
                    $result[$key] = $item->{$key};
                }
            }

            return $result;
        });
    }

    /**
     * Définit un élément de tableau à une valeur donnée en utilisant la notation "point".
     *
     * Si aucune clé n'est donnée à la méthode, le tableau entier sera remplacé.
     */
    public static function set(array &$array, int|string|null $key, mixed $value): array
    {
        if (null === $key) {
            return $array = $value;
        }

        $keys = explode('.', $key);

        foreach ($keys as $i => $key) {
            if (count($keys) === 1) {
                break;
            }

            unset($keys[$i]);

            // Si la clé n'existe pas à cette profondeur, nous créerons simplement un tableau vide
            // pour contenir la valeur suivante, nous permettant de créer les tableaux pour contenir les valeurs finales
            // à la bonne profondeur. Ensuite, nous continuerons à creuser dans le tableau.
            if (! isset($array[$key]) || ! is_array($array[$key])) {
                $array[$key] = [];
            }

            $array = &$array[$key];
        }

        $array[array_shift($keys)] = $value;

        return $array;
    }

    /**
     * Définit une valeur récursivement dans un tableau en utilisant la notation "point".
     */
    public static function setRecursive(array &$data, ?string $key = null, mixed $value = null): void
    {
        if (empty($data) && empty($key)) {
            return;
        }

        $key   = explode('.', $key);
        $count = count($key);

        if ($count === 1) {
            $data[$key[0]] = $value;

            return;
        }

        $sub_key = $key[1];

        for ($i = 2; $i < $count; $i++) {
            $sub_key .= '.' . $key[$i];
        }

        if (! isset($data[$key[0]])) {
            $data[$key[0]] = [];
        }

        self::setRecursive($data[$key[0]], $sub_key, $value);
    }

    /**
     * Mélange le tableau donné et retourne le résultat.
     */
    public static function shuffle(array $array, ?int $seed = null): array
    {
        if (null === $seed) {
            shuffle($array);
        } else {
            mt_srand($seed);
            shuffle($array);
            mt_srand();
        }

        return $array;
    }

    /**
     * Obtient le premier élément du tableau, mais seulement si exactement un élément existe. Sinon, lance une exception.
     *
     * @param (callable(mixed, array-key): array)|null $callback
     *
     * @throws ItemNotFoundException
     * @throws MultipleItemsFoundException
     */
    public static function sole(array $array, ?callable $callback = null)
    {
        if ($callback) {
            $array = static::where($array, $callback);
        }

        $count = count($array);

        if ($count === 0) {
            throw new ItemNotFoundException('Aucun élément trouvé.');
        }

        if ($count > 1) {
            throw new MultipleItemsFoundException($count, 'Plusieurs éléments trouvés.');
        }

        return static::first($array);
    }

    /**
     * Détermine si certains éléments passent le test de vérité donné.
     *
     * @param (callable(mixed, array-key): bool) $callback
     */
    public static function some(iterable $array, callable $callback): bool
    {
        foreach ($array as $key => $value) {
            if ($callback($value, $key)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Trie le tableau en utilisant le callback ou la notation "point" donnée.
     *
     * @param array|callable|string|null $callback
     */
    public static function sort(iterable $array, $callback = null): array
    {
        return Collection::make($array)->sortBy($callback)->all();
    }

    /**
     * Trie le tableau en ordre décroissant en utilisant le callback ou la notation "point" donnée.
     *
     * @param array|callable|string|null $callback
     */
    public static function sortDesc(iterable $array, $callback = null): array
    {
        return Collection::make($array)->sortByDesc($callback)->all();
    }

    /**
     * Trie un tableau dans l'ordre ASC/DESC relativement à une position spécifique
     *
     * @param array  $data      Tableau à trier
     * @param string $field     Chaîne décrivant la position du champ
     * @param int    $direction Direction du tri basée sur les constantes de classe
     *
     * @return array Tableau trié
     */
    public static function sortField(array $data, string $field, int $direction = self::SORT_ASC): array
    {
        usort($data, static function ($a, $b) use ($field, $direction) {
            $cmp1 = self::_getSortField_($a, $field);
            $cmp2 = self::_getSortField_($b, $field);

            if ($cmp1 === $cmp2) {
                return 0;
            }
            if ($direction === self::SORT_ASC) {
                return ($cmp1 < $cmp2) ? -1 : 1;
            }

            return ($cmp1 < $cmp2) ? 1 : -1;
        });

        return $data;
    }

    /**
     * Méthode interne pour obtenir la valeur d'un champ pour le tri
     *
     * @param mixed $element L'élément à traiter
     * @param string $field Le chemin du champ
     * @return mixed
     */
    private static function _getSortField_($element, $field)
    {
        $field = explode('.', $field);

        foreach ($field as $key) {
            if (is_object($element) && isset($element->{$key})) {
                $element = $element->{$key};
            } elseif (isset($element[$key])) {
                $element = $element[$key];
            } else {
                break;
            }
        }

        return $element;
    }

    /**
     * Trie récursivement un tableau par clés et valeurs.
     */
    public static function sortRecursive(array $array, int $options = SORT_REGULAR, bool $descending = false): array
    {
        foreach ($array as &$value) {
            if (is_array($value)) {
                $value = static::sortRecursive($value, $options, $descending);
            }
        }

        if (! array_is_list($array)) {
            $descending
                    ? krsort($array, $options)
                    : ksort($array, $options);
        } else {
            $descending
                    ? rsort($array, $options)
                    : sort($array, $options);
        }

        return $array;
    }

    /**
     * Trie récursivement un tableau par clés et valeurs en ordre décroissant.
     */
    public static function sortRecursiveDesc(array $array, int $options = SORT_REGULAR): array
    {
        return static::sortRecursive($array, $options, true);
    }

    /**
     * Obtient un élément chaîne à partir d'un tableau en utilisant la notation "point".
     *
     * @throws InvalidArgumentException
     */
    public static function string(ArrayAccess|array $array, string|int|null $key, ?string $default = null): string
    {
        $value = Arr::get($array, $key, $default);

        if (! is_string($value)) {
            throw new InvalidArgumentException(
                sprintf('La valeur du tableau pour la clé [%s] doit être une chaîne, %s trouvé.', $key, gettype($value))
            );
        }

        return $value;
    }

    /**
     * Prend les premiers ou derniers {$limit} éléments d'un tableau.
     */
    public static function take(array $array, int $limit): array
    {
        if ($limit < 0) {
            return array_slice($array, $limit, abs($limit));
        }

        return array_slice($array, 0, $limit);
    }

    /**
     * Compare récursivement deux tableaux associatifs et retourne la différence sous forme de nouveau tableau.
     * Retourne les clés qui existent dans `$original` mais pas dans `$compareWith`.
     */
    public static function diffRecursive(array $original, array $compareWith): array
    {
        $difference = [];

        if ($original === []) {
            return [];
        }

        if ($compareWith === []) {
            return $original;
        }

        foreach ($original as $originalKey => $originalValue) {
            if ($originalValue === []) {
                continue;
            }

            if (is_array($originalValue)) {
                $diffArrays = [];

                if (isset($compareWith[$originalKey]) && is_array($compareWith[$originalKey])) {
                    $diffArrays = self::diffRecursive($originalValue, $compareWith[$originalKey]);
                } else {
                    $difference[$originalKey] = $originalValue;
                }

                if ($diffArrays !== []) {
                    $difference[$originalKey] = $diffArrays;
                }
            } elseif (is_string($originalValue) && ! array_key_exists($originalKey, $compareWith)) {
                $difference[$originalKey] = $originalValue;
            }
        }

        return $difference;
    }

    /**
     * Compte récursivement toutes les clés.
     */
    public static function countRecursive(array $array, int $counter = 0): int
    {
        foreach ($array as $value) {
            if (is_array($value)) {
                $counter = self::countRecursive($value, $counter);
            }

            $counter++;
        }

        return $counter;
    }

    /**
     * Compile conditionnellement les classes d'un tableau en une liste de classes CSS.
     */
    public static function toCssClasses(array $array): string
    {
        $classList = static::wrap($array);

        $classes = [];

        foreach ($classList as $class => $constraint) {
            if (is_numeric($class)) {
                $classes[] = $constraint;
            } elseif ($constraint) {
                $classes[] = $class;
            }
        }

        return implode(' ', $classes);
    }

    /**
     * Compile conditionnellement les styles d'un tableau en une liste de styles.
     */
    public static function toCssStyles(array $array): string
    {
        $styleList = static::wrap($array);

        $styles = [];

        foreach ($styleList as $class => $constraint) {
            if (is_numeric($class)) {
                $styles[] = Text::finish($constraint, ';');
            } elseif ($constraint) {
                $styles[] = Text::finish($class, ';');
            }
        }

        return implode(' ', $styles);
    }

    /**
     * Joint un tableau associatif dans une chaîne.
     *
     * La clé et la valeur de chaque entrée sont jointes par "=", et toutes les entrées sont jointes par le séparateur spécifié et un espace supplémentaire (pour la lisibilité).
     * Les valeurs sont citées si nécessaire.
     *
     * Exemple :
     *
     *     Arr::toString(["foo" => "abc", "bar" => true, "baz" => "a b c"], ",")
     *     // => 'foo=abc, bar, baz="a b c"'
     *
     * @credit <a href="symfony.com">Symfony - Symfony\Component\HttpFoundation::toString</a>
     */
    public static function toString(array $array, string $separator, bool $space = true): string
    {
        $parts = [];

        foreach ($array as $name => $value) {
            if (true === $value) {
                $parts[] = $name;
            } else {
                $parts[] = $name . '=' . Text::quote($value);
            }
        }

        return implode($separator . ($space ? ' ' : ''), $parts);
    }

    /**
     * Filtre le tableau en utilisant le callback donné.
     */
    public static function where(array $array, callable $callback): array
    {
        return array_filter($array, $callback, ARRAY_FILTER_USE_BOTH);
    }

    /**
     * Filtre les éléments où la valeur n'est pas null.
     */
    public static function whereNotNull(array $array): array
    {
        return static::where($array, static fn ($value) => null !== $value);
    }

    /**
     * Si la valeur donnée n'est pas un tableau et n'est pas null, l'enveloppe dans un.
     */
    public static function wrap(mixed $value): array
    {
        if (null === $value) {
            return [];
        }

        return is_array($value) ? $value : [$value];
    }

    /**
     * Fonction de callback pour le filtrage.
     *
     * @param mixed $var Tableau à filtrer
     */
    protected static function _filter($var): bool
    {
        return (bool) ($var === 0 || $var === '0' || ! empty($var));
    }

    /**
     * Divise les conditions de token
     *
     * @param string $token Le token à diviser
     *
     * @return array array(token, conditions) avec le token divisé
     */
    protected static function _splitConditions(string $token): array
    {
        $conditions = false;
        $position   = strpos($token, '[');
        if ($position !== false) {
            $conditions = substr($token, $position);
            $token      = substr($token, 0, $position);
        }

        return [$token, $conditions];
    }

    /**
     * Vérifie une clé contre un token.
     *
     * @param string $key   La clé dans le tableau recherché
     * @param string $token Le token à comparer
     */
    protected static function _matchToken(string $key, string $token): bool
    {
        if ($token === '{n}') {
            return is_numeric($key);
        }
        if ($token === '{s}') {
            return is_string($key);
        }
        if (is_numeric($token)) {
            return $key === $token;
        }

        return $key === $token;
    }

    /**
     * Vérifie si $data correspond aux modèles d'attributs
     *
     * @param array  $data     Tableau de données à comparer
     * @param string $selector Les modèles à comparer
     *
     * @return bool Adéquation de l'expression
     */
    protected static function _matches(array $data, string $selector): bool
    {
        preg_match_all(
            '/(\[ (?P<attr>[^=><!]+?) (\s* (?P<op>[><!]?[=]|[><]) \s* (?P<val>(?:\/.*?\/ | [^\]]+)) )? \])/x',
            $selector,
            $conditions,
            PREG_SET_ORDER
        );

        foreach ($conditions as $cond) {
            $attr = $cond['attr'];
            $op   = $cond['op'] ?? null;
            $val  = $cond['val'] ?? null;

            // Test de présence.
            if (empty($op) && empty($val) && ! isset($data[$attr])) {
                return false;
            }
            // Attribut vide = échec.
            if (! (isset($data[$attr]) || array_key_exists($attr, $data))) {
                return false;
            }
            $prop = null;
            if (isset($data[$attr])) {
                $prop = $data[$attr];
            }
            $isBool = is_bool($prop);
            if ($isBool && is_numeric($val)) {
                $prop = $prop ? '1' : '0';
            } elseif ($isBool) {
                $prop = $prop ? 'true' : 'false';
            }
            // Correspondances de motifs et autres opérateurs.
            if ($op === '=' && $val && $val[0] === '/') {
                if (! preg_match($val, $prop)) {
                    return false;
                }
            } elseif (
                ($op === '=' && $prop !== $val)
                || ($op === '!=' && $prop === $val)
                || ($op === '>' && $prop <= $val)
                || ($op === '<' && $prop >= $val)
                || ($op === '>=' && $prop < $val)
                || ($op === '<=' && $prop > $val)
            ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Effectue une opération d'insertion/suppression simple.
     *
     * @param string $op     L'opération à effectuer
     * @param array  $data   Les données sur lesquelles opérer
     * @param array  $path   Le chemin sur lequel travailler
     * @param mixed  $values Les valeurs à insérer lors des insertions
     *
     * @return array|void données
     */
    protected static function _simpleOp(string $op, array $data, array $path, $values = null)
    {
        $_list = &$data;

        $count = count($path);
        $last  = $count - 1;

        foreach ($path as $i => $key) {
            if (is_numeric($key) && (int) $key > 0 || $key === '0') {
                $key = (int) $key;
            }
            if ($op === 'insert') {
                if ($i === $last) {
                    $_list[$key] = $values;

                    return $data;
                }
                if (! isset($_list[$key])) {
                    $_list[$key] = [];
                }
                $_list = &$_list[$key];
                if (! is_array($_list)) {
                    $_list = [];
                }
            } elseif ($op === 'remove') {
                if ($i === $last) {
                    unset($_list[$key]);

                    return $data;
                }
                if (! isset($_list[$key])) {
                    return $data;
                }
                $_list = &$_list[$key];
            }
        }
    }
}
