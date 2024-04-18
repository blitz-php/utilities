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

/**
 * Cette classe offre une fonction d'envahissement qui vous permettra de lire/écrire les propriétés privées d'un objet.
 * Elle vous permettra également de définir, d'obtenir et d'appeler des méthodes privées.
 *
 * @credit <a href="https://github.com/spatie/invade/blob/main/src/Invader.php">Spatie - Invade</a>
 *
 * @template T of object
 *
 * @mixin T
 */
class Invader
{
    /**
     * @param T $obj
     */
    public function __construct(public object $obj)
    {
    }

    /**
     * @param T $obj
     *
     * @return T
     */
    public static function make(object $obj)
    {
        return new self($obj);
    }

    public function __get(string $name): mixed
    {
        return (fn () => $this->{$name})->call($this->obj);
    }

    public function __set(string $name, mixed $value): void
    {
        (fn () => $this->{$name} = $value)->call($this->obj);
    }

    public function __call(string $name, array $params = []): mixed
    {
        return (fn () => $this->{$name}(...$params))->call($this->obj);
    }
}
