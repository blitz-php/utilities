<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities;

use BackedEnum;
use BlitzPHP\Traits\Mixins\HigherOrderTapProxy;
use BlitzPHP\Utilities\Invade\Invader;
use BlitzPHP\Utilities\Invade\StaticInvader;
use BlitzPHP\Utilities\Iterable\Arr;
use BlitzPHP\Utilities\Iterable\Collection;
use Closure;
use DomainException;
use Exception;
use HTMLPurifier;
use HTMLPurifier_Config;
use InvalidArgumentException;
use RuntimeException;
use Throwable;
use UnitEnum;

/**
 * Classe utilitaire fournissant des fonctions d'aide courantes
 *
 * Cette classe contient une variété de méthodes statiques pour manipuler des données,
 * gérer les environnements, effectuer des validations et d'autres opérations courantes.
 */
class Helpers
{
    /**
     * Configurations prédéfinies pour HTMLPurifier
     *
     * @var array<string, array>
     */
    private static array $purifierConfigs = [
        'comment' => [
            'HTML.Allowed'             => 'p,a[href|title],abbr[title],acronym[title],b,strong,blockquote[cite],code,em,i,strike',
            'AutoFormat.AutoParagraph' => true,
            'AutoFormat.Linkify'       => true,
            'AutoFormat.RemoveEmpty'   => true,
        ],
        'default' => [
            // Configuration par défaut vide
        ],
    ];

    /**
     * Cache des versions PHP vérifiées
     *
     * @var array<string, bool>
     */
    private static array $phpVersionCache = [];

    /**
     * Instance du escaper Laminas pour éviter les instanciations multiples
     *
     * @var \Laminas\Escaper\Escaper|null
     */
    private static $escaper;

    /**
     * Récupère la classe "basename" de l'objet/classe donné.
     *
     * @param object|string $class L'objet ou le nom de classe
     *
     * @return string Le nom court de la classe (sans le namespace)
     *
     * @see https://github.com/laravel/framework/blob/8.x/src/Illuminate/Support/helpers.php
     */
    public static function classBasename(object|string $class): string
    {
        $class = is_object($class) ? get_class($class) : $class;

        return basename(str_replace('\\', '/', $class));
    }

    /**
     * Renvoie tous les traits utilisés par une classe, ses classes parentes et le trait de leurs traits.
     *
     * @param object|string $class L'objet ou le nom de classe
     *
     * @return array Liste de tous les traits utilisés récursivement
     */
    public static function classUsesRecursive(object|string $class): array
    {
        if (is_object($class)) {
            $class = get_class($class);
        }

        $results = [];

        foreach (array_reverse(class_parents($class)) + [$class => $class] as $class) {
            $results += self::traitUsesRecursive($class);
        }

        return array_unique($results);
    }

    /**
     * Nettoie une URL en supprimant les références aux répertoires parents et en normalisant le format
     *
     * @param string $url L'URL à nettoyer
     *
     * @return string L'URL nettoyée
     */
    public static function cleanUrl(string $url): string
    {
        $urlParts = parse_url($url);
        if ($urlParts === false) {
            return $url;
        }

        $result = '';

        if (! empty($urlParts['scheme'])) {
            $result .= $urlParts['scheme'] . '://';
        }

        if (! empty($urlParts['user'])) {
            $result .= $urlParts['user'];
            if (! empty($urlParts['pass'])) {
                $result .= ':' . $urlParts['pass'];
            }
            $result .= '@';
        }

        if (! empty($urlParts['host'])) {
            $result .= $urlParts['host'];
        }

        if (! empty($urlParts['port'])) {
            $result .= ':' . $urlParts['port'];
        }

        if (! empty($urlParts['path'])) {
            $path = $urlParts['path'];

            $path = str_replace('/./', '/', $path);

            while (substr_count($path, '../')) {
                $path = preg_replace('!/([\\w\\d]+/\\.\\.)!', '', $path);
            }

            $result .= $path;
        }

        if (! empty($urlParts['query'])) {
            $result .= '?' . $urlParts['query'];
        }

        if (! empty($urlParts['fragment'])) {
            $result .= '#' . $urlParts['fragment'];
        }

        return $result;
    }

    /**
     * Créez une collection à partir de la valeur donnée.
     *
     * @param mixed $value La valeur à transformer en collection
     *
     * @return Collection Une nouvelle instance de Collection
     */
    public static function collect(mixed $value = null): Collection
    {
        return new Collection($value);
    }

    /**
     * Remplit les données manquantes dans un tableau ou un objet en utilisant la notation "point".
     *
     * @param mixed        &$target La cible à remplir (par référence)
     * @param array|string $key     La clé sous forme de tableau ou de chaîne avec notation point
     * @param mixed        $value   La valeur à définir
     *
     * @return mixed La cible modifiée
     */
    public static function dataFill(mixed &$target, array|string $key, mixed $value): mixed
    {
        return static::dataSet($target, $key, $value, false);
    }

