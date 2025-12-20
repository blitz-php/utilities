<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\String;

use BlitzPHP\Traits\Macroable;
use BlitzPHP\Utilities\Iterable\Arr;
use BlitzPHP\Utilities\Iterable\Collection;
use Closure;
use Countable;
use InvalidArgumentException;
use Jawira\CaseConverter\Convert;
use JsonException;
use Throwable;
use Traversable;

// Définition des constantes pour les extensions de caractères
if (! defined('MB_ENABLED')) {
    if (extension_loaded('mbstring')) {
        define('MB_ENABLED', true);
        // mbstring.internal_encoding est obsolète depuis PHP 5.6
        @ini_set('mbstring.internal_encoding', 'UTF-8');
        // Ceci est requis pour que mb_convert_encoding() supprime les caractères invalides
        mb_substitute_character('none');
    } else {
        define('MB_ENABLED', false);
    }
}

if (! defined('ICONV_ENABLED')) {
    define('ICONV_ENABLED', extension_loaded('iconv'));
}

/**
 * Utilitaire de manipulation de chaînes de caractères
 *
 * Cette classe fournit des méthodes statiques pour manipuler, transformer
 * et analyser les chaînes de caractères, avec support Unicode complet.
 *
 * @credit      CakePHP (Cake\Utility\Text - https://cakephp.org)
 * @credit      Laravel (Illuminate\Support\Str - https://laravel.com)
 */
class Text
{
	use Macroable;

    /**
     * Caractères invisibles dans les chaînes.
     *
     * @var string
     */
    const INVISIBLE_CHARACTERS = '\x{0009}\x{0020}\x{00A0}\x{00AD}\x{034F}\x{061C}\x{115F}\x{1160}\x{17B4}\x{17B5}\x{180E}\x{2000}\x{2001}\x{2002}\x{2003}\x{2004}\x{2005}\x{2006}\x{2007}\x{2008}\x{2009}\x{200A}\x{200B}\x{200C}\x{200D}\x{200E}\x{200F}\x{202F}\x{205F}\x{2060}\x{2061}\x{2062}\x{2063}\x{2064}\x{2065}\x{206A}\x{206B}\x{206C}\x{206D}\x{206E}\x{206F}\x{3000}\x{2800}\x{3164}\x{FEFF}\x{FFA0}\x{1D159}\x{1D173}\x{1D174}\x{1D175}\x{1D176}\x{1D177}\x{1D178}\x{1D179}\x{1D17A}\x{E0020}';

    /**
     * Identifiant de transliterator par défaut.
     *
     * @var string Identifiant de transliterator.
     */
    protected static string $_defaultTransliteratorId = 'Any-Latin; Latin-ASCII;';

    /**
     * Balises HTML qui ne doivent pas être comptées pour la troncature de texte.
     *
     * @var list<string>
     */
    protected static array $_defaultHtmlNoCount = [
        'style',
        'script',
    ];

    /**
     * Cache des mots en snake_case.
     *
     * @var array<string, string>
     */
    protected static array $snakeCache = [];

    /**
     * Cache des mots en camelCase.
     *
     * @var array<string, string>
     */
    protected static array $camelCache = [];

    /**
     * Cache des mots en StudlyCase.
     *
     * @var array<string, string>
     */
    protected static array $studlyCache = [];

    /**
     * Cache utilisé par la méthode convertTo qui utilise \Jawira\CaseConverter\Convert
     *
     * @var array<string, array<string, string>>
     *
     * @example
     * [
     *      'pascal' => [
     *          'blitz php' => 'BlitzPhp',
     *          'my variable' => 'MyVariable',
     *      ],
     *      'snake' => [
     *          'blitz php' => 'blitz_php',
     *          'my variable' => 'my_variable',
     *      ],
     *      'camel' => [
     *          'blitz php' => 'blitzPhp',
     *          'my variable' => 'myVariable',
     *      ],
     * ]
     */
    protected static array $converterCache = [];

    /**
     * Callback utilisé pour générer des chaînes aléatoires.
     *
     * @var callable|null
     */
    protected static $randomStringFactory = null;

    /**
     * Callback utilisé pour générer des UUIDs.
     *
     * @var callable|null
     */
    protected static $uuidFactory = null;

    /**
     * Callback utilisé pour générer des ULIDs.
     *
     * @var callable|null
     */
    protected static $ulidFactory = null;

    /**
     * Gestion des appels statiques dynamiques pour les conversions de casse
     *
     * @param string $name Nom de la méthode appelée
     * @param array<int, mixed> $arguments Arguments passés à la méthode
     * @return string Chaîne convertie
     * @throws InvalidArgumentException Si la méthode n'existe pas
     */
    public static function __callStatic(string $name, array $arguments): string
    {
        /**
         * Conversion de casse d'écriture
         */
        if (preg_match('#^to(.*)(Case)?$#i', $name)) {
            if (empty($arguments)) {
                throw new InvalidArgumentException('La méthode ' . $name . ' nécessite une chaîne en paramètre');
            }
            return static::convertTo((string) $arguments[0], $name);
        }

        throw new InvalidArgumentException('Méthode inconnue ' . __CLASS__ . '::' . $name);
    }

    /**
     * Convertit des chaînes entre 13 conventions de nommage :
     * - Snake case, Camel case, Kebab case, Pascal case, Ada case, Train case, Cobol case, Macro case,
     * - majuscules, minuscules, Title case, Sentence Case et notation par points.
     *
     * @param string $str Chaîne à convertir
     * @param string $converter Type de convertisseur (ex: 'toCamel', 'toSnakeCase')
     * @return string Chaîne convertie
     *
     * @throws InvalidArgumentException Si le type de convertisseur est invalide
     *
     * @uses \Jawira\CaseConverter\Convert
     */
    public static function convertTo(string $str, string $converter): string
    {
        // Nettoyage du nom du convertisseur
        $converter = preg_replace('#Case$#i', '', $converter);
        $converter = strtolower(str_replace('to', '', $converter));

        if (method_exists(static::class, $converter)) {
            return call_user_func([static::class, $converter], $str);
        }

		return static::jawiraConvert($str, $converter);
    }

	private static function jawiraConvert(string $value, string $converter): string
	{
		if (isset(static::$converterCache[$converter][$value])) {
			return static::$converterCache[$converter][$value];
		}

		$availableCase = [
            'ada', 'camel', 'cobol', 'dot', 'kebab',
			'lower', 'macro', 'pascal', 'snake', 'sentence',
            'train', 'title', 'upper',
        ];

        if (! in_array($converter, $availableCase, true)) {
            throw new InvalidArgumentException(
                "Type de convertisseur invalide : `{$converter}`. " .
                "Types disponibles : " . implode(', ', $availableCase)
            );
        }

		if (! class_exists(Convert::class)) {
			throw new InvalidArgumentException(
				'La classe Jawira\CaseConverter\Convert est requise pour cette conversion. ' .
				'Installez-la avec : composer require jawira/case-converter'
			);
		}

		$processor = new Convert($value);
		$method    = 'to' . ucfirst($converter);

		return static::$converterCache[$converter][$value] = $processor->$method();
	}

    /**
     * Crée un nouvel objet Stringable à partir de la chaîne donnée.
     *
     * @param string $string Chaîne source
	 *
     * @return Stringable Instance Stringable
     */
    public static function of(string $string): Stringable
    {
        return new Stringable($string);
    }

    /**
     * Retourne le reste d'une chaîne après la première occurrence d'une valeur donnée.
     *
     * @param string $subject Chaîne source
     * @param string $search Valeur à rechercher
	 *
     * @return string Reste de la chaîne
     */
    public static function after(string $subject, string $search): string
    {
        return $search === '' ? $subject : array_reverse(explode($search, $subject, 2))[0];
    }

