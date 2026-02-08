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
     * Constructeur de l'envahisseur
     *
     * @param T $obj L'objet à envahir
     */
    public function __construct(public object $obj)
    {
    }

    /**
     * Crée une nouvelle instance d'envahisseur
     *
     * @param T $obj L'objet à envahir
     */
    public static function make(object $obj): static
    {
        return new static($obj);
    }

    /**
     * Récupère la valeur d'une propriété (publique ou privée) de l'objet
     *
     * @param string $name Le nom de la propriété
     *
     * @return mixed La valeur de la propriété
     */
    public function __get(string $name): mixed
    {
        return (fn () => $this->{$name})->call($this->obj);
    }

    /**
     * Définit la valeur d'une propriété (publique ou privée) de l'objet
     *
     * @param string $name  Le nom de la propriété
     * @param mixed  $value La valeur à définir
     */
    public function __set(string $name, mixed $value): void
    {
        (fn () => $this->{$name} = $value)->call($this->obj);
    }

    /**
     * Appelle une méthode (publique ou privée) de l'objet
     *
     * @param string $name   Le nom de la méthode
     * @param array  $params Les paramètres à passer à la méthode
     *
     * @return mixed Le résultat de l'appel de la méthode
     */
    public function __call(string $name, array $params = []): mixed
    {
        return (fn () => $this->{$name}(...$params))->call($this->obj);
    }
}
