<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\Invade;

use Exception;

/**
 * Cette classe offre une fonction d'envahissement qui vous permettra de lire/écrire les propriétés statiques privées d'une classe.
 * Elle vous permettra également de définir, d'obtenir et d'appeler des méthodes privées statiques.
 *
 * @credit <a href="https://github.com/spatie/invade/blob/main/src/StaticInvader.php">Spatie - Invade</a>
 */
class StaticInvader
{
    private ?string $method = null;

    /**
     * @param class-string $className
     */
    public function __construct(public string $className)
    {
    }

    public static function make(string $className)
    {
        return new self($className);
    }

    public function get(string $name): mixed
    {
        return (fn () => static::${$name})->bindTo(null, $this->className)();
    }

    public function set(string $name, mixed $value): void
    {
        (fn ($value) => static::${$name} = $value)->bindTo(null, $this->className)($value);
    }

    public function method(string $name): self
    {
        $this->method = $name;

        return $this;
    }

    /**
     * @throws Exception
     */
    public function call(...$params): mixed
    {
        if ($this->method === null) {
            throw new Exception(
                'No method to be called. Use it like: invadeStatic(Foo::class)->method(\'bar\')->call()'
            );
        }

        return (static fn ($method) => static::{$method}(...$params))->bindTo(null, $this->className)($this->method);
    }
}
