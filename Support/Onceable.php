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

use Closure;
use ReflectionFunction;

class Onceable
{
    /**
     * Créer une nouvelle instance de Onceable.
     */
    public function __construct(public string $hash, public ?object $object, public $callable)
	{
	}

    /**
     * Tente de créer une nouvelle instance de type « onceable » à partir de la trace fournie.
     *
     * @param  array<int, array<string, mixed>>  $trace
     */
    public static function tryFromTrace(array $trace, callable $callable): ?static
    {
        if (null !== $hash = static::hashFromTrace($trace, $callable)) {
            $object = static::objectFromTrace($trace);

            return new static($hash, $object, $callable);
        }

		return null;
    }

    /**
     * Calcule l'objet de l'onceable à partir de la trace fournie, le cas échéant.
     *
     * @param  array<int, array<string, mixed>>  $trace
	 *
     * @return object|null
     */
    protected static function objectFromTrace(array $trace)
    {
        return $trace[1]['object'] ?? null;
    }

    /**
     * Calcule le hachage de l'objet Onceable à partir de la trace fournie.
     *
     * @param  array<int, array<string, mixed>>  $trace
     */
    protected static function hashFromTrace(array $trace, callable $callable): ?string
    {
        if (str_contains($trace[0]['file'] ?? '', 'eval()\'d code')) {
            return null;
        }

        $uses = array_map(
            static function (mixed $argument) {
                if (is_object($argument)) {
                    return spl_object_hash($argument);
                }

                return $argument;
            },
            $callable instanceof Closure ? (new ReflectionFunction($callable))->getClosureUsedVariables() : [],
        );

        $class = $callable instanceof Closure ? (new ReflectionFunction($callable))->getClosureCalledClass()?->getName() : null;

        $class ??= isset($trace[1]['class']) ? $trace[1]['class'] : null;

        return hash('xxh128', sprintf(
            '%s@%s%s:%s (%s)',
            $trace[0]['file'],
            $class ? $class.'@' : '',
            $trace[1]['function'],
            $trace[0]['line'],
            serialize($uses),
        ));
    }
}