    /**
     * Supprime un élément d'un tableau ou d'un objet en utilisant la notation "point".
     *
     * @param mixed                 &$target La cible à modifier (par référence)
     * @param array|int|string|null $key     La clé à supprimer
     *
     * @return mixed La cible modifiée
     */
    public static function dataForget(mixed &$target, array|int|string|null $key): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*' && Arr::accessible($target)) {
            if ($segments) {
                foreach ($target as &$inner) {
                    static::dataForget($inner, $segments);
                }
            }
        } elseif (Arr::accessible($target)) {
            if ($segments && Arr::exists($target, $segment)) {
                static::dataForget($target[$segment], $segments);
            } else {
                Arr::forget($target, $segment);
            }
        } elseif (is_object($target)) {
            if ($segments && isset($target->{$segment})) {
                static::dataForget($target->{$segment}, $segments);
            } elseif (isset($target->{$segment})) {
                unset($target->{$segment});
            }
        }

        return $target;
    }

    /**
     * Détermine si une clé/propriété existe sur un tableau ou un objet en utilisant la notation "point".
     *
     * @param mixed                 $target La cible à vérifier
     * @param array|int|string|null $key    La clé à vérifier
     *
     * @return bool True si la clé existe, false sinon
     */
    public static function dataHas(mixed $target, $key): bool
    {
        if (null === $key || $key === []) {
            return false;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $segment) {
            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && property_exists($target, $segment)) {
                $target = $target->{$segment};
            } else {
                return false;
            }
        }

        return true;
    }

    /**
     * Récupère un élément d'un tableau ou d'un objet en utilisant la notation "point".
     *
     * @param mixed                 $target  La cible à partir de laquelle récupérer
     * @param array|int|string|null $key     La clé à récupérer
     * @param mixed                 $default La valeur par défaut si la clé n'existe pas
     *
     * @return mixed La valeur récupérée
     */
    public static function dataGet(mixed $target, array|int|string|null $key, mixed $default = null): mixed
    {
        if (null === $key) {
            return $target;
        }

        $key = is_array($key) ? $key : explode('.', $key);

        foreach ($key as $i => $segment) {
            unset($key[$i]);

            if (null === $segment) {
                return $target;
            }

            if ($segment === '*') {
                if ($target instanceof Collection) {
                    $target = $target->all();
                } elseif (! is_iterable($target)) {
                    return static::value($default);
                }

                $result = [];

                foreach ($target as $item) {
                    $result[] = static::dataGet($item, $key);
                }

                return in_array('*', $key, true) ? Arr::collapse($result) : $result;
            }

            $segment = match ($segment) {
                '\*'       => '*',
                '\{first}' => '{first}',
                '{first}'  => array_key_first(is_array($target) ? $target : static::collect($target)->all()),
                '\{last}'  => '{last}',
                '{last}'   => array_key_last(is_array($target) ? $target : static::collect($target)->all()),
                default    => $segment,
            };

            if (Arr::accessible($target) && Arr::exists($target, $segment)) {
                $target = $target[$segment];
            } elseif (is_object($target) && isset($target->{$segment})) {
                $target = $target->{$segment};
            } else {
                return static::value($default);
            }
        }

        return $target;
    }

    /**
     * Définit un élément sur un tableau ou un objet en utilisant la notation point.
     *
     * @param mixed        &$target   La cible à modifier (par référence)
     * @param array|string $key       La clé sous forme de tableau ou de chaîne avec notation point
     * @param mixed        $value     La valeur à définir
     * @param bool         $overwrite Si true, écrase les valeurs existantes
     *
     * @return mixed La cible modifiée
     */
    public static function dataSet(mixed &$target, array|string $key, mixed $value, bool $overwrite = true): mixed
    {
        $segments = is_array($key) ? $key : explode('.', $key);

        if (($segment = array_shift($segments)) === '*') {
            if (! Arr::accessible($target)) {
                $target = [];
            }

            if ($segments) {
                foreach ($target as &$inner) {
                    static::dataSet($inner, $segments, $value, $overwrite);
                }
            } elseif ($overwrite) {
                foreach ($target as &$inner) {
                    $inner = $value;
                }
            }
        } elseif (Arr::accessible($target)) {
            if ($segments) {
                if (! Arr::exists($target, $segment)) {
                    $target[$segment] = [];
                }

                static::dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite || ! Arr::exists($target, $segment)) {
                $target[$segment] = $value;
            }
        } elseif (is_object($target)) {
            if ($segments) {
                if (! isset($target->{$segment})) {
                    $target->{$segment} = [];
                }

                static::dataSet($target->{$segment}, $segments, $value, $overwrite);
            } elseif ($overwrite || ! isset($target->{$segment})) {
                $target->{$segment} = $value;
            }
        } else {
            $target = [];

            if ($segments) {
                static::dataSet($target[$segment], $segments, $value, $overwrite);
            } elseif ($overwrite) {
                $target[$segment] = $value;
            }
        }

        return $target;
    }

    /**
     * Méthode d'assistance pour générer des avertissements d'obsolescence
     *
     * @param string $message    Le message à afficher comme avertissement d'obsolescence.
     * @param int    $stackFrame Le cadre de pile à inclure dans l'erreur. Par défaut à 1
     *                           car cela devrait pointer vers le code de l'application/du plugin.
     *
     * @return void
     */
    public static function deprecationWarning(string $message, int $stackFrame = 1)
    {
        if (! (error_reporting() & E_USER_DEPRECATED)) {
            return;
        }

        $trace = debug_backtrace();
        if (isset($trace[$stackFrame])) {
            $frame = $trace[$stackFrame];
            $frame += ['file' => '[internal]', 'line' => '??'];

            $message = sprintf(
                "%s - %s, line: %s\n" .
                ' You can disable deprecation warnings by setting `Error.errorLevel` to' .
                ' `E_ALL & ~E_USER_DEPRECATED` in your config/app.php.',
                $message,
                $frame['file'],
                $frame['line']
            );
        }

        @trigger_error($message, E_USER_DEPRECATED);
    }

    /**
     * Garantit qu'une extension se trouve à la fin d'un nom de fichier
     *
     * @param string $path Le chemin du fichier
     * @param string $ext  L'extension à garantir
     *
     * @return string Le chemin avec l'extension garantie
     */
    public static function ensureExt(string $path, string $ext = 'php'): string
    {
        if ($ext) {
            $ext = '.' . preg_replace('#^\.#', '', $ext);

            if (substr($path, -strlen($ext)) !== $ext) {
                $path .= $ext;
            }
        }

        return trim($path);
    }

    /**
     * Renvoie une valeur scalaire pour la valeur donnée qui pourrait être une énumération.
     *
     * @template TValue
     * @template TDefault
     *
     * @param TValue                              $value   La valeur à convertir
     * @param callable(TValue): TDefault|TDefault $default Valeur par défaut ou callback
     *
     * @return ($value is empty ? TDefault : mixed) La valeur scalaire ou la valeur par défaut
     */
    public static function enumValue($value, $default = null)
    {
        return match (true) {
            $value instanceof BackedEnum => $value->value,
            $value instanceof UnitEnum   => $value->name,

            default => $value ?? static::value($default),
        };
    }

    /**
     * Obtient une variable d'environnement à partir des sources disponibles et fournit une émulation
     * pour les variables d'environnement non prises en charge ou incohérentes (c'est-à-dire DOCUMENT_ROOT sur
     * IIS, ou SCRIPT_NAME en mode CGI). Expose également quelques coutumes supplémentaires
     * informations sur l'environnement.
     *
     * @param string     $key     Nom de la variable d'environnement
     * @param mixed|null $default Spécifiez une valeur par défaut au cas où la variable d'environnement n'est pas définie.
     *
     * @return string Paramétrage des variables d'environnement.
     *
     * @credit CakePHP - http://book.cakephp.org/4.0/en/core-libraries/global-constants-and-functions.html#env
     */
    public static function env(string $key, $default = null)
    {
        if ($key === 'HTTPS') {
            if (isset($_SERVER['HTTPS'])) {
                return ! empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
            }

            return str_starts_with((string) self::env('SCRIPT_URI'), 'https://');
        }

        if ($key === 'SCRIPT_NAME' && self::env('CGI_MODE') && isset($_ENV['SCRIPT_URL'])) {
            $key = 'SCRIPT_URL';
        }

        $val = $_SERVER[$key] ?? $_ENV[$key] ?? null;
        if ($val === null && getenv($key) !== false) {
            $val = getenv($key);
        }

        if ($key === 'REMOTE_ADDR' && $val === self::env('SERVER_ADDR')) {
            $addr = self::env('HTTP_PC_REMOTE_ADDR');
            if ($addr !== null) {
                $val = $addr;
            }
        }

        if ($val !== null) {
            return $val;
        }

        switch ($key) {
            case 'DOCUMENT_ROOT':
                $name     = (string) self::env('SCRIPT_NAME');
                $filename = (string) self::env('SCRIPT_FILENAME');
                $offset   = 0;
                if (! strpos($name, '.php')) {
                    $offset = 4;
                }

                return substr($filename, 0, -(strlen($name) + $offset));

            case 'PHP_SELF':
                return str_replace((string) self::env('DOCUMENT_ROOT'), '', (string) self::env('SCRIPT_FILENAME'));

            case 'CGI_MODE':
                return PHP_SAPI === 'cgi';
        }

        return $default;
    }

    /**
     * Effectue un simple échappement automatique des données pour des raisons de sécurité.
     * Pourrait envisager de rendre cela plus complexe à une date ultérieure.
     *
     * Si $data est une chaîne, il suffit alors de l'échapper et de la renvoyer.
     * Si $data est un tableau, alors il boucle dessus, s'échappant de chaque
     * 'valeur' des paires clé/valeur.
     *
     * Valeurs de contexte valides : html, js, css, url, attr, raw, null
     *
     * @param array|string $data     Les données à échapper
     * @param string|null  $context  Le contexte d'échappement
     * @param string|null  $encoding L'encodage à utiliser
     *
     * @return array|string Les données échappées
     *
     * @throws InvalidArgumentException Si le contexte n'est pas valide
     */
    public static function esc($data, ?string $context = 'html', ?string $encoding = null)
    {
        if (is_array($data)) {
            foreach ($data as $key => &$value) {
                $value = self::esc($value, $context);
            }
        }

        if (is_string($data)) {
            $context = strtolower($context);

            // Fournit un moyen de NE PAS échapper aux données depuis
            // cela pourrait être appelé automatiquement par la bibliothèque View.
            if (empty($context) || $context === 'raw') {
                return $data;
            }

            if (! in_array($context, ['html', 'js', 'css', 'url', 'attr'], true)) {
                throw new InvalidArgumentException("Contexte d'échappement invalide fourni.");
            }

            if ($context === 'attr') {
                $method = 'escapeHtmlAttr';
            } else {
                $method = 'escape' . ucfirst($context);
            }

            if (self::$escaper === null) {
                self::$escaper = new \Laminas\Escaper\Escaper($encoding);
            } elseif ($encoding && self::$escaper->getEncoding() !== $encoding) {
                self::$escaper = new \Laminas\Escaper\Escaper($encoding);
            }

            $data = self::$escaper->{$method}($data);
        }

        return $data;
    }

    /**
     * Recherche l'URL de base de l'application indépendamment de la configuration de l'utilisateur
     *
     * @return string L'URL de base de l'application
     */
    public static function findBaseUrl(): string
    {
        if (! isset($_SERVER['SERVER_ADDR'])) {
            return 'http://localhost:' . ($_SERVER['SERVER_PORT'] ?? '80');
        }

        $server_addr = $_SERVER['HTTP_HOST'] ?? ((str_contains($_SERVER['SERVER_ADDR'], ':')) ? '[' . $_SERVER['SERVER_ADDR'] . ']' : $_SERVER['SERVER_ADDR']);
        $scheme      = 'http';

        if (
            (! empty($_SERVER['HTTPS']) && strtolower($_SERVER['HTTPS']) !== 'off')
            || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
            || (! empty($_SERVER['HTTP_FRONT_END_HTTPS']) && strtolower($_SERVER['HTTP_FRONT_END_HTTPS']) !== 'off')
        ) {
            $scheme = 'https';
        }

        return trim($scheme . '://' . $server_addr . dirname(substr($_SERVER['SCRIPT_NAME'], 0, strpos($_SERVER['SCRIPT_NAME'], basename($_SERVER['SCRIPT_FILENAME'])))), '/\\') ?: '/';
    }

    /**
     * Méthode pratique pour htmlspecialchars.
     *
     * @param mixed       $text    Texte à envelopper dans htmlspecialchars. Fonctionne également avec des tableaux et des objets.
     *                             Les tableaux seront mappés et tous leurs éléments seront échappés. Les objets seront transtypés s'ils
     *                             implémenter une méthode `__toString`. Sinon, le nom de la classe sera utilisé.
     *                             Les autres types de scalaires seront renvoyés tels quels.
     * @param bool        $double  Encodez les entités html existantes.
     * @param string|null $charset Jeu de caractères à utiliser lors de l'échappement. La valeur par défaut est la valeur de configuration dans `mb_internal_encoding()` ou 'UTF-8'.
     *
     * @return mixed Texte enveloppé.
     *
     * @credit CackePHP (https://cakephp.org)
     */
    public static function h($text, bool $double = true, ?string $charset = null): mixed
    {
        if (is_string($text)) {
            // optimize for strings
        } elseif (is_array($text)) {
            $texts = [];

            foreach ($text as $k => $t) {
                $texts[$k] = static::h($t, $double, $charset);
            }

            return $texts;
        } elseif (is_object($text)) {
            if (method_exists($text, '__toString')) {
                $text = (string) $text;
            } else {
                $text = '(object)' . get_class($text);
            }
        } elseif ($text === null || is_scalar($text)) {
            return $text;
        }

        static $defaultCharset = false;
        if ($defaultCharset === false) {
            $defaultCharset = mb_internal_encoding();
            if ($defaultCharset === null) {
                $defaultCharset = 'UTF-8';
            }
        }
        if (is_string($double)) {
            self::deprecationWarning(
                'Passing charset string for 2nd argument is deprecated. ' .
                'Use the 3rd argument instead.'
            );
            $charset = $double;
            $double  = true;
        }

        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, $charset ?: $defaultCharset, $double);
    }

    /**
     * Récupère le premier élément d'un tableau. Utile pour l'enchaînement de méthodes.
     *
     * @param array $array Le tableau à traiter
     *
     * @return mixed Le premier élément du tableau
     */
    public static function head(array $array): mixed
    {
        return reset($array);
    }

    /**
     * Crée un envahisseur pour accéder aux propriétés et méthodes privées
     *
     * @template T of object
     *
     * @param class-string|T $object L'objet ou le nom de classe à envahir
     *
     * @return Invader<T>|StaticInvader L'instance d'envahisseur
     */
    public static function invade(object|string $object): Invader|StaticInvader
    {
        if (is_object($object)) {
            return new Invader($object);
        }

        return new StaticInvader($object);
    }

    /**
     * Obtenez l'adresse IP que le client utilise ou dit qu'il utilise.
     *
     * @return string L'adresse IP du client
     */
    public static function ipAddress(): string
    {
        // Obtenez une véritable IP de visiteur derrière le réseau CloudFlare
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $_SERVER['REMOTE_ADDR']    = $_SERVER['HTTP_CF_CONNECTING_IP'];
            $_SERVER['HTTP_CLIENT_IP'] = $_SERVER['HTTP_CF_CONNECTING_IP'];
        }

        $client  = $_SERVER['HTTP_CLIENT_IP'] ?? '';
        $forward = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        $remote  = $_SERVER['REMOTE_ADDR'] ?? '';

        if (filter_var($client, FILTER_VALIDATE_IP)) {
            $ip = $client;
        } elseif (filter_var($forward, FILTER_VALIDATE_IP)) {
            $ip = $forward;
        } elseif (filter_var($remote, FILTER_VALIDATE_IP)) {
            $ip = $remote;
        } else {
            $ip = $_SERVER['SERVER_ADDR'] ?? '';
            if (empty($ip) || $ip === '::1') {
                $ip = gethostname();
                if ($ip) {
                    $ip = gethostbyname($ip);
                } else {
                    $ip = $_SESSION['HTTP_HOST'] ?? '127.0.0.1';
                }
            }
        }

        return $ip;
    }

    /**
     * Vérifie si un chemin donné est un chemin absolu ou relatif
     *
     * @param string $path    Le chemin à vérifier
     * @param bool   $verbose Si true, lance une exception en cas d'erreur
     *
     * @return bool True si le chemin est absolu, false sinon
     *
     * @throws DomainException Si le chemin contient des caractères non imprimables ou n'est pas valide
     */
    public static function isAbsolutePath(string $path, bool $verbose = false): bool
    {
        if (! ctype_print($path)) {
            if ($verbose) {
                throw new DomainException('Le chemin ne peut PAS contenir de caractères non imprimables ou être vide');
            }

            return false;
        }

        // Emballage(s) facultatif(s).
        $regExp = '%^(?<wrappers>(?:[[:print:]]{2,}://)*)';
        // Préfixe racine facultatif.
        $regExp .= '(?<root>(?:[[:alpha:]]:/|/)?)';
        // Chemin réel.
        $regExp .= '(?<path>(?:[[:print:]]*))$%';
        $parts = [];

        if (! preg_match($regExp, $path, $parts)) {
            if ($verbose) {
                throw new DomainException(sprintf('Le chemin n\'est PAS valide, a été donné %s', $path));
            }

            return false;
        }

        return '' !== $parts['root'];
    }

    /**
     * Vérifie si un chemin donné est une URL absolue ou relative
     *
     * @param string $path Le chemin à vérifier
     *
     * @return bool True si le chemin est une URL absolue, false sinon
     */
    public static function isAbsoluteUrl(string $path): bool
    {
        return preg_match('#^(?:[a-z+]+:)?//#i', $path) !== false;
    }

    /**
     * Vérifie si la requête est exécutée en AJAX
     *
     * @return bool True si la requête est AJAX, false sinon
     */
    public static function isAjaxRequest(): bool
    {
        return ! empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    }

    /**
     * Vérifiez si une chaîne est encodée en Base64.
     *
     * @param string $input La chaîne à vérifier
     *
     * @return bool True si la chaîne est encodée en Base64, false sinon
     */
    public static function isBase64Encoded(string $input): bool
    {
        if (! preg_match('/^[a-zA-Z0-9\/+]*={0,2}$/', $input)) {
            return false;
        }

        $decoded = base64_decode($input, true);

        return $decoded !== false && base64_encode($decoded) === $input;
    }

    /**
     * Testez pour voir si une demande a été faite à partir de la ligne de commande.
     *
     * @return bool True si l'exécution est en CLI, false sinon
     */
    public static function isCli(): bool
    {
        return PHP_SAPI === 'cli' || defined('STDIN');
    }

    /**
     * Vérifie si l'utilisateur a une connexion internet active.
     *
     * @param array $endpoints Liste des endpoints à vérifier (host:port)
     * @param int   $timeout   Timeout en secondes pour chaque connexion
     *
     * @return bool True si connecté à Internet, false sinon
     */
    public static function isConnected(array $endpoints = ['www.google.com:80', '8.8.8.8:53', '1.1.1.1:53'], int $timeout = 2): bool
    {
        foreach ($endpoints as $endpoint) {
            [$host, $port] = explode(':', $endpoint . ':80'); // Port par défaut 80
            $connected     = @fsockopen($host, (int) $port, $errno, $errstr, $timeout);
            if ($connected) {
                fclose($connected);

                return true;
            }
        }

        return false;
    }

    /**
     * Tester si une application s'exécute en local ou en ligne
     *
     * @return bool True si en ligne, false si en local
     */
    public static function isOnline(): bool
    {
        $host = explode(':', $_SERVER['HTTP_HOST'] ?? '')[0];

        if (empty($host)) {
            return false;
        }
        $localPatterns = [
            '/\.dev$/i',
            '/\.test$/i',
            '/\.lab$/i',
            '/\.loc(al)?$/i',
            '/\.localhost$/i',
        ];

        foreach ($localPatterns as $pattern) {
            if (preg_match($pattern, $host)) {
                return false;
            }
        }

        return ! in_array($host, ['localhost', '127.0.0.1'], true)
            && ! preg_match('/^192\.168/', $host);
    }

    /**
     * Détermine si la version actuelle de PHP est égale ou supérieure à la valeur fournie
     *
     * @param string $version La version PHP à vérifier
     *
     * @return bool True si la version PHP est suffisante, false sinon
     */
    public static function isPhp(string $version): bool
    {
        if (! isset(self::$phpVersionCache[$version])) {
            self::$phpVersionCache[$version] = version_compare(PHP_VERSION, $version, '>=');
        }

        return self::$phpVersionCache[$version];
    }

    /**
     * Tests d'inscriptibilité des fichiers
     *
     * is_writable() renvoie TRUE sur les serveurs Windows lorsque vous ne pouvez vraiment pas écrire
     * le fichier, basé sur l'attribut en lecture seule. is_writable() n'est pas non plus fiable
     * sur les serveurs Unix si safe_mode est activé.
     *
     * @param string $file Le chemin du fichier à vérifier
     *
     * @return bool True si le fichier est réellement accessible en écriture, false sinon
     *
     * @throws Exception
     *
     * @see https://bugs.php.net/bug.php?id=54709
     */
    public static function isReallyWritable(string $file): bool
    {
        // Si nous sommes sur un serveur Unix avec safe_mode désactivé, nous appelons is_writable
        if (DIRECTORY_SEPARATOR === '/' || ! ini_get('safe_mode')) {
            return is_writable($file);
        }

        /* Pour les serveurs Windows et les installations safe_mode "on", nous allons en fait
         * écrire un fichier puis le lire. Bah...
         */
        if (is_dir($file)) {
            $file = rtrim($file, '/') . '/' . bin2hex(random_bytes(16));
            if (($fp = @fopen($file, 'ab')) === false) {
                return false;
            }

            fclose($fp);
            @chmod($file, 0o777);
            @unlink($file);

            return true;
        }
        if (! is_file($file) || ($fp = @fopen($file, 'ab')) === false) {
            return false;
        }

        fclose($fp);

        return true;
    }

    /**
     * Récupère le dernier élément d'un tableau.
     *
     * @param array $array Le tableau à traiter
     *
     * @return mixed Le dernier élément du tableau
     */
    public static function last(array $array): mixed
    {
        return end($array);
    }

    /**
     * Séparez l'espace de noms du nom de classe.
     *
     * Couramment utilisé comme `list($namespace, $className) = Helpers::namespaceSplit($class);`.
     *
     * @param string $class Le nom complet de la classe, ie `BlitzPHP\Core\App`.
     *
     * @return list<string> Tableau avec 2 index. 0 => namespace, 1 => nom de la classe.
     */
    public static function namespaceSplit(string $class): array
    {
        $pos = strrpos($class, '\\');
        if ($pos === false) {
            return ['', $class];
        }

        return [substr($class, 0, $pos), substr($class, $pos + 1)];
    }

    /**
     * Jolie fonction de commodité d'impression JSON.
     *
     * Dans les terminaux, cela agira de la même manière que json_encode() avec JSON_PRETTY_PRINT directement, lorsqu'il n'est pas exécuté sur cli
     * enveloppera également les balises <pre> autour de la sortie de la variable donnée. Similaire à pr().
     *
     * Cette fonction renvoie la même variable qui a été transmise.
     *
     * @param mixed $var Variable à imprimer.
     *
     * @return mixed le même $var qui a été passé à cette fonction
     *
     * @see pr()
     */
    public static function pj($var)
    {
        $template = (PHP_SAPI !== 'cli' && PHP_SAPI !== 'phpdbg') ? '<pre class="pj">%s</pre>' : "\n%s\n\n";
        printf($template, trim(json_encode($var, JSON_PRETTY_PRINT)));

        return $var;
    }

    /**
     * Enregistre une nouvelle configuration pour HTMLPurifier
     *
     * @param string $name   Le nom de la configuration
     * @param array  $config La configuration
     */
    public static function registerPurifierConfig(string $name, array $config): void
    {
        self::$purifierConfigs[$name] = $config;
    }

    /**
     * Purifiez l'entrée à l'aide de la classe autonome HTMLPurifier.
     * Utilisez facilement plusieurs configurations de purificateur.
     *
     * @param list<string>|string $dirty_html Le HTML à purifier
     * @param false|string        $config     La configuration à utiliser
     * @param string              $charset    Le charset à utiliser
     *
     * @return list<string>|string Le HTML purifié
     *
     * @throws InvalidArgumentException Si la configuration n'est pas trouvée
     */
    public static function purify($dirty_html, $config = false, string $charset = 'UTF-8')
    {
        if (is_array($dirty_html)) {
            $clean_html = [];

            foreach ($dirty_html as $key => $val) {
                $clean_html[$key] = self::purify($val, $config);
            }

            return $clean_html;
        }

        $purifierConfig = HTMLPurifier_Config::createDefault();
        $purifierConfig->set('Core.Encoding', $charset);
        $purifierConfig->set('HTML.Doctype', 'XHTML 1.0 Strict');

        if ($config !== false) {
            if (isset(self::$purifierConfigs[$config])) {
                foreach (self::$purifierConfigs[$config] as $key => $value) {
                    $purifierConfig->set($key, $value);
                }
            } else {
                throw new InvalidArgumentException(
                    'La configuration HTMLPurifier intitulée "' .
                    htmlspecialchars((string) $config, ENT_QUOTES, $charset) .
                    '" est introuvable.'
                );
            }
        }

        $purifier = new HTMLPurifier($purifierConfig);

        return $purifier->purify($dirty_html);
    }

    /**
     * Supprimer les caractères invisibles
     *
     * Cela empêche de prendre en sandwich des caractères nuls
     * entre les caractères ascii, comme Java\0script.
     *
     * @param string $str         La chaîne à nettoyer
     * @param bool   $url_encoded Si true, nettoie également les caractères encodés dans l'URL
     *
     * @return string La chaîne nettoyée
     */
    public static function removeInvisibleCharacters(string $str, bool $url_encoded = true): string
    {
        $non_displayables = [];

        if ($url_encoded) {
            $non_displayables[] = '/%0[0-8bcef]/i';    // url encoded 00-08, 11, 12, 14, 15
            $non_displayables[] = '/%1[0-9a-f]/i';    // url encoded 16-31
            $non_displayables[] = '/%7f/i';    // url encoded 127
        }

        $non_displayables[] = '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/S';    // 00-08, 11, 12, 14-31, 127

        do {
            $str = preg_replace($non_displayables, '', $str, -1, $count);
        } while ($count);

        return $str;
    }

    /**
     * Réessayez une opération un certain nombre de fois.
     *
     * @param array|int     $times             Le nombre de tentatives ou un tableau de délais d'attente
     * @param callable      $callback          La fonction à réessayer
     * @param Closure|int   $sleepMilliseconds Le temps d'attente entre les tentatives
     * @param callable|null $when              Condition pour continuer à réessayer
     *
     * @return mixed Le résultat de la fonction callback
     *
     * @throws RuntimeException Si toutes les tentatives échouent
     *
     * @credit <a href="http://laravel.com/">Laravel</a>
     */
    public static function retry(array|int $times, callable $callback, Closure|int $sleepMilliseconds = 0, ?callable $when = null): mixed
    {
        $maxAttempts = is_array($times) ? count($times) + 1 : $times;
        $backoff     = is_array($times) ? $times : [];

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $callback($attempt);
            } catch (Exception $e) {
                if ($attempt === $maxAttempts || ($when && ! $when($e))) {
                    throw $e;
                }

                $sleep = $backoff[$attempt - 1] ?? $sleepMilliseconds;
                if ($sleep) {
                    $sleepValue = static::value($sleep, $attempt, $e);
                    usleep($sleepValue * 1000);
                }
            }
        }

        throw new RuntimeException("La boucle de réessai s'est terminée de manière inattendue.");
    }

    /**
     * Chaîner les attributs à utiliser dans les balises HTML.
     *
     * Fonction d'assistance utilisée pour convertir une chaîne, un tableau ou un objet
     * d'attributs à une chaîne.
     *
     * @param array|object|string $attributes Les attributs à transformer
     * @param bool                $js         Si true, formatte pour JavaScript
     *
     * @return string Les attributs sous forme de chaîne
     */
    public static function stringifyAttributes($attributes, bool $js = false): string
    {
        $atts = '';

        if (empty($attributes)) {
            return $atts;
        }

        if (is_string($attributes)) {
            return ' ' . $attributes;
        }

        $attributes = (array) $attributes;

        foreach ($attributes as $key => $val) {
            $atts .= ($js) ? $key . '=' . self::esc($val, 'js') . ',' : ' ' . $key . '="' . self::esc($val, 'attr') . '"';
        }

        return rtrim($atts, ',');
    }

    /**
     * Supprime de manière récursive les barres obliques de toutes les valeurs d'un tableau
     *
     * @param array|string $values Tableau de valeurs pour supprimer les barres obliques
     *
     * @return mixed Ce qui est retourné en appelant stripslashes
     *
     * @credit http://book.cakephp.org/2.0/en/core-libraries/global-constants-and-functions.html#stripslashes_deep
     */
    public static function stripslashesDeep($values)
    {
        if (is_array($values)) {
            foreach ($values as $key => $value) {
                $values[$key] = self::stripslashesDeep($value);
            }
        } else {
            $values = stripslashes($values);
        }

        return $values;
    }

    /**
     * Appelez la Closure donnée avec cette instance puis renvoyez l'instance.
     *
     * @param mixed         $value    La valeur à passer au callback
     * @param callable|null $callback Le callback à exécuter
     *
     * @return mixed La valeur originale ou une instance de HigherOrderTapProxy
     */
    public static function tap(mixed $value, ?callable $callback = null): mixed
    {
        if (null === $callback) {
            return new HigherOrderTapProxy($value);
        }

        $callback($value);

        return $value;
    }

    /**
     * Lève l'exception donnée si la condition donnée est vraie.
     *
     * @param mixed            $condition     La condition à vérifier
     * @param string|Throwable $exception     L'exception à lever ou son nom de classe
     * @param mixed            ...$parameters Les paramètres pour l'exception
     *
     * @return mixed La condition
     *
     * @throws Throwable Si la condition est vraie
     *
     * @credit <a href="http://laravel.com/">Laravel</a>
     */
    public static function throwIf(mixed $condition, string|Throwable $exception = 'RuntimeException', ...$parameters): mixed
    {
        if ($condition) {
            if (is_string($exception) && class_exists($exception)) {
                $exception = new $exception(...$parameters);
            }

            throw is_string($exception) ? new RuntimeException($exception) : $exception;
        }

        return $condition;
    }

    /**
     * Renvoie tous les traits utilisés par un trait et ses traits.
     *
     * @param string $trait Le trait à analyser
     *
     * @return array Les traits utilisés récursivement
     */
    public static function traitUsesRecursive(string $trait): array
    {
        $traits = class_uses($trait) ?: [];

        foreach ($traits as $trait) {
            $traits += self::traitUsesRecursive($trait);
        }

        return $traits;
    }

    /**
     * Déclenche un E_USER_WARNING.
     *
     * @param string $message Le message d'avertissement
     */
    public static function triggerWarning(string $message): void
    {
        $stackFrame = 1;
        $trace      = debug_backtrace();
        if (isset($trace[$stackFrame])) {
            $frame = $trace[$stackFrame];
            $frame += ['file' => '[internal]', 'line' => '??'];
            $message = sprintf(
                '%s - %s, line: %s',
                $message,
                $frame['file'],
                $frame['line']
            );
        }
        trigger_error($message, E_USER_WARNING);
    }

    /**
     * Renvoie la classe d'objets ou le type var si ce n'est pas un objet
     *
     * @param mixed $var Variable à vérifier
     *
     * @return string Renvoie le nom de la classe ou le type de variable
     */
    public static function typeName(mixed $var): string
    {
        return is_object($var) ? get_class($var) : gettype($var);
    }

    /**
     * Renvoie la valeur par défaut de la valeur donnée.
     *
     * @param mixed $value   La valeur à évaluer
     * @param mixed ...$args Arguments additionnels pour les closures
     *
     * @return mixed La valeur résolue
     */
    public static function value(mixed $value, ...$args): mixed
    {
        return $value instanceof Closure ? $value(...$args) : $value;
    }

    /**
     * Renvoie une valeur si la condition donnée est vraie.
     *
     * @param mixed         $condition La condition à vérifier
     * @param Closure|mixed $value     La valeur à retourner si la condition est vraie
     * @param Closure|mixed $default   La valeur à retourner si la condition est fausse
     *
     * @return mixed La valeur appropriée selon la condition
     */
    public static function when(mixed $condition, $value, $default = null): mixed
    {
        $condition = $condition instanceof Closure ? $condition() : $condition;

        if ($condition) {
            return static::value($value, $condition);
        }

        return static::value($default, $condition);
    }

    /**
     * Renvoie la valeur donnée, éventuellement transmise via le rappel donné.
     *
     * @param mixed         $value    La valeur à traiter
     * @param callable|null $callback Le callback à appliquer
     *
     * @return mixed La valeur originale ou le résultat du callback
     */
    public static function with(mixed $value, ?callable $callback = null): mixed
    {
        return null === $callback ? $value : $callback($value);
    }
}