    /**
     * Retourne le reste d'une chaîne après la dernière occurrence d'une valeur donnée.
     *
     * @param string $subject Chaîne source
     * @param string $search Valeur à rechercher
	 *
     * @return string Reste de la chaîne
     */
    public static function afterLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position === false) {
            return $subject;
        }

        return substr($subject, $position + strlen($search));
    }

    /**
     * Translittère une valeur UTF-8 en ASCII.
     *
     * @param string $value Valeur à translittérer
     * @param string $language Langue (par défaut: 'en')
	 *
     * @return string Valeur translittérée
     */
    public static function ascii(string $value, string $language = 'en'): string
    {
		if (class_exists('\voku\helper\ASCII')) {
			return \voku\helper\ASCII::to_ascii((string) $value, $language, replace_single_chars_only: false);
		}

        $languageSpecific = static::languageSpecificCharsArray($language);

        if (null !== $languageSpecific) {
            $value = str_replace($languageSpecific[0], $languageSpecific[1], $value);
        }

        foreach (static::charsArray() as $key => $val) {
            $value = str_replace($val, $key, $value);
        }

        return preg_replace('/[^\x20-\x7E]/u', '', $value) ?? $value;
    }

    /**
     * Translittère une chaîne vers sa représentation ASCII la plus proche.
     *
     * @param string $string Chaîne à translittérer
     * @param string|null $unknown Caractère de remplacement pour les caractères inconnus
     * @param bool|null $strict Mode strict
	 *
     * @return string Chaîne translittérée
     */
    public static function transliterate(string $string, ?string $unknown = '?', ?bool $strict = false): string
    {
		if (class_exists('\voku\helper\ASCII')) {
			return \voku\helper\ASCII::to_transliterate($string, $unknown, $strict);
		}

        if (function_exists('transliterator_transliterate')) {
            $result = transliterator_transliterate(static::$_defaultTransliteratorId, $string);

			return $result !== false ? $result : $string;
        }

        return static::ascii($string, 'en');
    }

    /**
     * Récupère la partie d'une chaîne avant la première occurrence d'une valeur donnée.
     *
     * @param string $subject Chaîne source
     * @param string $search Valeur à rechercher
	 *
     * @return string Partie avant la première occurrence
     */
    public static function before(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $result = strstr($subject, $search, true);

        return $result === false ? $subject : $result;
    }

    /**
     * Récupère la partie d'une chaîne avant la dernière occurrence d'une valeur donnée.
     *
     * @param string $subject Chaîne source
     * @param string $search Valeur à rechercher
	 *
     * @return string Partie avant la dernière occurrence
     */
    public static function beforeLast(string $subject, string $search): string
    {
        if ($search === '') {
            return $subject;
        }

        $pos = mb_strrpos($subject, $search);

        if ($pos === false) {
            return $subject;
        }

        return static::substr($subject, 0, $pos);
    }

    /**
     * Récupère la partie d'une chaîne entre deux valeurs données.
     *
     * @param string $subject Chaîne source
     * @param string $from Valeur de début
     * @param string $to Valeur de fin
	 *
     * @return string Partie entre les deux valeurs
     */
    public static function between(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::beforeLast(static::after($subject, $from), $to);
    }

    /**
     * Récupère la plus petite partie possible d'une chaîne entre deux valeurs données.
     *
     * @param string $subject Chaîne source
     * @param string $from Valeur de début
     * @param string $to Valeur de fin
	 *
     * @return string Plus petite partie entre les deux valeurs
     */
    public static function betweenFirst(string $subject, string $from, string $to): string
    {
        if ($from === '' || $to === '') {
            return $subject;
        }

        return static::before(static::after($subject, $from), $to);
    }

    /**
     * Convertit une valeur en camelCase.
     *
     * @param string $value Valeur à convertir
	 *
     * @return string Valeur en camelCase
     */
    public static function camel(string $value): string
    {
        if (isset(static::$camelCache[$value])) {
            return static::$camelCache[$value];
        }

        return static::$camelCache[$value] = lcfirst(static::studly($value));
    }

    /**
     * Récupère le caractère à l'index spécifié.
     *
     * @param string $subject Chaîne source
     * @param int $index Index du caractère
	 *
     * @return string|false Caractère à l'index ou false si hors limites
     */
    public static function charAt(string $subject, int $index): string|false
    {
        $length = mb_strlen($subject);

        if ($index < 0 ? $index < -$length : $index > $length - 1) {
            return false;
        }

        return mb_substr($subject, $index, 1);
    }

    /**
     * Supprime la chaîne donnée si elle existe au début de la chaîne source.
     *
     * @param string $subject Chaîne source
     * @param string|array $needle Chaîne(s) à supprimer
	 *
     * @return string Chaîne modifiée
     */
    public static function chopStart(string $subject, string|array $needle): string
    {
        foreach ((array) $needle as $n) {
            if (str_starts_with($subject, $n)) {
                return substr($subject, strlen($n));
            }
        }

        return $subject;
    }

    /**
     * Supprime la chaîne donnée si elle existe à la fin de la chaîne source.
     *
     * @param string $subject Chaîne source
     * @param string|array $needle Chaîne(s) à supprimer
	 *
     * @return string Chaîne modifiée
     */
    public static function chopEnd(string $subject, string|array $needle): string
    {
        foreach ((array) $needle as $n) {
            if (str_ends_with($subject, $n)) {
                return substr($subject, 0, -strlen($n));
            }
        }

        return $subject;
    }

    /**
     * Nettoie les chaînes UTF-8.
     *
     * @param string $str Chaîne à nettoyer
	 *
     * @return string Chaîne nettoyée
     */
    public static function clean(string $str): string
    {
        if (! static::isAscii($str)) {
            if (MB_ENABLED) {
                $str = mb_convert_encoding($str, 'UTF-8', 'UTF-8');
            } elseif (ICONV_ENABLED) {
                $str = @iconv('UTF-8', 'UTF-8//IGNORE', $str) ?: $str;
            }
        }

        return $str;
    }

    /**
     * Nettoie une chaîne formatée avec static::insert() selon les options 'clean'.
     * La méthode par défaut utilisée est 'text' mais 'html' est également disponible.
     * Le but de cette fonction est de remplacer tous les espaces blancs et balises
     * inutiles autour des placeholders qui n'ont pas été remplacés par static::insert().
     *
     * @param string $str Chaîne à nettoyer
     * @param array<string, mixed> $options Options de nettoyage
	 *
     * @return string Chaîne nettoyée
     */
    public static function cleanInsert(string $str, array $options): string
    {
        $clean = $options['clean'] ?? false;
        if (! $clean) {
            return $str;
        }

        if ($clean === true) {
            $clean = ['method' => 'text'];
        }

        if (! is_array($clean)) {
            $clean = ['method' => $clean];
        }

        $clean += [
            'word'        => '[\w,.]+',
            'andText'     => true,
            'replacement' => '',
        ];

        switch ($clean['method']) {
            case 'html':
                $kleenex = sprintf(
                    '/[\s]*[a-z]+=(")(%s%s%s[\s]*)+\\1/i',
                    preg_quote($options['before'] ?? ':', '/'),
                    $clean['word'],
                    preg_quote($options['after'] ?? '', '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);

                if ($clean['andText']) {
                    $options['clean'] = ['method' => 'text'];
                    $str              = static::cleanInsert($str, $options);
                }
                break;

            case 'text':
                $clean += ['gap' => '[\s]*(?:(?:and|or)[\s]*)?'];

                $kleenex = sprintf(
                    '/(%s%s%s%s|%s%s%s%s)/',
                    preg_quote($options['before'] ?? ':', '/'),
                    $clean['word'],
                    preg_quote($options['after'] ?? '', '/'),
                    $clean['gap'],
                    $clean['gap'],
                    preg_quote($options['before'] ?? ':', '/'),
                    $clean['word'],
                    preg_quote($options['after'] ?? '', '/')
                );
                $str = preg_replace($kleenex, $clean['replacement'], $str);
                break;
        }

        return $str;
    }

    /**
     * Détermine si une chaîne donnée contient une sous-chaîne donnée.
     *
     * @param string $haystack Chaîne dans laquelle chercher
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
     * @param bool $ignoreCase Ignorer la casse (par défaut: false)
	 *
     * @return bool true si la sous-chaîne est trouvée, false sinon
     */
    public static function contains(string $haystack, iterable|string $needles, bool $ignoreCase = false): bool
    {
        if ($ignoreCase) {
            $haystack = mb_strtolower($haystack);
        }

        if (! is_iterable($needles)) {
            $needles = (array) $needles;
        }

        foreach ($needles as $needle) {
            if ($ignoreCase) {
                $needle = mb_strtolower($needle);
            }

            if ($needle !== '' && str_contains($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si une chaîne donnée contient toutes les valeurs d'un tableau.
     *
     * @param string $haystack Chaîne dans laquelle chercher
     * @param iterable<string> $needles Sous-chaînes à rechercher
     * @param bool $ignoreCase Ignorer la casse (par défaut: false)
	 *
     * @return bool true si toutes les sous-chaînes sont trouvées, false sinon
     */
    public static function containsAll(string $haystack, iterable $needles, bool $ignoreCase = false): bool
    {
        foreach ($needles as $needle) {
            if (! static::contains($haystack, $needle, $ignoreCase)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Détermine si une chaîne donnée ne contient pas une sous-chaîne donnée.
     *
     * @param string $haystack Chaîne dans laquelle chercher
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
     * @param bool $ignoreCase Ignorer la casse (par défaut: false)
	 *
     * @return bool true si la sous-chaîne n'est pas trouvée, false sinon
     */
    public static function doesntContain(string $haystack, iterable|string $needles, bool $ignoreCase = false): bool
    {
        return ! static::contains($haystack, $needles, $ignoreCase);
    }

    /**
     * Convertit la casse d'une chaîne.
     *
     * @param string $string Chaîne à convertir
     * @param int $mode Mode de conversion (par défaut: MB_CASE_FOLD)
     * @param string|null $encoding Encodage (par défaut: 'UTF-8')
	 *
     * @return string Chaîne convertie
     */
    public static function convertCase(string $string, int $mode = MB_CASE_FOLD, ?string $encoding = 'UTF-8'): string
    {
        return mb_convert_case($string, $mode, $encoding);
    }

    /**
     * Remplace les instances consécutives d'un caractère donné par un seul caractère.
     *
     * @param string $string Chaîne source
     * @param array<string>|string $characters Caractère(s) à dédupliquer
	 *
     * @return string Chaîne modifiée
     */
    public static function deduplicate(string $string, array|string $characters = ' '): string
    {
        if (is_string($characters)) {
            return preg_replace('/'.preg_quote($characters, '/').'+/u', $characters, $string) ?? $string;
        }

        return array_reduce(
            $characters,
            fn ($carry, $character) => preg_replace('/'.preg_quote($character, '/').'+/u', $character, $carry) ?? $carry,
            $string
        );
    }

    /**
     * Détermine si une chaîne donnée se termine par une sous-chaîne donnée.
     *
     * @param string $haystack Chaîne à vérifier
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
	 *
     * @return bool true si la chaîne se termine par l'une des sous-chaînes
     */
    public static function endsWith(string $haystack, iterable|string $needles): bool
    {
        if (! is_iterable($needles)) {
            $needles = (array) $needles;
        }

        foreach ($needles as $needle) {
            if ((string) $needle !== '' && str_ends_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si une chaîne donnée ne se termine pas par une sous-chaîne donnée.
     *
     * @param string $haystack Chaîne à vérifier
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
	 *
     * @return bool true si la chaîne ne se termine pas par les sous-chaînes
     */
    public static function doesntEndWith(string $haystack, iterable|string $needles): bool
    {
        return ! static::endsWith($haystack, $needles);
    }

    /**
     * Extrait un extrait de texte qui correspond à la première instance d'une phrase.
     *
     * @param string $text Texte source
     * @param string $phrase Phrase à rechercher
     * @param array<string, mixed> $options Options d'extrait
	 *
     * @return string|null L'extrait ou null si non trouvé
     */
    public static function excerpt(string $text, string $phrase = '', array $options = []): ?string
    {
        $radius = $options['radius'] ?? 100;
        $omission = $options['omission'] ?? '...';

        preg_match('/^(.*?)('.preg_quote((string) $phrase, '/').')(.*)$/iu', (string) $text, $matches);

        if (empty($matches)) {
            return null;
        }

        $start = ltrim($matches[1]);

        $start = self::of(mb_substr($start, max(mb_strlen($start, 'UTF-8') - $radius, 0), $radius, 'UTF-8'))->ltrim()->unless(
            fn ($startWithRadius) => $startWithRadius->exactly($start),
            fn ($startWithRadius) => $startWithRadius->prepend($omission),
        );

        $end = rtrim($matches[3]);

        $end = self::of(mb_substr($end, 0, $radius, 'UTF-8'))->rtrim()->unless(
            fn ($endWithRadius) => $endWithRadius->exactly($end),
            fn ($endWithRadius) => $endWithRadius->append($omission),
        );

        return $start->append($matches[2], $end)->toString();
    }

    /**
     * Termine une chaîne avec une seule instance d'une valeur donnée.
     *
     * @param string $value Chaîne à terminer
     * @param string $cap Valeur à ajouter à la fin
	 *
     * @return string Chaîne terminée
     */
    public static function finish(string $value, string $cap): string
    {
        $quoted = preg_quote($cap, '/');

        return preg_replace('/(?:' . $quoted . ')+$/u', '', $value) . $cap;
    }

    /**
     * Met en surbrillance une phrase donnée dans un texte.
     *
     * @param string $text Texte dans lequel rechercher
     * @param array<int, string>|string $phrase La ou les phrases à rechercher
     * @param array<string, mixed> $options Options de surbrillance
	 *
     * @return string Texte avec surbrillance
     */
    public static function highlight(string $text, array|string $phrase, array $options = []): string
    {
        if (empty($phrase)) {
            return $text;
        }

        $defaults = [
            'format' => '<span class="highlight">\1</span>',
            'html'   => false,
            'regex'  => '|%s|iu',
            'limit'  => -1,
        ];
        $options += $defaults;

        $html   = $options['html'];
        $format = $options['format'];
        $limit  = $options['limit'];

        if (is_array($phrase)) {
            $replace = [];
            $with    = [];

            foreach ($phrase as $key => $segment) {
                $segment = '(' . preg_quote($segment, '|') . ')';
                if ($html) {
                    $segment = "(?![^<]+>){$segment}(?![^<]+>)";
                }

                $with[]    = is_array($format) ? ($format[$key] ?? $format) : $format;
                $replace[] = sprintf($options['regex'], $segment);
            }

            return preg_replace($replace, $with, $text, $limit) ?? $text;
        }

        $phrase = '(' . preg_quote($phrase, '|') . ')';
        if ($html) {
            $phrase = "(?![^<]+>){$phrase}(?![^<]+>)";
        }

        return preg_replace(sprintf($options['regex'], $phrase), $format, $text, $limit) ?? $text;
    }

    /**
     * Encapsule une chaîne avec les chaînes données.
     *
     * @param string $value Chaîne à encapsuler
     * @param string $before Chaîne à placer avant
     * @param string|null $after Chaîne à placer après (null = utilise $before)
	 *
     * @return string Chaîne encapsulée
     */
    public static function wrap(string $value, string $before, ?string $after = null): string
    {
        return $before . $value . ($after ?? $before);
    }

    /**
     * Désencapsule une chaîne avec les chaînes données.
     *
     * @param string $value Chaîne à désencapsuler
     * @param string $before Chaîne au début
     * @param string|null $after Chaîne à la fin (null = utilise $before)
	 *
     * @return string Chaîne désencapsulée
     */
    public static function unwrap(string $value, string $before, ?string $after = null): string
    {
        if (static::startsWith($value, $before)) {
            $value = static::substr($value, static::length($before));
        }

        if (static::endsWith($value, $after ??= $before)) {
            $value = static::substr($value, 0, -static::length($after));
        }

        return $value;
    }

    /**
     * Remplace les placeholders variables dans une chaîne par les données données.
     *
     * @param string $str Chaîne contenant des placeholders
     * @param array<string, mixed> $data Tableau clé => valeur où chaque clé correspond à un placeholder
     * @param array<string, mixed> $options Options de remplacement
	 *
     * @return string Chaîne avec remplacements
     */
    public static function insert(string $str, array $data, array $options = []): string
    {
        $defaults = [
            'before' => ':', 'after' => null, 'escape' => '\\', 'format' => null, 'clean' => false,
        ];
        $options += $defaults;
        $format = $options['format'];
        $data   = (array) $data;

        if (empty($data)) {
            return $options['clean'] ? static::cleanInsert($str, $options) : $str;
        }

        if (! isset($format)) {
            $format = sprintf(
                '/(?<!%s)%s%%s%s/',
                preg_quote($options['escape'], '/'),
                str_replace('%', '%%', preg_quote($options['before'], '/')),
                str_replace('%', '%%', preg_quote($options['after'] ?? '', '/'))
            );
        }

        if (str_contains($str, '?') && is_numeric(key($data))) {
            $offset = 0;

            while (($pos = strpos($str, '?', $offset)) !== false) {
                $val    = array_shift($data);
                $offset = $pos + strlen((string) $val);
                $str    = substr_replace($str, (string) $val, $pos, 1);
            }

            return $options['clean'] ? static::cleanInsert($str, $options) : $str;
        }

        $dataKeys = array_keys($data);
        $hashKeys = array_map('crc32', $dataKeys);
        $tempData = array_combine($dataKeys, $hashKeys);
        krsort($tempData);

        foreach ($tempData as $key => $hashVal) {
            $key = sprintf($format, preg_quote($key, '/'));
            $str = preg_replace($key, (string) $hashVal, $str) ?? $str;
        }

        $dataReplacements = array_combine($hashKeys, array_values($data));

        foreach ($dataReplacements as $tmpHash => $tmpValue) {
            $tmpValue = is_array($tmpValue) ? '' : (string) $tmpValue;
            $str      = str_replace((string) $tmpHash, $tmpValue, $str);
        }

        if (! isset($options['format']) && isset($options['before'])) {
            $str = str_replace($options['escape'] . $options['before'], $options['before'], $str);
        }

        return $options['clean'] ? static::cleanInsert($str, $options) : $str;
    }

    /**
     * Détermine si une chaîne donnée correspond à un motif donné.
     *
     * @param iterable<string>|string $pattern Motif(s) à comparer
     * @param string $value Valeur à tester
     * @param bool $ignoreCase Ignorer la casse (par défaut: false)
     * @return bool true si la chaîne correspond à l'un des motifs
     */
    public static function is(iterable|string $pattern, string $value, bool $ignoreCase = false): bool
    {
        $value = (string) $value;

        if (! is_iterable($pattern)) {
            $pattern = [$pattern];
        }

        foreach ($pattern as $pattern) {
            $pattern = (string) $pattern;

            // Si la valeur correspond exactement
            if ($pattern === '*' || $pattern === $value) {
                return true;
            }

            if ($ignoreCase && mb_strtolower($pattern) === mb_strtolower($value)) {
                return true;
            }

            $pattern = preg_quote($pattern, '#');

            // Remplace les astérisques par des wildcards regex
            $pattern = str_replace('\*', '.*', $pattern);

            if (preg_match('#^' . $pattern . '\z#' . ($ignoreCase ? 'isu' : 'su'), $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si une chaîne donnée est en ASCII 7 bits.
     *
     * @param string $value Chaîne à vérifier
	 *
     * @return bool true si la chaîne est en ASCII
     */
    public static function isAscii(string $value): bool
    {
		if (class_exists('\voku\helper\ASCII')) {
			return \voku\helper\ASCII::is_ascii($value);
		}

        if (function_exists('mb_detect_encoding')) {
            return mb_detect_encoding($value, 'ASCII', true) !== false;
        }

        return preg_match('/[^\x00-\x7F]/S', $value) === 0;
    }

    /**
     * Détermine si une chaîne donnée est un JSON valide.
     *
     * @param string $value Chaîne à vérifier
     * @return bool true si la chaîne est un JSON valide
     */
    public static function isJson(string $value): bool
    {
		if (function_exists('json_validate')) {
			return json_validate($value, 512); // PHP 8.3+
		}

        try {
            json_decode($value, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return false;
        }

        return true;
    }

    /**
     * Détermine si une chaîne donnée est une URL valide.
     *
     * @param string $value Valeur à vérifier
     * @param array $protocols Protocoles autorisés
	 *
     * @return bool true si la valeur est une URL valide
     */
    public static function isUrl(string $value, array $protocols = []): bool
    {
        if ($value === '' || $value === '0') {
            return false;
        }

        $protocolList = empty($protocols)
            ? 'aaa|aaas|about|acap|acct|acd|acr|adiumxtra|adt|afp|afs|aim|amss|android|appdata|apt|ark|attachment|aw|barion|beshare|bitcoin|bitcoincash|blob|bolo|browserext|calculator|callto|cap|cast|casts|chrome|chrome-extension|cid|coap|coap\+tcp|coap\+ws|coaps|coaps\+tcp|coaps\+ws|com-eventbrite-attendee|content|conti|crid|cvs|dab|data|dav|diaspora|dict|did|dis|dlna-playcontainer|dlna-playsingle|dns|dntp|dpp|drm|drop|dtn|dvb|ed2k|elsi|example|facetime|fax|feed|feedready|file|filesystem|finger|first-run-pen-experience|fish|fm|ftp|fuchsia-pkg|geo|gg|git|gizmoproject|go|gopher|graph|gtalk|h323|ham|hcap|hcp|http|https|hxxp|hxxps|hydrazone|iax|icap|icon|im|imap|info|iotdisco|ipn|ipp|ipps|irc|irc6|ircs|iris|iris\.beep|iris\.lwz|iris\.xpc|iris\.xpcs|isostore|itms|jabber|jar|jms|keyparc|lastfm|ldap|ldaps|leaptofrogans|lorawan|lvlt|magnet|mailserver|mailto|maps|market|message|mid|mms|modem|mongodb|moz|ms-access|ms-browser-extension|ms-calculator|ms-drive-to|ms-enrollment|ms-excel|ms-eyecontrolspeech|ms-gamebarservices|ms-gamingoverlay|ms-getoffice|ms-help|ms-infopath|ms-inputapp|ms-lockscreencomponent-config|ms-media-stream-id|ms-mixedrealitycapture|ms-mobileplans|ms-officeapp|ms-people|ms-project|ms-powerpoint|ms-publisher|ms-restoretabcompanion|ms-screenclip|ms-screensketch|ms-search|ms-search-repair|ms-secondary-screen-controller|ms-secondary-screen-setup|ms-settings|ms-settings-airplanemode|ms-settings-bluetooth|ms-settings-camera|ms-settings-cellular|ms-settings-cloudstorage|ms-settings-connectabledevices|ms-settings-displays-topology|ms-settings-emailandaccounts|ms-settings-language|ms-settings-location|ms-settings-lock|ms-settings-nfctransactions|ms-settings-notifications|ms-settings-power|ms-settings-privacy|ms-settings-proximity|ms-settings-screenrotation|ms-settings-wifi|ms-settings-workplace|ms-spd|ms-sttoverlay|ms-transit-to|ms-useractivityset|ms-virtualtouchpad|ms-visio|ms-walk-to|ms-whiteboard|ms-whiteboard-cmd|ms-word|msnim|msrp|msrps|mss|mtqp|mumble|mupdate|mvn|news|nfs|ni|nih|nntp|notes|ocf|oid|onenote|onenote-cmd|opaquelocktoken|openpgp4fpr|pack|palm|paparazzi|payto|pkcs11|platform|pop|pres|prospero|proxy|pwid|psyc|pttp|qb|query|redis|rediss|reload|res|resource|rmi|rsync|rtmfp|rtmp|rtsp|rtsps|rtspu|s3|secondlife|service|session|sftp|sgn|shttp|sieve|simpleledger|sip|sips|skype|smb|sms|smtp|snews|snmp|soap\.beep|soap\.beeps|soldat|spiffe|spotify|ssh|steam|stun|stuns|submit|svn|tag|teamspeak|tel|teliaeid|telnet|tftp|tg|things|thismessage|tip|tn3270|tool|ts3server|turn|turns|tv|udp|unreal|urn|ut2004|v-event|vemmi|ventrilo|videotex|vnc|view-source|wais|webcal|wpid|ws|wss|wtai|wyciwyg|xcon|xcon-userid|xfire|xmlrpc\.beep|xmlrpc\.beeps|xmpp|xri|ymsgr|z39\.50|z39\.50r|z39\.50s'
            : implode('|', $protocols);

        /*
         * Ce pattern est issu de Symfony\Component\Validator\Constraints\UrlValidator (5.0.7).
         *
         * (c) Fabien Potencier <fabien@symfony.com> http://symfony.com
         */
        $pattern = '~^
            (BLITZPHP_PROTOCOLS)://                                 # protocol
            (((?:[\_\.\pL\pN-]|%[0-9A-Fa-f]{2})+:)?((?:[\_\.\pL\pN-]|%[0-9A-Fa-f]{2})+)@)?  # basic auth
            (
                ([\pL\pN\pS\-\_\.])+(\.?([\pL\pN]|xn\-\-[\pL\pN-]+)+\.?) # a domain name
                    |                                                 # or
                \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}                    # an IP address
                    |                                                 # or
                \[
                    (?:(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){6})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:::(?:(?:(?:[0-9a-f]{1,4})):){5})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){4})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,1}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){3})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,2}(?:(?:[0-9a-f]{1,4})))?::(?:(?:(?:[0-9a-f]{1,4})):){2})(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,3}(?:(?:[0-9a-f]{1,4})))?::(?:(?:[0-9a-f]{1,4})):)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,4}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:(?:(?:(?:[0-9a-f]{1,4})):(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9]))\.){3}(?:(?:25[0-5]|(?:[1-9]|1[0-9]|2[0-4])?[0-9])))))))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,5}(?:(?:[0-9a-f]{1,4})))?::)(?:(?:[0-9a-f]{1,4})))|(?:(?:(?:(?:(?:(?:[0-9a-f]{1,4})):){0,6}(?:(?:[0-9a-f]{1,4})))?::))))
                \]  # an IPv6 address
            )
            (:[0-9]+)?                              # a port (optional)
            (?:/ (?:[\pL\pN\-._\~!$&\'()*+,;=:@]|%[0-9A-Fa-f]{2})* )*          # a path
            (?:\? (?:[\pL\pN\-._\~!$&\'\[\]()*+,;=:@/?]|%[0-9A-Fa-f]{2})* )?   # a query (optional)
            (?:\# (?:[\pL\pN\-._\~!$&\'()*+,;=:@/?]|%[0-9A-Fa-f]{2})* )?       # a fragment (optional)
        $~ixu';

        return preg_match(str_replace('BLITZPHP_PROTOCOLS', $protocolList, $pattern), $value) > 0;
    }

    /**
     * Détermine si une chaîne donnée est un UUID valide.
     *
     * @param string $value Chaîne à vérifier
	 * @param  int<0, 8>|'nil'|'max'|null  $version
	 *
     * @return bool true si la chaîne est un UUID valide
     */
    public static function isUuid(string $value, $version = null): bool
    {
		if ($version === null) {
            return preg_match('/^[\da-fA-F]{8}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{4}-[\da-fA-F]{12}$/D', $value) > 0;
        }

		if ($version === 'max') {
			return $value === Uuid::max();
		}

		if ($version === 'nil') {
			return Uuid::isNil($value);
		}

		return Uuid::isValidVersion($value, $version);
    }

    /**
     * Détermine si une chaîne donnée est un ULID valide.
     *
     * @param string $value Chaîne à vérifier
	 *
     * @return bool true si la chaîne est un ULID valide
     */
    public static function isUlid(string $value): bool
    {
        // ULID: 26 caractères base32 (0-9, A-Z sauf I, L, O, U)
        return preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/i', $value) > 0;
    }

    /**
     * Détermine si une chaîne donnée est une expression régulière valide.
     *
     * @param string $value Chaîne à vérifier
	 *
     * @return bool true si la chaîne est une expression régulière valide
     */
    public static function isRegex(string $value): bool
    {
        if (strlen($value) < 2) {
            return false;
        }

        $delimiters = ['/', '#', '~', '%', '|', '!', '@', '_'];
        $firstChar = $value[0];

        // Vérifier les délimiteurs appairés aussi
        $pairedDelimiters = ['(' => ')', '{' => '}', '[' => ']', '<' => '>'];

        // Si c'est un délimiteur appairé
        if (isset($pairedDelimiters[$firstChar])) {
            $closingChar = $pairedDelimiters[$firstChar];
            $lastChar = substr(rtrim($value, 'imsxeADSUXJu'), -1);

            if ($lastChar === $closingChar) {
                return @preg_match($value, '') !== false;
            }
            return false;
        }

        // Si c'est un délimiteur simple
        if (in_array($firstChar, $delimiters)) {
            // Cherche le délimiteur de fin (en ignorant les modificateurs)
            $pattern = '/^' . preg_quote($firstChar, '/') . '.*?' .
                preg_quote($firstChar, '/') . '[imsxeADSUXJu]*$/';

            if (preg_match($pattern, $value)) {
                // Teste la compilation de la regex
                return @preg_match($value, '') !== false;
            }
        }

        return false;
    }

    /**
     * Convertit une chaîne en kebab-case.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en kebab-case
     */
    public static function kebab(string $value): string
    {
		if (class_exists(Convert::class)) {
			return static::jawiraConvert($value, 'kebab');
		}

        return static::snake($value, '-');
    }

    /**
     * Retourne la longueur d'une chaîne donnée.
     *
     * @param string $value Chaîne à mesurer
     * @param string|null $encoding Encodage à utiliser (par défaut: UTF-8)
	 *
     * @return int Longueur de la chaîne
     */
    public static function length(string $value, ?string $encoding = null): int
    {
        return mb_strlen($value, $encoding);
    }

    /**
     * Limite le nombre de caractères dans une chaîne.
     *
     * @param string $value Chaîne à limiter
     * @param int $limit Limite de caractères (par défaut: 100)
     * @param string $end Suffixe à ajouter si tronqué (par défaut: '...')
     * @param bool $preserveWords Préserver les mots complets (par défaut: false)
	 *
     * @return string Chaîne tronquée
     */
    public static function limit(string $value, int $limit = 100, string $end = '...', bool $preserveWords = false): string
    {
        if (mb_strwidth($value, 'UTF-8') <= $limit) {
            return $value;
        }

        if (! $preserveWords) {
            return rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8')) . $end;
        }

        $value = trim(preg_replace('/[\n\r]+/', ' ', strip_tags($value)));

        $trimmed = rtrim(mb_strimwidth($value, 0, $limit, '', 'UTF-8'));

        if (mb_substr($value, $limit, 1, 'UTF-8') === ' ') {
            return $trimmed . $end;
        }

        return (preg_replace("/(.*)\s.*/", '$1', $trimmed) ?? $trimmed) . $end;
    }

    /**
     * Convertit la chaîne donnée en minuscules.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en minuscules
     */
    public static function lower(string $value): string
    {
        return mb_strtolower($value, 'UTF-8');
    }

    /**
     * Limite le nombre de mots dans une chaîne.
     *
     * @param string $value Chaîne à limiter
     * @param int $words Nombre maximum de mots (par défaut: 100)
     * @param string $end Suffixe à ajouter si tronqué (par défaut: '...')
     * @return string Chaîne tronquée par mots
     */
    public static function words(string $value, int $words = 100, string $end = '...'): string
    {
        preg_match('/^\s*+(?:\S++\s*+){1,' . $words . '}/u', $value, $matches);

        if (! isset($matches[0]) || static::length($value) === static::length($matches[0])) {
            return $value;
        }

        return rtrim($matches[0]) . $end;
    }

    /**
     * Convertit le Markdown GitHub en HTML.
     *
     * @param string $string Chaîne Markdown
     * @param array $options Options
     * @param array $extensions Extensions
	 *
     * @return string HTML
     */
    public static function markdown(string $string, array $options = [], array $extensions = []): string
    {
		if (! class_exists('\League\CommonMark\GithubFlavoredMarkdownConverter')) {
			return $string;
		}

		$converter = new \League\CommonMark\GithubFlavoredMarkdownConverter($options);

        $environment = $converter->getEnvironment();

        foreach ($extensions as $extension) {
            $environment->addExtension($extension);
        }

        return (string) $converter->convert($string);
    }

    /**
     * Convertit le Markdown en ligne en HTML.
     *
     * @param string $string Chaîne Markdown
     * @param array $options Options
     * @param array $extensions Extensions
	 *
     * @return string HTML
     */
    public static function inlineMarkdown(string $string, array $options = [], array $extensions = []): string
    {
        // Implémentation simplifiée
        return static::markdown($string, $options, $extensions);
    }

    /**
     * Masque une partie d'une chaîne avec un caractère répété.
     *
     * @param string $string Chaîne source
     * @param string $character Caractère de masquage
     * @param int $index Position de départ du masquage
     * @param int|null $length Longueur à masquer (null = jusqu'à la fin)
     * @param string $encoding Encodage (par défaut: 'UTF-8')
	 *
     * @return string Chaîne masquée
     */
    public static function mask(string $string, string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): string
    {
        if ($character === '') {
            return $string;
        }

        $segment = mb_substr($string, $index, $length, $encoding);

        if ($segment === '') {
            return $string;
        }

        $strlen     = mb_strlen($string, $encoding);
        $startIndex = $index;

        if ($index < 0) {
            $startIndex = $index < -$strlen ? 0 : $strlen + $index;
        }

        $start      = mb_substr($string, 0, $startIndex, $encoding);
        $segmentLen = mb_strlen($segment, $encoding);
        $end        = mb_substr($string, $startIndex + $segmentLen);

        return $start . str_repeat(mb_substr($character, 0, 1, $encoding), $segmentLen) . $end;
    }

    /**
     * Récupère la chaîne correspondant au motif donné.
     *
     * @param string $pattern Pattern regex
     * @param string $subject Chaîne source
	 *
     * @return string Chaîne correspondante ou chaîne vide
     */
    public static function match(string $pattern, string $subject): string
    {
        preg_match($pattern, $subject, $matches);

        if (! $matches) {
            return '';
        }

        return $matches[1] ?? $matches[0];
    }

    /**
     * Détermine si une chaîne donnée correspond à un motif regex.
     *
     * @param string|iterable<string> $pattern Motif(s) regex
     * @param string $value Valeur à tester
	 *
     * @return bool true si la chaîne correspond à l'un des motifs
     */
    public static function isMatch(string|iterable $pattern, string $value): bool
    {
        $value = (string) $value;

        if (! is_iterable($pattern)) {
            $pattern = [$pattern];
        }

        foreach ($pattern as $pattern) {
            $pattern = (string) $pattern;

            if (preg_match($pattern, $value) === 1) {
                return true;
            }
        }

        return false;
    }

    /**
     * Récupère toutes les chaînes correspondant au motif donné.
     *
     * @param string $pattern Pattern regex
     * @param string $subject Chaîne source
	 *
     * @return Collection<string> Collection des correspondances
     */
    public static function matchAll(string $pattern, string $subject): Collection
    {
        preg_match_all($pattern, $subject, $matches);

        if (empty($matches[0])) {
            return new Collection();
        }

        return new Collection($matches[1] ?? $matches[0]);
    }

    /**
     * Supprime tous les caractères non numériques d'une chaîne.
     *
     * @param string $value Chaîne source
	 *
     * @return string Chaîne avec uniquement des chiffres
     */
    public static function numbers(string $value): string
    {
        return preg_replace('/[^0-9]/', '', $value) ?? '';
    }

    /**
     * Remplit les deux côtés d'une chaîne avec un autre caractère.
     *
     * @param string $value Chaîne à remplir
     * @param int $length Longueur totale souhaitée
     * @param string $pad Caractère de remplissage (par défaut: espace)
	 *
     * @return string Chaîne remplie des deux côtés
     */
    public static function padBoth(string $value, int $length, string $pad = ' '): string
    {
		if (function_exists('mb_str_pad')) {
			return mb_str_pad($value, $length, $pad, STR_PAD_BOTH);
		}

        $short      = max(0, $length - mb_strlen($value));
        $shortLeft  = floor($short / 2);
        $shortRight = ceil($short / 2);

        return mb_substr(str_repeat($pad, (int) $shortLeft), 0, (int) $shortLeft) .
               $value .
               mb_substr(str_repeat($pad, (int) $shortRight), 0, (int) $shortRight);
    }

    /**
     * Remplit le côté gauche d'une chaîne avec un autre caractère.
     *
     * @param string $value Chaîne à remplir
     * @param int $length Longueur totale souhaitée
     * @param string $pad Caractère de remplissage (par défaut: espace)
     * @return string Chaîne remplie à gauche
     */
    public static function padLeft(string $value, int $length, string $pad = ' '): string
    {
		if (function_exists('mb_str_pad')) {
			return mb_str_pad($value, $length, $pad, STR_PAD_LEFT);
		}

        $short = max(0, $length - mb_strlen($value));

        return mb_substr(str_repeat($pad, $short), 0, $short) . $value;
    }

    /**
     * Remplit le côté droit d'une chaîne avec un autre caractère.
     *
     * @param string $value Chaîne à remplir
     * @param int $length Longueur totale souhaitée
     * @param string $pad Caractère de remplissage (par défaut: espace)
     * @return string Chaîne remplie à droite
     */
    public static function padRight(string $value, int $length, string $pad = ' '): string
    {
		if (function_exists('mb_str_pad')) {
			return mb_str_pad($value, $length, $pad, STR_PAD_RIGHT);
		}

        $short = max(0, $length - mb_strlen($value));

        return $value . mb_substr(str_repeat($pad, $short), 0, $short);
    }

    /**
     * Parse un callback de style Class[@]method en classe et méthode.
     *
     * @param string $callback Callback à parser
     * @param string|null $default Valeur par défaut pour la méthode
     * @return array<int, string|null> [classe, méthode]
     */
    public static function parseCallback(string $callback, ?string $default = null): array
    {
        if (static::contains($callback, "@anonymous\0")) {
            if (static::substrCount($callback, '@') > 1) {
                return [
                    static::beforeLast($callback, '@'),
                    static::afterLast($callback, '@'),
                ];
            }

            return [$callback, $default];
        }

        return static::contains($callback, '@') ? explode('@', $callback, 2) : [$callback, $default];
    }

    /**
     * Génère un mot de passe aléatoire sécurisé.
     *
     * @param int $length Longueur du mot de passe (par défaut: 32)
     * @param bool $letters Inclure des lettres (par défaut: true)
     * @param bool $numbers Inclure des chiffres (par défaut: true)
     * @param bool $symbols Inclure des symboles (par défaut: true)
     * @param bool $spaces Inclure des espaces (par défaut: false)
	 *
     * @return string Mot de passe généré
     */
    public static function password(int $length = 32, bool $letters = true, bool $numbers = true, bool $symbols = true, bool $spaces = false): string
    {
        $password = new Collection();

        $options = (new Collection([
            'letters' => $letters === true ? [
                'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k',
                'l', 'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v',
                'w', 'x', 'y', 'z', 'A', 'B', 'C', 'D', 'E', 'F', 'G',
                'H', 'I', 'J', 'K', 'L', 'M', 'N', 'O', 'P', 'Q', 'R',
                'S', 'T', 'U', 'V', 'W', 'X', 'Y', 'Z',
            ] : null,
            'numbers' => $numbers === true ? [
                '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
            ] : null,
            'symbols' => $symbols === true ? [
                '~', '!', '#', '$', '%', '^', '&', '*', '(', ')', '-',
                '_', '.', ',', '<', '>', '?', '/', '\\', '{', '}', '[',
                ']', '|', ':', ';',
            ] : null,
            'spaces' => $spaces === true ? [' '] : null,
        ]))
            ->filter()
            ->each(fn ($c) => $password->push($c[random_int(0, count($c) - 1)]))
            ->flatten();

        $length = $length - $password->count();

        return $password->merge($options->pipe(
            fn ($c) => Collection::times($length, fn () => $c[random_int(0, $c->count() - 1)])
        ))->shuffle()->implode('');
    }

    /**
     * Trouve la position de la première occurrence d'une sous-chaîne.
     *
     * @param string $haystack Chaîne dans laquelle chercher
     * @param string $needle Sous-chaîne à rechercher
     * @param int $offset Position de départ
     * @param string|null $encoding Encodage
	 *
     * @return int|false Position ou false si non trouvé
     */
    public static function position(string $haystack, string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        return mb_strpos($haystack, $needle, $offset, $encoding);
    }

    /**
     * Récupère la forme plurielle d'un mot anglais.
     *
     * @param string $value Mot au singulier
     * @param array|Countable|int $count Nombre pour décider du pluriel (par défaut: 2)
     * @param  bool  $prependCount
	 *
     * @return string Mot au pluriel
     */
    public static function plural(string $value, array|Countable|int $count = 2, $prependCount = false): string
    {
        if (is_countable($count)) {
            $count = count($count);
        }

		if ($count < 2) {
			return $value;
		}

        return ($prependCount ? (string) $count . ' ' : '') . Inflector::pluralize($value);
    }

    /**
     * Met au pluriel le dernier mot d'une chaîne en StudlyCaps.
     *
     * @param string $value Chaîne en StudlyCaps
     * @param array|Countable|int $count Nombre pour décider du pluriel (par défaut: 2)
	 *
     * @return string Chaîne avec dernier mot au pluriel
     */
    public static function pluralStudly(string $value, array|Countable|int $count = 2): string
    {
        $parts = preg_split('/(.)(?=[A-Z])/u', $value, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($parts === false || count($parts) < 2) {
            return static::plural($value, $count);
        }

        $lastWord = array_pop($parts);

        return implode('', $parts) . static::plural($lastWord, $count);
    }

	/**
     * Met au pluriel le dernier mot d'une chaîne en PascalCaps.
     *
     * @param string $value Chaîne en PascalCaps
     * @param array|Countable|int $count Nombre pour décider du pluriel (par défaut: 2)
	 *
     * @return string Chaîne avec dernier mot au pluriel
     */
    public static function pluralPascal(string $value, array|Countable|int $count = 2): string
    {
        return static::pluralStudly($value, $count);
    }

    /**
     * Encode une chaîne sous forme de chaîne entre guillemets, si nécessaire.
     *
     * Si une chaîne contient des caractères non autorisés par la construction "token"
     * dans la spécification HTTP, elle est échappée et placée entre guillemets.
     *
     * @credit <a href="symfony.com">Symfony - Symfony\Component\HttpFoundation::quote</a>
     * @param string $value Chaîne à encoder
     * @return string Chaîne encodée
     */
    public static function quote(string $value): string
    {
        if (preg_match('/^[a-z0-9!#$%&\'*.^_`|~-]+$/i', $value)) {
            return $value;
        }

        return '"' . addcslashes($value, '"\\"') . '"';
    }

    /**
     * Génère une chaîne alphanumérique vraiment "aléatoire".
     *
     * @param int $length Longueur de la chaîne (par défaut: 16)
     * @return string Chaîne aléatoire
     */
    public static function random(int $length = 16): string
    {
        return (static::$randomStringFactory ?? static function ($length) {
            $string = '';

            while (($len = strlen($string)) < $length) {
                $size = $length - $len;

                $bytesSize = (int) ceil(($size) / 3) * 3;

                $bytes = random_bytes($bytesSize);

                $string .= substr(str_replace(['/', '+', '='], '', base64_encode($bytes)), 0, $size);
            }

            return $string;
        })($length);
    }

    /**
     * Définit le callback qui sera utilisé pour générer des chaînes aléatoires.
     *
     * @param callable|null $factory Factory de génération de chaînes
     */
    public static function createRandomStringsUsing(?callable $factory = null): void
    {
        static::$randomStringFactory = $factory;
    }

    /**
     * Définit la séquence qui sera utilisée pour générer des chaînes aléatoires.
     *
     * @param array<int, string> $sequence Séquence de chaînes
     * @param callable|null $whenMissing Callback si la séquence est épuisée
     */
    public static function createRandomStringsUsingSequence(array $sequence, ?callable $whenMissing = null): void
    {
        $next = 0;

        $whenMissing ??= static function ($length) use (&$next) {
            $factoryCache = static::$randomStringFactory;

            static::$randomStringFactory = null;

            $randomString = static::random($length);

            static::$randomStringFactory = $factoryCache;

            $next++;

            return $randomString;
        };

        static::createRandomStringsUsing(static function ($length) use (&$next, $sequence, $whenMissing) {
            if (array_key_exists($next, $sequence)) {
                return $sequence[$next++];
            }

            return $whenMissing($length);
        });
    }

    /**
     * Indique que les chaînes aléatoires doivent être créées normalement
     * et non pas en utilisant une factory personnalisée.
     */
    public static function createRandomStringsNormally(): void
    {
        static::$randomStringFactory = null;
    }

    /**
     * Répète la chaîne donnée.
     *
     * @param string $string Chaîne à répéter
     * @param int $times Nombre de répétitions
	 *
     * @return string Chaîne répétée
     */
    public static function repeat(string $string, int $times): string
    {
        return str_repeat($string, $times);
    }

    /**
     * Remplace une valeur donnée dans la chaîne séquentiellement avec un tableau.
     *
     * @param string $search Valeur à rechercher
     * @param iterable<string> $replace Valeurs de remplacement
     * @param string $subject Chaîne source
	 *
     * @return string Chaîne avec remplacements
     */
    public static function replaceArray(string $search, iterable $replace, string $subject): string
    {
        if ($replace instanceof Traversable) {
            $replace = Arr::from($replace);
        }

        $segments = explode($search, $subject);

        $result = array_shift($segments);

        foreach ($segments as $segment) {
            $result .= self::toStringOr(array_shift($replace) ?? $search, $search) . $segment;
        }

        return $result;
    }

    /**
     * Convertit la valeur donnée en chaîne de caractères ou renvoie la valeur de repli donnée en cas d'échec.
     */
    private static function toStringOr(mixed $value, string $fallback): string
    {
        try {
            return (string) $value;
        } catch (Throwable) {
            return $fallback;
        }
    }

    /**
     * Remplace la valeur donnée dans la chaîne donnée.
     *
     * @param iterable<string>|string $search Valeur(s) à rechercher
     * @param iterable<string>|string $replace Valeur(s) de remplacement
     * @param iterable<string>|string $subject Chaîne(s) source
     * @param bool $caseSensitive Sensible à la casse (par défaut: true)
	 *
     * @return string Chaîne avec remplacements
     */
    public static function replace(iterable|string $search, iterable|string $replace, iterable|string $subject, bool $caseSensitive = true): string
    {
        if ($search instanceof Traversable) {
            $search = Arr::from($search);
        }

        if ($replace instanceof Traversable) {
            $replace = Arr::from($replace);
        }

        if ($subject instanceof Traversable) {
            $subject = Arr::from($subject);
        }

        return $caseSensitive
            ? str_replace($search, $replace, $subject)
            : str_ireplace($search, $replace, $subject);
    }

    /**
     * Remplace la première occurrence d'une valeur donnée dans la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     * @param string $subject Chaîne source
	 *
     * @return string Chaîne avec remplacement
     */
    public static function replaceFirst(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Remplace la dernière occurrence d'une valeur donnée dans la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     * @param string $subject Chaîne source
	 *
     * @return string Chaîne avec remplacement
     */
    public static function replaceLast(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        $position = strrpos($subject, $search);

        if ($position !== false) {
            return substr_replace($subject, $replace, $position, strlen($search));
        }

        return $subject;
    }

    /**
     * Remplace la première occurrence si elle apparaît au début de la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     * @param string $subject Chaîne source
	 *
     * @return string Chaîne modifiée
     */
    public static function replaceStart(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        if (static::startsWith($subject, $search)) {
            return static::replaceFirst($search, $replace, $subject);
        }

        return $subject;
    }

    /**
     * Remplace la dernière occurrence si elle apparaît à la fin de la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     * @param string $subject Chaîne source
	 *
     * @return string Chaîne modifiée
     */
    public static function replaceEnd(string $search, string $replace, string $subject): string
    {
        if ($search === '') {
            return $subject;
        }

        if (static::endsWith($subject, $search)) {
            return static::replaceLast($search, $replace, $subject);
        }

        return $subject;
    }

    /**
     * Remplace les motifs correspondant à l'expression régulière donnée.
     *
     * @param array|string $pattern Motif regex
     * @param Closure|list<string>|string $replace Remplacement
     * @param array|string $subject Sujet
     * @param int $limit Limite de remplacements
	 *
     * @return string|array|null Résultat
     */
    public static function replaceMatches(array|string $pattern, Closure|array|string $replace, array|string $subject, int $limit = -1): string|array|null
    {
        if ($replace instanceof Closure) {
            return preg_replace_callback($pattern, $replace, $subject, $limit);
        }

        return preg_replace($pattern, $replace, $subject, $limit);
    }

    /**
     * Supprime toute occurrence de la chaîne donnée dans le sujet.
     *
     * @param iterable<string>|string $search Valeur(s) à supprimer
     * @param string $subject Chaîne source
     * @param bool $caseSensitive Sensible à la casse (par défaut: true)
	 *
     * @return string Chaîne sans les occurrences
     */
    public static function remove(iterable|string $search, string $subject, bool $caseSensitive = true): string
    {
        if ($search instanceof Traversable) {
            $search = Arr::from($search);
        }

        return $caseSensitive
            ? str_replace($search, '', $subject)
            : str_ireplace($search, '', $subject);
    }

    /**
     * Supprime le dernier mot du texte.
     *
     * @param string $text Texte source
	 *
     * @return string Texte sans le dernier mot
     */
    public static function removeLastWord(string $text): string
    {
        $spacepos = mb_strrpos($text, ' ');

        if ($spacepos !== false) {
            $lastWord = mb_substr($text, $spacepos + 1);

            // Certaines langues sont écrites sans séparation de mots.
            // Nous reconnaissons une chaîne comme un mot si elle ne contient pas de caractères pleine largeur.
            if (mb_strwidth($lastWord) === mb_strlen($lastWord)) {
                $text = mb_substr($text, 0, $spacepos);
            }

            return $text;
        }

        return '';
    }

    /**
     * Inverse la chaîne donnée.
     *
     * @param string $value Chaîne à inverser
	 *
     * @return string Chaîne inversée
     */
    public static function reverse(string $value): string
    {
        return implode('', array_reverse(mb_str_split($value)));
    }

    /**
     * Commence une chaîne avec une seule instance d'une valeur donnée.
     *
     * @param string $value Chaîne source
     * @param string $prefix Préfixe à ajouter
	 *
     * @return string Chaîne avec préfixe
     */
    public static function start(string $value, string $prefix): string
    {
        $quoted = preg_quote($prefix, '/');

        return $prefix . preg_replace('/^(?:' . $quoted . ')+/u', '', $value);
    }

    /**
     * Convertit la chaîne donnée en majuscules.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en majuscules
     */
    public static function upper(string $value): string
    {
        return mb_strtoupper($value, 'UTF-8');
    }

    /**
     * Convertit la chaîne donnée en title case.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en title case
     */
    public static function title(string $value): string
    {
        return mb_convert_case($value, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Convertit la chaîne donnée en title case pour chaque mot.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en headline case
     */
    public static function headline(string $value): string
    {
        $parts = mb_split('\s+', $value);

        $parts = count($parts) > 1
            ? array_map(static::title(...), $parts)
            : array_map(static::title(...), static::ucsplit(implode('_', $parts)));

        $collapsed = static::replace(['-', '_', ' '], '_', implode('_', $parts));

        return implode(' ', array_filter(explode('_', $collapsed)));
    }

    /**
     * Convertit la chaîne donnée en casse de titre APA.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en casse APA
	 *
	 * @see: https://apastyle.apa.org/style-grammar-guidelines/capitalization/title-case
     */
    public static function apa(string $value): string
    {
        if (trim($value) === '') {
            return $value;
        }

        $minorWords = [
            'and', 'as', 'but', 'for', 'if', 'nor', 'or', 'so', 'yet', 'a', 'an',
            'the', 'at', 'by', 'in', 'of', 'off', 'on', 'per', 'to', 'up', 'via',
        ];

        $endPunctuation = ['.', '!', '?', ':', '—', ','];

        $words = mb_split('\s+', $value);
        $wordCount = count($words);

        for ($i = 0; $i < $wordCount; $i++) {
            $lowercaseWord = mb_strtolower($words[$i]);

            if (str_contains($lowercaseWord, '-')) {
                $hyphenatedWords = explode('-', $lowercaseWord);

                $hyphenatedWords = array_map(function ($part) use ($minorWords) {
                    return (in_array($part, $minorWords) && mb_strlen($part) <= 3)
                        ? $part
                        : mb_strtoupper(mb_substr($part, 0, 1)) . mb_substr($part, 1);
                }, $hyphenatedWords);

                $words[$i] = implode('-', $hyphenatedWords);
            } else {
                if (in_array($lowercaseWord, $minorWords) &&
                    mb_strlen($lowercaseWord) <= 3 &&
                    ! ($i === 0 || in_array(mb_substr($words[$i - 1], -1), $endPunctuation))) {
                    $words[$i] = $lowercaseWord;
                } else {
                    $words[$i] = mb_strtoupper(mb_substr($lowercaseWord, 0, 1)) . mb_substr($lowercaseWord, 1);
                }
            }
        }

        return implode(' ', $words);
    }

    /**
     * Récupère la forme singulière d'un mot anglais.
     *
     * @param string $value Mot au pluriel
	 *
     * @return string Mot au singulier
     */
    public static function singular(string $value): string
    {
		return Inflector::singularize($value);
    }

    /**
     * Génère un "slug" convivial pour les URL à partir d'une chaîne donnée.
     *
     * @param string $title Titre à slugifier
     * @param string $separator Séparateur (par défaut: '-')
     * @param string|null $language Langue pour la translittération
     * @param array<string, string> $dictionary Dictionnaire de remplacements
	 *
     * @return string Slug généré
     */
    public static function slug(string $title, string $separator = '-', ?string $language = 'en', array $dictionary = ['@' => 'at']): string
    {
        $title = $language ? static::ascii($title, $language) : $title;

        // Convertit tous les tirets/underscores en séparateur
        $flip = $separator === '-' ? '_' : '-';

        $title = preg_replace('![' . preg_quote($flip) . ']+!u', $separator, $title) ?? $title;

        // Remplace les mots du dictionnaire
        foreach ($dictionary as $key => $value) {
            $dictionary[$key] = $separator . $value . $separator;
        }

        $title = str_replace(array_keys($dictionary), array_values($dictionary), $title);

        // Supprime tous les caractères qui ne sont pas le séparateur, lettres, nombres ou espaces
        $title = preg_replace('![^' . preg_quote($separator) . '\pL\pN\s]+!u', '', static::lower($title)) ?? '';

        // Remplace tous les caractères de séparateur et espaces par un seul séparateur
        $title = preg_replace('![' . preg_quote($separator) . '\s]+!u', $separator, $title) ?? '';

        return trim($title, $separator);
    }

    /**
     * Convertit une chaîne en snake_case.
     *
     * @param string $value Chaîne à convertir
     * @param string $delimiter Délimateur (par défaut: '_')
	 *
     * @return string Chaîne en snake_case
     */
    public static function snake(string $value, string $delimiter = '_'): string
    {
		if (class_exists(Convert::class)) {
			// return static::jawiraConvert($value, 'snake');
		}

        $key = $value . $delimiter;

        if (isset(static::$snakeCache[$key])) {
            return static::$snakeCache[$key];
        }

        if (! ctype_lower($value)) {
            $value = preg_replace('/\s+/u', '', ucwords($value)) ?? $value;
            $value = static::lower(preg_replace('/(.)(?=[A-Z])/u', '$1' . $delimiter, $value) ?? $value);
        }

        return static::$snakeCache[$key] = $value;
    }

    /**
     * Supprime les espaces des deux extrémités d'une chaîne.
     *
     * @param string $value Chaîne à trimer
     * @param string|null $charlist Liste de caractères à supprimer
	 *
     * @return string Chaîne trimée
     */
    public static function trim(string $value, ?string $charlist = null): string
    {
        if ($charlist === null) {
            $trimDefaultCharacters = " \n\r\t\v\0";

            return preg_replace('~^[\s' . self::INVISIBLE_CHARACTERS . $trimDefaultCharacters . ']+|[\s' . self::INVISIBLE_CHARACTERS . $trimDefaultCharacters . ']+$~u', '', $value) ?? trim($value);
        }

        return trim($value, $charlist);
    }

    /**
     * Supprime les espaces du début d'une chaîne.
     *
     * @param string $value Chaîne à trimer
     * @param string|null $charlist Liste de caractères à supprimer
	 *
     * @return string Chaîne trimée à gauche
     */
    public static function ltrim(string $value, ?string $charlist = null): string
    {
        if ($charlist === null) {
            $ltrimDefaultCharacters = " \n\r\t\v\0";

            return preg_replace('~^[\s' . self::INVISIBLE_CHARACTERS . $ltrimDefaultCharacters . ']+~u', '', $value) ?? ltrim($value);
        }

        return ltrim($value, $charlist);
    }

    /**
     * Supprime les espaces de la fin d'une chaîne.
     *
     * @param string $value Chaîne à trimer
     * @param string|null $charlist Liste de caractères à supprimer
     * @return string Chaîne trimée à droite
     */
    public static function rtrim(string $value, ?string $charlist = null): string
    {
        if ($charlist === null) {
            $rtrimDefaultCharacters = " \n\r\t\v\0";

            return preg_replace('~[\s' . self::INVISIBLE_CHARACTERS . $rtrimDefaultCharacters . ']+$~u', '', $value) ?? rtrim($value);
        }

        return rtrim($value, $charlist);
    }

    /**
     * Supprime tous les espaces "extra" de la chaîne donnée.
     *
     * @param string $value Chaîne à nettoyer
	 *
     * @return string Chaîne nettoyée
     */
    public static function squish(string $value): string
    {
        return preg_replace('~(\s|\x{3164})+~u', ' ', static::trim($value)) ?? '';
    }

    /**
     * Détermine si une chaîne donnée commence par une sous-chaîne donnée.
     *
     * @param string $haystack Chaîne à vérifier
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
	 *
     * @return bool true si la chaîne commence par l'une des sous-chaînes
     */
    public static function startsWith(string $haystack, iterable|string $needles): bool
    {
        if (! is_iterable($needles)) {
            $needles = [$needles];
        }

        foreach ($needles as $needle) {
            if ((string) $needle !== '' && str_starts_with($haystack, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Détermine si une chaîne donnée ne commence pas par une sous-chaîne donnée.
     *
     * @param string $haystack Chaîne à vérifier
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
	 *
     * @return bool true si la chaîne ne commence pas par les sous-chaînes
     */
    public static function doesntStartWith(string $haystack, iterable|string $needles): bool
    {
        return ! static::startsWith($haystack, $needles);
    }

    /**
     * Récupère la longueur d'une chaîne.
     *
     * Options :
     * - `html` Si true, les entités HTML seront traitées comme des caractères décodés.
     * - `trimWidth` Si true, la largeur sera retournée.
     *
     * @param string $text Texte à mesurer
     * @param array<string, mixed> $options Options de mesure
	 *
     * @return int Longueur de la chaîne
     */
    public static function strlen(string $text, array $options): int
    {
        if (empty($options['trimWidth'])) {
            $strlen = 'mb_strlen';
        } else {
            $strlen = 'mb_strwidth';
        }

        if (empty($options['html'])) {
            return $strlen($text);
        }

        $pattern = '/&[0-9a-z]{2,8};|&#[0-9]{1,7};|&#x[0-9a-f]{1,6};/i';
        $replace = preg_replace_callback(
            $pattern,
            static function ($match) use ($strlen) {
                $utf8 = html_entity_decode($match[0], ENT_HTML5 | ENT_QUOTES, 'UTF-8');
                return str_repeat(' ', $strlen($utf8, 'UTF-8'));
            },
            $text
        ) ?? $text;

        return $strlen($replace);
    }

    /**
     * Convertit une valeur en StudlyCaps.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en StudlyCaps
     */
    public static function studly(string $value): string
    {
        $key = $value;

        if (isset(static::$studlyCache[$key])) {
            return static::$studlyCache[$key];
        }

        $words = mb_split('\s+', static::replace(['-', '_'], ' ', $value));

        $studlyWords = array_map(static fn ($word) => static::ucfirst($word), $words);

        return static::$studlyCache[$key] = implode($studlyWords);
    }

    /**
     * Convertit une valeur en PascalCase.
     *
     * @param string $value Chaîne à convertir
	 *
     * @return string Chaîne en PascalCase
     */
    public static function pascal(string $value): string
    {
		if (class_exists(Convert::class)) {
			return static::jawiraConvert($value, 'pascal');
		}

        return static::studly($value);
    }

    /**
     * Retourne la partie de chaîne spécifiée par les paramètres start et length.
     *
     * Options :
     * - `html` Si true, les entités HTML seront traitées comme des caractères décodés.
     * - `trimWidth` Si true, sera tronqué avec la largeur spécifiée.
     *
     * @param string $string Texte source
     * @param int $start Position de départ
     * @param int|null $length Longueur à extraire (null = jusqu'à la fin)
     * @param string|null $encoding Encodage
	 *
     * @return string Sous-chaîne extraite
     */
    public static function substr(string $string, int $start, ?int $length = null, ?string $encoding = null): string
    {
		return mb_substr($string, $start, $length, $encoding);
    }

    /**
     * Retourne le nombre d'occurrences d'une sous-chaîne.
     *
     * @param string $haystack Chaîne dans laquelle chercher
     * @param string $needle Sous-chaîne à compter
     * @param int $offset Position de départ
     * @param int|null $length Longueur à parcourir
	 *
     * @return int Nombre d'occurrences
     */
    public static function substrCount(string $haystack, string $needle, int $offset = 0, ?int $length = null): int
    {
        if (null !== $length) {
            return substr_count($haystack, $needle, $offset, $length);
        }

        return substr_count($haystack, $needle, $offset);
    }

    /**
     * Remplace du texte dans une partie d'une chaîne.
     *
     * @param list<string>|string $string Chaîne source
     * @param list<string>|string $replace Texte de remplacement
     * @param int|list<int> $offset Position de départ
     * @param int|list<int>|null $length Longueur à remplacer
	 *
     * @return list<string>|string Chaîne modifiée
     */
    public static function substrReplace(array|string $string, array|string $replace, int|array $offset = 0, int|array|null $length = null): array|string
    {
        if ($length === null) {
            $length = is_array($string) ? array_map(static::length(...), $string) : static::length($string);
        }

        return substr_replace($string, $replace, $offset, $length);
    }

    /**
     * Prend les N premiers ou derniers caractères d'une chaîne.
     *
     * @param string $string Chaîne source
     * @param int $limit Nombre de caractères
	 *
     * @return string Sous-chaîne
     */
    public static function take(string $string, int $limit): string
    {
        if ($limit < 0) {
            return static::substr($string, $limit);
        }

        return static::substr($string, 0, $limit);
    }

    /**
     * Échange plusieurs mots-clés dans une chaîne avec d'autres mots-clés.
     *
     * @param array<string, string> $map Tableau de correspondances
     * @param string $subject Chaîne source
     * @return string Chaîne modifiée
     */
    public static function swap(array $map, string $subject): string
    {
        return strtr($subject, $map);
    }

    /**
     * Convertit une chaîne en Base64.
     *
     * @param string $string Chaîne à encoder
	 *
     * @return string Chaîne encodée en Base64
     */
    public static function toBase64(string $string): string
    {
        return base64_encode($string);
    }

    /**
     * Décode une chaîne Base64.
     *
     * @param string $string Chaîne Base64
     * @param bool $strict Mode strict
	 *
     * @return string|false Chaîne décodée ou false en cas d'erreur
     */
    public static function fromBase64(string $string, bool $strict = false): string|false
    {
        return base64_decode($string, $strict);
    }

    /**
     * Met le premier caractère d'une chaîne en minuscule.
     *
     * @param string $string Chaîne à modifier
	 *
     * @return string Chaîne modifiée
     */
    public static function lcfirst(string $string): string
    {
        return static::lower(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Met le premier caractère d'une chaîne en majuscule.
     *
     * @param string $string Chaîne à modifier
	 *
     * @return string Chaîne modifiée
     */
    public static function ucfirst(string $string): string
    {
        return static::upper(static::substr($string, 0, 1)) . static::substr($string, 1);
    }

    /**
     * Met en majuscule la première lettre de chaque mot dans une chaîne.
     *
     * @param string $string Chaîne à modifier
     * @param string $separators Séparateurs de mots
	 *
     * @return string Chaîne modifiée
     */
    public static function ucwords(string $string, string $separators = " \t\r\n\f\v"): string
    {
        $pattern = '/(^|[' . preg_quote($separators, '/') . '])(\p{Ll})/u';

        return preg_replace_callback($pattern, function ($matches) {
            return $matches[1] . mb_strtoupper($matches[2]);
        }, $string) ?? $string;
    }

    /**
     * Divise une chaîne en morceaux par caractères majuscules.
     *
     * @param string $string Chaîne à diviser
	 *
     * @return list<string> Tableau de morceaux
     */
    public static function ucsplit(string $string): array
    {
        return preg_split('/(?=\p{Lu})/u', $string, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Tronque le texte en partant de la fin.
     *
     * @param string $text Texte à tronquer
     * @param int $length Longueur maximale
     * @param array<string, mixed> $options Options de troncature
	 *
     * @return string Texte tronqué
     */
    public static function tail(string $text, int $length = 100, array $options = []): string
    {
        $default = [
            'ellipsis' => '...', 'exact' => true,
        ];
        $options += $default;

        $ellipsis = $options['ellipsis'];
        $exact    = $options['exact'];

        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $truncate = mb_substr($text, mb_strlen($text) - $length + mb_strlen($ellipsis));
        if (! $exact) {
            $spacepos = mb_strpos($truncate, ' ');
            $truncate = $spacepos === false ? '' : trim(mb_substr($truncate, $spacepos));
        }

        return $ellipsis . $truncate;
    }

    /**
     * Tokenise une chaîne en utilisant $separator, en ignorant les instances de $separator
     * qui apparaissent entre $leftBound et $rightBound.
     *
     * @param string $data Chaîne à tokeniser
     * @param string $separator Séparateur (par défaut: ',')
     * @param string $leftBound Délimiteur gauche (par défaut: '(')
     * @param string $rightBound Délimiteur droit (par défaut: ')')
	 *
     * @return array<int, string> Tableau de tokens
     */
    public static function tokenize(string $data, string $separator = ',', string $leftBound = '(', string $rightBound = ')'): array
    {
        if (empty($data)) {
            return [];
        }

        $depth   = 0;
        $offset  = 0;
        $buffer  = '';
        $results = [];
        $length  = strlen($data);
        $open    = false;

        while ($offset <= $length) {
            $tmpOffset = -1;
            $offsets   = [
                strpos($data, $separator, $offset),
                strpos($data, $leftBound, $offset),
                strpos($data, $rightBound, $offset),
            ];

            for ($i = 0; $i < 3; $i++) {
                if ($offsets[$i] !== false && ($offsets[$i] < $tmpOffset || $tmpOffset === -1)) {
                    $tmpOffset = $offsets[$i];
                }
            }
            if ($tmpOffset !== -1) {
                $buffer .= substr($data, $offset, ($tmpOffset - $offset));
                if (! $depth && $data[$tmpOffset] === $separator) {
                    $results[] = $buffer;
                    $buffer    = '';
                } else {
                    $buffer .= $data[$tmpOffset];
                }
                if ($leftBound !== $rightBound) {
                    if ($data[$tmpOffset] === $leftBound) {
                        $depth++;
                    }
                    if ($data[$tmpOffset] === $rightBound) {
                        $depth--;
                    }
                } else {
                    if ($data[$tmpOffset] === $leftBound) {
                        if (! $open) {
                            $depth++;
                            $open = true;
                        } else {
                            $depth--;
                        }
                    }
                }
                $offset = ++$tmpOffset;
            } else {
                $results[] = $buffer . substr($data, $offset);
                $offset    = $length + 1;
            }
        }
        if (empty($results) && ! empty($buffer)) {
            $results[] = $buffer;
        }

        return array_map('trim', $results);
    }

    /**
     * Crée une liste séparée par des virgules où les deux derniers éléments sont joints avec 'et'.
     *
     * @param list<string> $list Liste à joindre
     * @param string $separator Séparateur (par défaut: ', ')
     * @param string $and Mot pour "et" (par défaut: 'and')
	 *
     * @return string Liste formatée
     */
    public static function toList(array $list, string $separator = ', ', string $and = 'and'): string
    {
        if (count($list) > 1) {
            return implode($separator, array_slice($list, 0, -1)) . ' ' . $and . ' ' . array_pop($list);
        }

        return array_pop($list) ?? '';
    }

    /**
     * Convertit en UTF-8
     *
     * Tente de convertir une chaîne en UTF-8.
     *
     * @param string $str Chaîne à convertir
     * @param string $encoding Encodage source
     * @return string|false Chaîne encodée en UTF-8 ou FALSE en cas d'échec
     */
    public static function toUtf8(string $str, string $encoding): string|false
    {
        if (MB_ENABLED) {
            return mb_convert_encoding($str, 'UTF-8', $encoding);
        }

        if (ICONV_ENABLED) {
            return @iconv($encoding, 'UTF-8', $str);
        }

        return false;
    }

    /**
     * Génère un UUID (version 4).
     *
     * @return string UUID généré
     */
    public static function uuid(): string
    {
		return Uuid::v4();
    }

    /**
     * Génère un UUID (version 7).
     *
     * @return string UUID généré
     */
    public static function uuid7(): string
    {
		return Uuid::v7();
    }

    /**
     * Génère un ULID.
     *
     * @return string ULID généré
     */
    public static function ulid(): string
    {
        $chars = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';
        $ulid = '';

        // Partie timestamp (10 caractères)
        $time = microtime(true) * 1000;
        for ($i = 9; $i >= 0; $i--) {
            $mod = $time % 32;
            $ulid = $chars[$mod] . $ulid;
            $time = ($time - $mod) / 32;
        }

        // Partie aléatoire (16 caractères)
        for ($i = 0; $i < 16; $i++) {
            $ulid .= $chars[random_int(0, 31)];
        }

        return $ulid;
    }

    /**
     * Définit le callback qui sera utilisé pour générer des UUIDs.
     *
     * @param callable|null $factory Factory de génération d'UUIDs
     */
    public static function createUuidsUsing(?callable $factory = null): void
    {
        static::$uuidFactory = $factory;
    }

    /**
     * Indique que les UUIDs doivent être créés normalement.
     */
    public static function createUuidsNormally(): void
    {
        static::$uuidFactory = null;
    }

    /**
     * Gèle la génération d'UUIDs.
     *
     * @param Closure|null $callback Callback à exécuter
     * @return string UUID gelé
     */
    public static function freezeUuids(?Closure $callback = null): string
    {
        $uuid = static::uuid();
        static::createUuidsUsing(fn () => $uuid);

        if ($callback !== null) {
            try {
                $callback($uuid);
            } finally {
                static::createUuidsNormally();
            }
        }

        return $uuid;
    }

    /**
     * Définit le callback qui sera utilisé pour générer des ULIDs.
     *
     * @param callable|null $factory Factory de génération d'ULIDs
     */
    public static function createUlidsUsing(?callable $factory = null): void
    {
        static::$ulidFactory = $factory;
    }

    /**
     * Indique que les ULIDs doivent être créés normalement.
     */
    public static function createUlidsNormally(): void
    {
        static::$ulidFactory = null;
    }

    /**
     * Gèle la génération d'ULIDs.
     *
     * @param Closure|null $callback Callback à exécuter
     * @return string ULID gelé
     */
    public static function freezeUlids(?Closure $callback = null): string
    {
        $ulid = static::ulid();
        static::createUlidsUsing(fn () => $ulid);

        if ($callback !== null) {
            try {
                $callback($ulid);
            } finally {
                static::createUlidsNormally();
            }
        }

        return $ulid;
    }

    /**
     * Récupère le nombre de mots dans une chaîne.
     *
     * @param string $string Chaîne à compter
     * @param string|null $characters Caractères supplémentaires considérés comme faisant partie des mots
	 *
     * @return int Nombre de mots
     */
    public static function wordCount(string $string, ?string $characters = null): int
    {
        return str_word_count($string, 0, $characters);
    }

    /**
     * Enveloppe une chaîne à un nombre donné de caractères.
     *
     * @param string $string Chaîne à envelopper
     * @param int $characters Nombre de caractères par ligne
     * @param string $break Caractère de rupture
     * @param bool $cutLongWords Couper les longs mots
	 *
     * @return string Chaîne enveloppée
     */
    public static function wordWrap(string $string, int $characters = 75, string $break = "\n", bool $cutLongWords = false): string
    {
        return wordwrap($string, $characters, $break, $cutLongWords);
    }

    /**
     * Supprime toutes les chaînes des caches de casse.
     */
    public static function flushCache(): void
    {
        static::$snakeCache     = [];
        static::$camelCache     = [];
        static::$studlyCache    = [];
        static::$converterCache = [];
    }

    /**
     * Récupère l'identifiant de transliterator par défaut.
     *
     * @return string Identifiant de transliterator
     */
    public static function getTransliteratorId(): string
    {
        return static::$_defaultTransliteratorId;
    }

    /**
     * Définit l'identifiant de transliterator par défaut.
     *
     * @param string $transliteratorId Identifiant de transliterator
     */
    public static function setTransliteratorId(string $transliteratorId): void
    {
		static::$_defaultTransliteratorId = $transliteratorId;
    }

    /**
     * Retourne les remplacements pour la méthode ascii.
     *
     * Note: Adapté de Stringy\Stringy.
     *
     * @return array<string, array<int, string>> Tableau de correspondances ASCII
     *
     * @see https://github.com/danielstjules/Stringy/blob/3.1.0/LICENSE.txt
     */
    protected static function charsArray(): array
    {
        static $charsArray = null;

        if ($charsArray !== null) {
            return $charsArray;
        }

        return $charsArray = [
            '0'    => ['°', '₀', '۰', '０'],
            '1'    => ['¹', '₁', '۱', '１'],
            '2'    => ['²', '₂', '۲', '２'],
            '3'    => ['³', '₃', '۳', '３'],
            '4'    => ['⁴', '₄', '۴', '٤', '４'],
            '5'    => ['⁵', '₅', '۵', '٥', '５'],
            '6'    => ['⁶', '₆', '۶', '٦', '６'],
            '7'    => ['⁷', '₇', '۷', '７'],
            '8'    => ['⁸', '₈', '۸', '８'],
            '9'    => ['⁹', '₉', '۹', '９'],
            'a'    => ['à', 'á', 'ả', 'ã', 'ạ', 'ă', 'ắ', 'ằ', 'ẳ', 'ẵ', 'ặ', 'â', 'ấ', 'ầ', 'ẩ', 'ẫ', 'ậ', 'ā', 'ą', 'å', 'α', 'ά', 'ἀ', 'ἁ', 'ἂ', 'ἃ', 'ἄ', 'ἅ', 'ἆ', 'ἇ', 'ᾀ', 'ᾁ', 'ᾂ', 'ᾃ', 'ᾄ', 'ᾅ', 'ᾆ', 'ᾇ', 'ὰ', 'ά', 'ᾰ', 'ᾱ', 'ᾲ', 'ᾳ', 'ᾴ', 'ᾶ', 'ᾷ', 'а', 'أ', 'အ', 'ာ', 'ါ', 'ǻ', 'ǎ', 'ª', 'ა', 'अ', 'ا', 'ａ', 'ä', 'א'],
            'b'    => ['б', 'β', 'ب', 'ဗ', 'ბ', 'ｂ', 'ב'],
            'c'    => ['ç', 'ć', 'č', 'ĉ', 'ċ', 'ｃ'],
            'd'    => ['ď', 'ð', 'đ', 'ƌ', 'ȡ', 'ɖ', 'ɗ', 'ᵭ', 'ᶁ', 'ᶑ', 'д', 'δ', 'د', 'ض', 'ဍ', 'ဒ', 'დ', 'ｄ', 'ד'],
            'e'    => ['é', 'è', 'ẻ', 'ẽ', 'ẹ', 'ê', 'ế', 'ề', 'ể', 'ễ', 'ệ', 'ë', 'ē', 'ę', 'ě', 'ĕ', 'ė', 'ε', 'έ', 'ἐ', 'ἑ', 'ἒ', 'ἓ', 'ἔ', 'ἕ', 'ὲ', 'έ', 'е', 'ё', 'э', 'є', 'ə', 'ဧ', 'ေ', 'ဲ', 'ე', 'ए', 'إ', 'ئ', 'ｅ'],
            'f'    => ['ф', 'φ', 'ف', 'ƒ', 'ფ', 'ｆ', 'פ', 'ף'],
            'g'    => ['ĝ', 'ğ', 'ġ', 'ģ', 'г', 'ґ', 'γ', 'ဂ', 'გ', 'گ', 'ｇ', 'ג'],
            'h'    => ['ĥ', 'ħ', 'η', 'ή', 'ح', 'ه', 'ဟ', 'ှ', 'ჰ', 'ｈ', 'ה'],
            'i'    => ['í', 'ì', 'ỉ', 'ĩ', 'ị', 'î', 'ï', 'ī', 'ĭ', 'į', 'ı', 'ι', 'ί', 'ϊ', 'ΐ', 'ἰ', 'ἱ', 'ἲ', 'ἳ', 'ἴ', 'ἵ', 'ἶ', 'ἷ', 'ὶ', 'ί', 'ῐ', 'ῑ', 'ῒ', 'ΐ', 'ῖ', 'ῗ', 'і', 'ї', 'и', 'ဣ', 'ိ', 'ီ', 'ည်', 'ǐ', 'ი', 'इ', 'ی', 'ｉ', 'י'],
            'j'    => ['ĵ', 'ј', 'Ј', 'ჯ', 'ج', 'ｊ'],
            'k'    => ['ķ', 'ĸ', 'к', 'κ', 'Ķ', 'ق', 'ك', 'က', 'კ', 'ქ', 'ک', 'ｋ', 'ק'],
            'l'    => ['ł', 'ľ', 'ĺ', 'ļ', 'ŀ', 'л', 'λ', 'ل', 'လ', 'ლ', 'ｌ', 'ל'],
            'm'    => ['м', 'μ', 'م', 'မ', 'მ', 'ｍ', 'מ', 'ם'],
            'n'    => ['ñ', 'ń', 'ň', 'ņ', 'ŉ', 'ŋ', 'ν', 'н', 'ن', 'န', 'ნ', 'ｎ', 'נ'],
            'o'    => ['ó', 'ò', 'ỏ', 'õ', 'ọ', 'ô', 'ố', 'ồ', 'ổ', 'ỗ', 'ộ', 'ơ', 'ớ', 'ờ', 'ở', 'ỡ', 'ợ', 'ø', 'ō', 'ő', 'ŏ', 'ο', 'ὀ', 'ὁ', 'ὂ', 'ὃ', 'ὄ', 'ὅ', 'ὸ', 'ό', 'о', 'و', 'ို', 'ǒ', 'ǿ', 'º', 'ო', 'ओ', 'ｏ', 'ö'],
            'p'    => ['п', 'π', 'ပ', 'პ', 'پ', 'ｐ', 'פ', 'ף'],
            'q'    => ['ყ', 'ｑ'],
            'r'    => ['ŕ', 'ř', 'ŗ', 'р', 'ρ', 'ر', 'რ', 'ｒ', 'ר'],
            's'    => ['ś', 'š', 'ş', 'с', 'σ', 'ș', 'ς', 'س', 'ص', 'စ', 'ſ', 'ს', 'ｓ', 'ס'],
            't'    => ['ť', 'ţ', 'т', 'τ', 'ț', 'ت', 'ط', 'ဋ', 'တ', 'ŧ', 'თ', 'ტ', 'ｔ', 'ת'],
            'u'    => ['ú', 'ù', 'ủ', 'ũ', 'ụ', 'ư', 'ứ', 'ừ', 'ử', 'ữ', 'ự', 'û', 'ū', 'ů', 'ű', 'ŭ', 'ų', 'µ', 'у', 'ဉ', 'ု', 'ူ', 'ǔ', 'ǖ', 'ǘ', 'ǚ', 'ǜ', 'უ', 'उ', 'ｕ', 'ў', 'ü'],
            'v'    => ['в', 'ვ', 'ϐ', 'ｖ', 'ו'],
            'w'    => ['ŵ', 'ω', 'ώ', 'ဝ', 'ွ', 'ｗ'],
            'x'    => ['χ', 'ξ', 'ｘ'],
            'y'    => ['ý', 'ỳ', 'ỷ', 'ỹ', 'ỵ', 'ÿ', 'ŷ', 'й', 'ы', 'υ', 'ϋ', 'ύ', 'ΰ', 'ي', 'ယ', 'ｙ'],
            'z'    => ['ź', 'ž', 'ż', 'з', 'ζ', 'ز', 'ဇ', 'ზ', 'ｚ', 'ז'],
            'aa'   => ['ع', 'आ', 'آ'],
            'ae'   => ['æ', 'ǽ'],
            'ai'   => ['ऐ'],
            'ch'   => ['ч', 'ჩ', 'ჭ', 'چ'],
            'dj'   => ['ђ', 'đ'],
            'dz'   => ['џ', 'ძ', 'דז'],
            'ei'   => ['ऍ'],
            'gh'   => ['غ', 'ღ'],
            'ii'   => ['ई'],
            'ij'   => ['ĳ'],
            'kh'   => ['х', 'خ', 'ხ'],
            'lj'   => ['љ'],
            'nj'   => ['њ'],
            'oe'   => ['ö', 'œ', 'ؤ'],
            'oi'   => ['ऑ'],
            'oii'  => ['ऒ'],
            'ps'   => ['ψ'],
            'sh'   => ['ш', 'შ', 'ش', 'ש'],
            'shch' => ['щ'],
            'ss'   => ['ß'],
            'sx'   => ['ŝ'],
            'th'   => ['þ', 'ϑ', 'θ', 'ث', 'ذ', 'ظ'],
            'ts'   => ['ц', 'ც', 'წ'],
            'ue'   => ['ü'],
            'uu'   => ['ऊ'],
            'ya'   => ['я'],
            'yu'   => ['ю'],
            'zh'   => ['ж', 'ჟ', 'ژ'],
            '(c)'  => ['©'],
            'A'    => ['Á', 'À', 'Ả', 'Ã', 'Ạ', 'Ă', 'Ắ', 'Ằ', 'Ẳ', 'Ẵ', 'Ặ', 'Â', 'Ấ', 'Ầ', 'Ẩ', 'Ẫ', 'Ậ', 'Å', 'Ā', 'Ą', 'Α', 'Ά', 'Ἀ', 'Ἁ', 'Ἂ', 'Ἃ', 'Ἄ', 'Ἅ', 'Ἆ', 'Ἇ', 'ᾈ', 'ᾉ', 'ᾊ', 'ᾋ', 'ᾌ', 'ᾍ', 'ᾎ', 'ᾏ', 'Ᾰ', 'Ᾱ', 'Ὰ', 'Ά', 'ᾼ', 'А', 'Ǻ', 'Ǎ', 'Ａ', 'Ä'],
            'B'    => ['Б', 'Β', 'ब', 'Ｂ'],
            'C'    => ['Ç', 'Ć', 'Č', 'Ĉ', 'Ċ', 'Ｃ'],
            'D'    => ['Ď', 'Ð', 'Đ', 'Ɖ', 'Ɗ', 'Ƌ', 'ᴅ', 'ᴆ', 'Д', 'Δ', 'Ｄ'],
            'E'    => ['É', 'È', 'Ẻ', 'Ẽ', 'Ẹ', 'Ê', 'Ế', 'Ề', 'Ể', 'Ễ', 'Ệ', 'Ë', 'Ē', 'Ę', 'Ě', 'Ĕ', 'Ė', 'Ε', 'Έ', 'Ἐ', 'Ἑ', 'Ἒ', 'Ἓ', 'Ἔ', 'Ἕ', 'Έ', 'Ὲ', 'Е', 'Ё', 'Э', 'Є', 'Ə', 'Ｅ'],
            'F'    => ['Ф', 'Φ', 'Ｆ'],
            'G'    => ['Ğ', 'Ġ', 'Ģ', 'Г', 'Ґ', 'Γ', 'Ｇ'],
            'H'    => ['Η', 'Ή', 'Ħ', 'Ｈ'],
            'I'    => ['Í', 'Ì', 'Ỉ', 'Ĩ', 'Ị', 'Î', 'Ï', 'Ī', 'Ĭ', 'Į', 'İ', 'Ι', 'Ί', 'Ϊ', 'Ἰ', 'Ἱ', 'Ἳ', 'Ἴ', 'Ἵ', 'Ἶ', 'Ἷ', 'Ῐ', 'Ῑ', 'Ὶ', 'Ί', 'И', 'І', 'Ї', 'Ǐ', 'ϒ', 'Ｉ'],
            'J'    => ['Ｊ'],
            'K'    => ['К', 'Κ', 'Ｋ'],
            'L'    => ['Ĺ', 'Ł', 'Л', 'Λ', 'Ļ', 'Ľ', 'Ŀ', 'ल', 'Ｌ'],
            'M'    => ['М', 'Μ', 'Ｍ'],
            'N'    => ['Ń', 'Ñ', 'Ň', 'Ņ', 'Ŋ', 'Н', 'Ν', 'Ｎ'],
            'O'    => ['Ó', 'Ò', 'Ỏ', 'Õ', 'Ọ', 'Ô', 'Ố', 'Ồ', 'Ổ', 'Ỗ', 'Ộ', 'Ơ', 'Ớ', 'Ờ', 'Ở', 'Ỡ', 'Ợ', 'Ø', 'Ō', 'Ő', 'Ŏ', 'Ο', 'Ό', 'Ὀ', 'Ὁ', 'Ὂ', 'Ὃ', 'Ὄ', 'Ὅ', 'Ὸ', 'Ό', 'О', 'Ө', 'Ǒ', 'Ǿ', 'Ｏ', 'Ö'],
            'P'    => ['П', 'Π', 'Ｐ'],
            'Q'    => ['Ｑ'],
            'R'    => ['Ř', 'Ŕ', 'Р', 'Ρ', 'Ŗ', 'Ｒ'],
            'S'    => ['Ş', 'Ŝ', 'Ș', 'Š', 'Ś', 'С', 'Σ', 'Ｓ'],
            'T'    => ['Ť', 'Ţ', 'Ŧ', 'Ț', 'Т', 'Τ', 'Ｔ'],
            'U'    => ['Ú', 'Ù', 'Ủ', 'Ũ', 'Ụ', 'Ư', 'Ứ', 'Ừ', 'Ử', 'Ữ', 'Ự', 'Û', 'Ū', 'Ů', 'Ű', 'Ŭ', 'Ų', 'У', 'Ǔ', 'Ǖ', 'Ǘ', 'Ǚ', 'Ǜ', 'Ｕ', 'Ў', 'Ü'],
            'V'    => ['В', 'Ｖ'],
            'W'    => ['Ω', 'Ώ', 'Ŵ', 'Ｗ'],
            'X'    => ['Χ', 'Ξ', 'Ｘ'],
            'Y'    => ['Ý', 'Ỳ', 'Ỷ', 'Ỹ', 'Ỵ', 'Ÿ', 'Ῠ', 'Ῡ', 'Ὺ', 'Ύ', 'Ы', 'Й', 'Υ', 'Ϋ', 'Ŷ', 'Ｙ'],
            'Z'    => ['Ź', 'Ž', 'Ż', 'З', 'Ζ', 'Ｚ'],
            'AE'   => ['Æ', 'Ǽ'],
            'Ch'   => ['Ч'],
            'Dj'   => ['Ђ'],
            'Dz'   => ['Џ'],
            'Gx'   => ['Ĝ'],
            'Hx'   => ['Ĥ'],
            'Ij'   => ['Ĳ'],
            'Jx'   => ['Ĵ'],
            'Kh'   => ['Х'],
            'Lj'   => ['Љ'],
            'Nj'   => ['Њ'],
            'Oe'   => ['Œ'],
            'Ps'   => ['Ψ'],
            'Sh'   => ['Ш', 'ש'],
            'Shch' => ['Щ'],
            'Ss'   => ['ẞ'],
            'Th'   => ['Þ', 'Θ', 'ת'],
            'Ts'   => ['Ц'],
            'Ya'   => ['Я', 'יא'],
            'Yu'   => ['Ю', 'יו'],
            'Zh'   => ['Ж'],
            ' '    => ["\xC2\xA0", "\xE2\x80\x80", "\xE2\x80\x81", "\xE2\x80\x82", "\xE2\x80\x83", "\xE2\x80\x84", "\xE2\x80\x85", "\xE2\x80\x86", "\xE2\x80\x87", "\xE2\x80\x88", "\xE2\x80\x89", "\xE2\x80\x8A", "\xE2\x80\xAF", "\xE2\x81\x9F", "\xE3\x80\x80", "\xEF\xBE\xA0"],
        ];
    }

    /**
     * Retourne les remplacements spécifiques à la langue pour la méthode ascii.
     *
     * Note: Adapté de Stringy\Stringy.
     *
     * @param string $language Langue pour les caractères spécifiques
     * @return array<int, array<int, string>>|null Tableau de correspondances spécifiques à la langue
     *
     * @see https://github.com/danielstjules/Stringy/blob/3.1.0/LICENSE.txt
     */
    protected static function languageSpecificCharsArray(string $language): ?array
    {
        static $languageSpecific = null;

        if ($languageSpecific === null) {
            $languageSpecific = [
                'bg' => [
                    ['х', 'Х', 'щ', 'Щ', 'ъ', 'Ъ', 'ь', 'Ь'],
                    ['h', 'H', 'sht', 'SHT', 'a', 'А', 'y', 'Y'],
                ],
                'da' => [
                    ['æ', 'ø', 'å', 'Æ', 'Ø', 'Å'],
                    ['ae', 'oe', 'aa', 'Ae', 'Oe', 'Aa'],
                ],
                'de' => [
                    ['ä', 'ö', 'ü', 'Ä', 'Ö', 'Ü'],
                    ['ae', 'oe', 'ue', 'AE', 'OE', 'UE'],
                ],
                'he' => [
                    ['א', 'ב', 'ג', 'ד', 'ה', 'ו'],
                    ['ז', 'ח', 'ט', 'י', 'כ', 'ל'],
                    ['מ', 'נ', 'ס', 'ע', 'פ', 'צ'],
                    ['ק', 'ר', 'ש', 'ת', 'ן', 'ץ', 'ך', 'ם', 'ף'],
                ],
                'ro' => [
                    ['ă', 'â', 'î', 'ș', 'ț', 'Ă', 'Â', 'Î', 'Ș', 'Ț'],
                    ['a', 'a', 'i', 's', 't', 'A', 'A', 'I', 'S', 'T'],
                ],
            ];
        }

        return $languageSpecific[$language] ?? null;
    }
}
