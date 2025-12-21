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
     * Constructeur de l'envahisseur statique
     *
     * @param class-string $className Le nom de la classe à envahir
     */
    public function __construct(public string $className)
    {
    }

    /**
     * Crée une nouvelle instance d'envahisseur statique
     *
     * @param class-string $className Le nom de la classe à envahir
     */
    public static function make(string $className): static
    {
        return new static($className);
    }

    /**
     * Récupère la valeur d'une propriété statique (publique ou privée) de la classe
     *
     * @param string $name Le nom de la propriété statique
     *
     * @return mixed La valeur de la propriété statique
     */
    public function get(string $name): mixed
    {
        return (fn () => static::${$name})->bindTo(null, $this->className)();
    }

    /**
     * Définit la valeur d'une propriété statique (publique ou privée) de la classe
     *
     * @param string $name  Le nom de la propriété statique
     * @param mixed  $value La valeur à définir
     */
    public function set(string $name, mixed $value): void
    {
        (fn ($value) => static::${$name} = $value)->bindTo(null, $this->className)($value);
    }

    /**
     * Sélectionne une méthode statique à appeler
     *
     * @param string $name Le nom de la méthode statique
     *
     * @return $this
     */
    public function method(string $name): self
    {
        $this->method = $name;

        return $this;
    }

    /**
     * Appelle la méthode statique précédemment sélectionnée
     *
     * @param mixed ...$params Les paramètres à passer à la méthode
     *
     * @return mixed Le résultat de l'appel de la méthode
     *
     * @throws Exception Si aucune méthode n'a été sélectionnée
     */
    public function call(...$params): mixed
    {
        if ($this->method === null) {
            throw new Exception(
                'Aucune méthode à appeler. Utilisez-la comme: invadeStatic(Foo::class)->method(\'bar\')->call()'
            );
        }

        return (static fn ($method) => static::{$method}(...$params))->bindTo(null, $this->className)($this->method);
    }
}
