<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\DateTime;

use BadMethodCallException;
use BlitzPHP\Utilities\Exceptions\DateException;
use DateInterval;
use DateTime;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Generator;
use IntlCalendar;
use IntlDateFormatter;
use InvalidArgumentException;
use ResourceBundle;
use Stringable;

/**
 * Classe DateTimeDate : gestion avancée des dates et heures.
 *
 * Fournit des méthodes utilitaires pour la création, le parsing et la manipulation de dates.
 *
 * @method Date   subOneYear()                                                            Soustrait une année à la date (alias de subYear).
 * @method bool   equals(DateTimeInterface|string $date, string|null $timezone = null)    Vérifie si la date est égale à une autre date.
 * @method int    getDaysInMonth()                                                        Retourne le nombre de jours dans le mois.
 * @method int    getGmtDifference()                                                      Retourne la différence avec GMT.
 * @method int    getSecondsSinceEpoch()                                                  Retourne le nombre de secondes depuis l'epoch UNIX.
 * @method bool   gte(DateTimeInterface|string $date, string|null $timezone = null)       Vérifie si la date est postérieure ou égale à une autre date.
 * @method bool   isAfter(DateTimeInterface|string $date, string|null $timezone = null)   Vérifie si la date est postérieure à une autre date.
 * @method bool   isBefore(DateTimeInterface|string $date, string|null $timezone = null)  Vérifie si la date est antérieure à une autre date.
 * @method string getDaySuffix()                                                          Retourne le suffixe du jour (ex: 'st', 'nd').
 * @method bool   lte(DateTimeInterface|string $date, string|null $timezone = null)       Vérifie si la date est antérieure ou égale à une autre date.
 * @method bool   notEquals(DateTimeInterface|string $date, string|null $timezone = null) Vérifie si la date est différente d'une autre date.
 *
 * @property-read int    $age         Âge en années par rapport à maintenant
 * @property-read string $day         Jour du mois (1-31)
 * @property-read string $dayOfWeek   Jour de la semaine (0-6)
 * @property-read string $dayOfYear   Jour de l'année (0-365)
 * @property-read bool   $dst         Vérifie si l'heure d'été est active
 * @property-read string $hour        Heure (0-23)
 * @property-read bool   $isAm        Vérifie si l'heure est AM (avant midi)
 * @property-read bool   $isFuture    Vérifie si la date est dans le futur
 * @property-read bool   $isLeapYear  Vérifie si l'année est bissextile
 * @property-read bool   $isPast      Vérifie si la date est dans le passé
 * @property-read bool   $isPm        Vérifie si l'heure est PM (midi ou après)
 * @property-read bool   $isToday     Vérifie si la date est aujourd'hui
 * @property-read bool   $isTomorrow  Vérifie si la date est demain
 * @property-read bool   $isWeekday   Vérifie si c'est un jour de semaine
 * @property-read bool   $isWeekend   Vérifie si c'est un week-end
 * @property-read bool   $isYesterday Vérifie si la date est hier
 * @property-read bool   $local       Vérifie si le fuseau horaire est local
 * @property-read string $minute      Minute (0-59)
 * @property-read string $month       Mois (1-12)
 * @property-read string $quarter     Trimestre (1-4)
 * @property-read string $second      Seconde (0-59)
 * @property-read int    $timestamp   Timestamp UNIX
 * @property-read bool   $utc         Vérifie si le fuseau horaire est UTC
 * @property-read string $weekOfMonth Semaine dans le mois (1-5)
 * @property-read string $weekOfYear  Semaine ISO dans l'année (1-53)
 * @property-read string $year        Année (ex: 2024)
 */
class Date extends DateTime implements Stringable
{
    /**
     * Fuseau horaire par défaut.
     */
    protected static string $DEFAULT_TIMEZONE = 'UTC'; // UTC&#65533;00:00 Coordinated Universal Time

    /**
     * Format par défaut lors de la conversion en chaîne.
     */
    protected static string $defaultDateFormat = 'Y-m-d H:i:s';

    /**
     * Jour de début de la semaine (0 = dimanche, 1 = lundi).
     */
    protected int $weekStartDay = 0;

    /**
     * Fuseau horaire utilisé par l'instance.
     */
    protected ?DateTimeZone $timezone = null;

    /**
     * Locale utilisée pour la date (optionnel).
     */
    protected ?string $locale = null;

    /**
     * Instance de test pour les tests unitaires (date figée).
     *
     * @var DateTimeInterface|static|null
     */
    protected static $testNow;

    public static $BUSINESS_DAYS_HYBRID_THRESHOLD = 30;

    /**
     * Mapping des méthodes magiques Carbon-like vers les vraies méthodes.
     * Permet d'utiliser ne(), lt(), equals(), etc. dynamiquement.
     */
    private array $facadesMethodsMapping = [
        'eq'        => 'equalTo',
        'equals'    => 'equalTo',
        'notEquals' => 'notEqualTo',
        'ne'        => 'notEqualTo',
        'isAfter'   => 'greaterThan',
        'gt'        => 'greaterThan',
        'isBefore'  => 'lessThan',
        'lt'        => 'lessThan',
        'lte'       => 'lessOrEqualTo',
        'gte'       => 'greaterOrEqualTo',
        // Méthodes one step
        'addOneDay'     => 'addDay',
        'addOneHour'    => 'addHour',
        'addOneMinute'  => 'addMinute',
        'addOneMonth'   => 'addMonth',
        'addOneQuarter' => 'addQuarter',
        'addOneSecond'  => 'addSecond',
        'addOneWeek'    => 'addWeek',
        'addOneYear'    => 'addYear',
        'subOneDay'     => 'subDay',
        'subOneHour'    => 'subHour',
        'subOneMinute'  => 'subMinute',
        'subOneMonth'   => 'subMonth',
        'subOneSecond'  => 'subSecond',
        'subOneQuarter' => 'subQuarter',
        'subOneWeek'    => 'subWeek',
        'subOneYear'    => 'subYear',
    ];

    /**
     * Traductions pour la méthode diffForHumans par locale.
     *
     * @var array<string, array<string, array<string, string>>>
     */
    protected static array $translations = [
        'fr' => [
            'year'          => ['an', 'ans'],
            'month'         => ['mois', 'mois'],
            'day'           => ['jour', 'jours'],
            'hour'          => ['heure', 'heures'],
            'minute'        => ['minute', 'minutes'],
            'second'        => ['seconde', 'secondes'],
            'prefix_future' => 'dans ',
            'prefix_past'   => 'il y a ',
        ],
        'en' => [
            'year'          => ['year', 'years'],
            'month'         => ['month', 'months'],
            'day'           => ['day', 'days'],
            'hour'          => ['hour', 'hours'],
            'minute'        => ['minute', 'minutes'],
            'second'        => ['second', 'seconds'],
            'prefix_future' => 'in ',
            'prefix_past'   => ' ago',
        ],
    ];

    /**
     * Constructeur principal.
     *
     * @param string|null              $time     Chaîne de date/heure ou null pour maintenant
     * @param DateTimeZone|string|null $timezone Fuseau horaire ou null pour défaut
     * @param string|null              $locale   Locale optionnelle
     */
    public function __construct(?string $time = '', DateTimeZone|string|null $timezone = null, ?string $locale = null)
    {
        $time ??= '';

        if ($locale !== null && ! static::isValidLocale($locale)) {
            throw new InvalidArgumentException("La locale '{$locale}' n'est pas valide.");
        }

        // Si une instance de test a été fournie, utilisez-la à la place.
        if ($time === '' && static::$testNow instanceof self) {
            $testNow = static::$testNow;
            if ($timezone !== null) {
                // On s'assure d'avoir une instance DateTime pour manipuler le fuseau horaire
                $dt = ($testNow instanceof DateTimeInterface) ? new DateTime($testNow->format('Y-m-d H:i:s'), $testNow->getTimezone()) : new DateTime('now');
                $dt->setTimezone(static::parseSuppliedTimezone($timezone));
                $time = $dt->format('Y-m-d H:i:s');
            } else {
                $timezone = ($testNow instanceof DateTimeInterface) ? $testNow->getTimezone() : static::$DEFAULT_TIMEZONE;
                $time     = ($testNow instanceof DateTimeInterface) ? $testNow->format('Y-m-d H:i:s') : date('Y-m-d H:i:s');
            }
        }

        $timezone       = $timezone ?: date_default_timezone_get();
        $this->timezone = static::parseSuppliedTimezone($timezone);
        $this->locale   = $locale;

        // Si la chaîne de temps était une chaîne relative (par exemple 'next Tuesday'),
        // nous devons alors ajuster l'heure afin d'obtenir le fuseau horaire actuel avec lequel travailler.
        if ($time !== '' && static::hasRelativeKeywords($time)) {
            $instance = new DateTime(static::$testNow?->format('Y-m-d H:i:s.u') ?? 'now', $this->timezone);
            $instance->modify($time);
            $time = $instance->format('Y-m-d H:i:s.u');
        }

        parent::__construct($time, $this->timezone);
    }

    /**
     * Crée une nouvelle instance de Date.
     *
     * @param string                   $time     Chaîne de date/heure ou 'now'
     * @param DateTimeZone|string|null $timezone Fuseau horaire
     * @param string|null              $locale   Locale optionnelle
     */
    public static function create(string $time = 'now', DateTimeZone|string|null $timezone = null, ?string $locale = null): static
    {
        return new static($time, $timezone, $locale);
    }

    /**
     * Crée une instance à partir d'une date (année, mois, jour).
     */
    public static function createFromDate(?int $year = null, ?int $month = null, ?int $day = null, DateTimeZone|string|null $timezone = null, ?string $locale = null): static
    {
        return static::createFromDateTime($year, $month, $day, null, null, null, $timezone, $locale);
    }

    /**
     * Crée une instance à partir d'une date et heure complète.
     */
    public static function createFromDateTime(?int $year = null, ?int $month = null, ?int $day = null, ?int $hour = null, ?int $minutes = null, ?int $seconds = null, DateTimeZone|string|null $timezone = null, ?string $locale = null): static
    {
        $year ??= (int) (static::$testNow?->year ?? date('Y'));
        $month ??= (int) (static::$testNow?->month ?? date('m'));
        $day ??= (int) (static::$testNow?->day ?? date('d'));
        $hour ??= 0;
        $minutes ??= 0;
        $seconds ??= 0;

        $dateStr = sprintf('%04d-%02d-%02d %02d:%02d:%02d', $year, $month, $day, $hour, $minutes, $seconds);

        return new static($dateStr, $timezone, $locale);
    }

    /**
     * Crée une instance à partir d'un format spécifique.
     *
     * @param string                   $format   Format de la date
     * @param string                   $datetime Chaîne de date
     * @param DateTimeZone|string|null $timezone Fuseau horaire
     */
    public static function createFromFormat(string $format, string $datetime, DateTimeZone|string|null $timezone = null): static
    {
        $timezone = static::parseSuppliedTimezone($timezone);
        $date     = parent::createFromFormat($format, $datetime, $timezone);

        return static::create($date->format('Y-m-d H:i:s.u'), $timezone);
    }

    /**
     * Crée une instance de Date en détectant automatiquement le format de la chaîne de date.
     *
     * @param string                   $datetime La chaîne de date à parser.
     * @param DateTimeZone|string|null $timezone Le fuseau horaire à utiliser.
     *
     * @throws InvalidArgumentException Si le format de la date ne peut pas être déterminé.
     */
    public static function createFromAnyFormat(string $datetime, DateTimeZone|string|null $timezone = null): static
    {
        $format = FormatDetector::detect($datetime);

        if ($format === null) {
            throw new InvalidArgumentException(sprintf(
                'Impossible de déterminer le format de la date: %s',
                $datetime
            ));
        }

        return $format === 'U' || $format === 'U.u'
            ? static::createFromTimestamp((int) $datetime, $timezone)
            : static::createFromFormat($format, $datetime, $timezone);
    }

    /**
     * Crée une instance à partir d'un objet DateTimeInterface.
     */
    public static function createFromInstance(DateTimeInterface $dateTime): static
    {
        $date     = $dateTime->format('Y-m-d H:i:s');
        $timezone = $dateTime->getTimezone();

        return new static($date, $timezone);
    }

    /**
     * Crée une instance à partir d'une heure, minute, seconde.
     */
    public static function createFromTime(?int $hour = null, ?int $minutes = null, ?int $seconds = null, DateTimeZone|string|null $timezone = null): static
    {
        return static::createFromDateTime(null, null, null, $hour, $minutes, $seconds, $timezone);
    }

    /**
     * Crée une instance à partir d'un timestamp UNIX.
     */
    public static function createFromTimestamp(int $timestamp, DateTimeZone|string|null $timezone = null): static
    {
        return static::create(date('Y-m-d H:i:s', $timestamp), $timezone);
    }

    /**
     * Retourne la date/heure actuelle.
     *
     * @return static|string L'objet Date ou une chaîne formatée
     */
    public static function now(DateTimeZone|string|null $timezone = null, ?string $format = null): static|string
    {
        $date = new static(null, $timezone);

        if (! static::hasTestNow()) {
            $date->setTimestamp(time());
        }

        if (null !== $format) {
            return $date->format($format);
        }

        return $date;
    }

    /**
     * Parse une chaîne en instance de Date.
     *
     * Example:
     *     $time = Date::parse('first day of April 2023');
     */
    public static function parse(string $datetime, DateTimeZone|string|null $timezone = null, ?string $locale = null): static
    {
        return new static($datetime, $timezone, $locale);
    }

    /**
     * Retourne la date du jour.
     *
     * @return static|string L'objet Date ou une chaîne formatée
     */
    public static function today(DateTimeZone|string|null $timezone = null, ?string $format = null): static|string
    {
        $date = static::now($timezone);

        if (null !== $format) {
            return $date->format($format);
        }

        return $date;
    }

    /**
     * Retourne la date de demain.
     *
     * @return static|string L'objet Date ou une chaîne formatée
     */
    public static function tomorrow(DateTimeZone|string|null $timezone = null, ?string $format = null): static|string
    {
        $date = static::now($timezone)->addDay();

        if (null !== $format) {
            return $date->format($format);
        }

        return $date;
    }

    /**
     * Retourne la date d'hier.
     *
     * @return static|string L'objet Date ou une chaîne formatée
     */
    public static function yesterday(DateTimeZone|string|null $timezone = null, ?string $format = null): static|string
    {
        $date = static::now($timezone)->subDay();

        if (null !== $format) {
            return $date->format($format);
        }

        return $date;
    }

    /**
     * Détecte si la chaîne contient des mots-clés relatifs (ex : 'demain', 'hier', etc.)
     */
    protected static function hasRelativeKeywords(string $time): bool
    {
        // ignore les formats courant avec un '-' dedans
        if (preg_match('/\d{4}-\d{1,2}-\d{1,2}/', $time) !== 1) {
            return preg_match('/this|next|last|tomorrow|yesterday|midnight|today|[+-]|first|last|ago/i', $time) > 0;
        }

        return false;
    }

    /**
     * Parse et retourne un objet DateTimeZone à partir d'une chaîne ou d'un objet.
     */
    protected static function parseSuppliedTimezone(DateTimeZone|string|null $timezone): ?DateTimeZone
    {
        if ($timezone instanceof DateTimeZone || null === $timezone) {
            return $timezone;
        }

        try {
            $timezone = new DateTimeZone($timezone);
        } catch (Exception) {
            throw new InvalidArgumentException('Le fuseau horaire fourni [' . $timezone . "] n'est pas supporté.");
        }

        return $timezone;
    }

    /**
     * Retourne la date au format AAAA-MM-JJ.
     */
    public function getDate(): string
    {
        return $this->format('Y-m-d');
    }

    /**
     * Retourne la date et l'heure au format AAAA-MM-JJ HH:MM:SS.
     */
    public function getDateTime(): string
    {
        return $this->format('Y-m-d H:i:s');
    }

    /**
     * Retourne l'heure au format HH:MM:SS.
     */
    public function getTime(): string
    {
        return $this->format('H:i:s');
    }

    /**
     * Retourne la date au format par défaut.
     */
    public function getDefaultDate(): string
    {
        return $this->format(static::$defaultDateFormat);
    }

    /**
     * Retourne la date longue (ex : 31 janvier 2024 à 7:45).
     */
    public function getLongDate(): string
    {
        $format = match ($this->locale) {
            'fr'    => 'd F Y \à H:i',
            default => 'F jS, Y \a\\t g:ia',
        };

        return $this->format($format);
    }

    /**
     * Retourne la date courte (ex : 31 janv. 2024).
     */
    public function getShortDate(): string
    {
        $format = match ($this->locale) {
            'fr'    => 'd M Y',
            default => 'M j, Y',
        };

        return $this->format($format);
    }

    /**
     * Retourne le jour du mois (01 à 31).
     *
     * @return numeric-string
     */
    public function getDay(): string
    {
        return $this->format('d');
    }

    /**
     * Retourne le numéro du jour de la semaine (0 = dimanche, 6 = samedi).
     */
    public function getDayOfWeek(): int
    {
        return (7 + $this->format('w') - $this->getWeekStartDay()) % 7;
    }

    /**
     * Retourne le jour de la semaine (de dimanche à samedi).
     */
    public function getDayOfWeekAsString(): string
    {
        return $this->format('l');
    }

    /**
     * Retourne le numéro du jour dans l'année (0 à 365).
     */
    public function getDayOfYear(): int
    {
        return (int) $this->format('z');
    }

    /**
     * Retourne l'heure (0 à 23).
     */
    public function getHour(): int
    {
        return (int) $this->format('G');
    }

    /**
     * Retourne les minutes (0 à 59).
     */
    public function getMinute(): int
    {
        return (int) $this->format('i');
    }

    /**
     * Retourne le mois (1 à 12).
     */
    public function getMonth(): int
    {
        return (int) $this->format('n');
    }

    /**
     * Retourne le trimestre (1 à 4).
     */
    public function getQuarter(): int
    {
        return (int) ceil($this->getMonth() / 3);
    }

    /**
     * Retourne les secondes (0 à 59).
     */
    public function getSecond(): int
    {
        return (int) $this->format('s');
    }

    /**
     * Retourne le numéro de la semaine dans le mois (1 à 5).
     */
    public function getWeekOfMonth(): int
    {
        return (int) ceil(($this->getDay() + $this->copy()->startOfMonth()->getDayOfWeek() - 1) / 7);
    }

    /**
     * Retourne le numéro de la semaine ISO-8601 dans l'année (1 à 53).
     */
    public function getWeekOfYear(): int
    {
        return (int) $this->format('W');
    }

    /**
     * Retourne l'année (ex : 2024).
     */
    public function getYear(): int
    {
        return (int) $this->format('Y');
    }

    /**
     * Retourne la locale courante.
     */
    public function getLocale(): ?string
    {
        return $this->locale;
    }

    /**
     * Retourne le jour de début de la semaine (0 = dimanche, 1 = lundi).
     */
    public function getWeekStartDay(): int
    {
        return $this->weekStartDay;
    }

    /**
     * Définit l'année.
     */
    public function setYear(int|string $value): static
    {
        return $this->setValue('year', $value);
    }

    /**
     * Définit le mois.
     */
    public function setMonth(int|string $value): static
    {
        if (is_numeric($value) && ($value < 1 || $value > 12)) {
            throw DateException::invalidMonth((string) $value);
        }

        if (is_string($value) && ! is_numeric($value)) {
            $value = date('m', strtotime("{$value} 1 2017"));
        }

        return $this->setValue('month', $value);
    }

    /**
     * Définit le jour du mois.
     */
    public function setDay(int|string $value): static
    {
        if ($value < 1 || $value > 31) {
            throw DateException::invalidDay((string) $value);
        }

        $date    = $this->getYear() . '-' . $this->getMonth();
        $lastDay = date('t', strtotime($date));
        if ($value > $lastDay) {
            throw DateException::invalidOverDay($lastDay, (string) $value);
        }

        return $this->setValue('day', $value);
    }

    /**
     * Définit l'heure .
     */
    public function setHour(int|string $value): static
    {
        if ($value < 0 || $value > 23) {
            throw DateException::invalidHour($value);
        }

        return $this->setValue('hour', $value);
    }

    /**
     * Définit les minutes.
     */
    public function setMinute(int|string $value): static
    {
        if ($value < 0 || $value > 59) {
            throw DateException::invalidMinutes($value);
        }

        return $this->setValue('minute', $value);
    }

    /**
     * Définit les secondes.
     */
    public function setSecond(int|string $value): static
    {
        if ($value < 0 || $value > 59) {
            throw DateException::invalidSeconds($value);
        }

        return $this->setValue('second', $value);
    }

    /**
     * Définit le format par défaut pour l'affichage.
     */
    public static function setDefaultDateFormat(string $format): void
    {
        static::$defaultDateFormat = $format;
    }

    /**
     * Définit les traductions pour une locale donnée pour la méthode diffForHumans.
     *
     * @param string                                      $locale       La locale pour laquelle définir les traductions (ex: 'es_ES', 'de_DE').
     * @param array<string, array<string, string>|string> $translations Tableau contenant les traductions pour chaque unité (year, month, etc.) et les préfixes.
     *                                                                  Format attendu :
     *                                                                  [
     *                                                                  'year' => ['singular', 'plural'],
     *                                                                  'month' => ['singular', 'plural'],
     *                                                                  'day' => ['singular', 'plural'],
     *                                                                  'hour' => ['singular', 'plural'],
     *                                                                  'minute' => ['singular', 'plural'],
     *                                                                  'second' => ['singular', 'plural'],
     *                                                                  'prefix_future' => 'string', // Préfixe pour le futur (ex: 'dans ')
     *                                                                  'prefix_past' => 'string',   // Préfixe pour le passé (ex: 'il y a ')
     *                                                                  ]
     *
     * @throws InvalidArgumentException Si la structure des traductions est invalide.
     */
    public static function setTranslations(string $locale, array $translations): void
    {
        $requiredKeys = ['year', 'month', 'day', 'hour', 'minute', 'second', 'prefix_future', 'prefix_past'];

        foreach ($requiredKeys as $key) {
            if (! isset($translations[$key])) {
                throw new InvalidArgumentException("La clé '{$key}' est manquante dans les traductions pour la locale '{$locale}'.");
            }
            if ($key !== 'prefix_future' && $key !== 'prefix_past' && (! is_array($translations[$key]) || count($translations[$key]) !== 2)) {
                throw new InvalidArgumentException("La clé '{$key}' doit être un tableau avec deux éléments (singulier, pluriel) pour la locale '{$locale}'.");
            }
        }

        static::$translations[$locale] = $translations;
    }

    /**
     * Définit la locale courante.
     *
     * @param string $locale La locale à définir (ex: 'fr_FR', 'en_US')
     *
     * @throws InvalidArgumentException Si la locale est invalide
     */
    public function setLocale(string $locale): static
    {
        if (! static::isValidLocale($locale)) {
            throw new InvalidArgumentException("La locale '{$locale}' n'est pas valide.");
        }

        $this->locale = $locale;

        return $this;
    }

    /**
     * Vérifie si une locale est valide.
     */
    protected static function isValidLocale(string $locale): bool
    {
        $availableLocales = ResourceBundle::getLocales('');

        return in_array($locale, $availableLocales, true) || in_array(str_replace('_', '-', $locale), $availableLocales, true);
    }

    /**
     * Définit le jour de début de la semaine (0 = dimanche, 1 = lundi).
     */
    public function setWeekStartDay(int|string $weekStartDay): static
    {
        if (is_numeric($weekStartDay)) {
            $this->weekStartDay = $weekStartDay;
        } else {
            $this->weekStartDay = array_search(strtolower($weekStartDay), ['sunday', 'monday'], true);
        }

        return $this;
    }

    /**
     * Change le fuseau horaire de l'instance.
     */
    public function setTimezone(DateTimeZone|string $timezone): static
    {
        $tz = static::parseSuppliedTimezone($timezone);
        parent::setTimezone($tz);
        $this->timezone = $tz;

        return $this;
    }

    /**
     * Change le timestamp de l'instance.
     */
    public function setTimestamp(int $timestamp): static
    {
        parent::setTimestamp($timestamp);

        return $this;
    }

    /**
     * Définit l'horodatage à partir d'une chaîne lisible par l'utilisateur.
     */
    public function setTimestampFromString(string $string): static
    {
        $this->setTimestamp(strtotime($string));

        return $this;
    }

    /**
     * Méthode d'aide pour effectuer les tâches lourdes des méthodes "setX".
     */
    protected function setValue(string $name, int|string $value): static
    {
        [$year, $month, $day, $hour, $minute, $second] = explode('-', $this->format('Y-n-j-G-i-s'));

        ${$name} = $value;

        return static::createFromDateTime(
            (int) $year,
            (int) $month,
            (int) $day,
            (int) $hour,
            (int) $minute,
            (int) $second,
            $this->timezoneName(),
            $this->locale
        );
    }

    // --------------------------------------------------------------------
    // Formatters
    // --------------------------------------------------------------------

    /**
     * Convertit toute date-heure textuelle en anglais en un objet date.
     *
     * @param DateTimeInterface|string $date     La date à convertir (chaîne ou objet).
     * @param DateTimeZone|string|null $timezone Fuseau horaire optionnel (défaut : UTC).
     *
     * @return static L'instance Date convertie.
     *
     * @throws InvalidArgumentException Si la date est invalide et ne peut être parsée.
     */
    public static function convertToDate(DateTimeInterface|string $date, DateTimeZone|string|null $timezone = null): static
    {
        $tz = static::parseSuppliedTimezone($timezone ?? static::getDefaultTimezone());

        if ($date instanceof DateTimeInterface) {
            $date = $date->format('Y-m-d H:i:s.U');
        }

        if (static::$testNow && static::hasRelativeKeywords($date)) {
            $now = static::now($tz);
            $now->modify($date);

            return $now;
        }

        try {
            $dt = new DateTime($date, $tz);

            return static::createFromInstance($dt);
        } catch (Exception) {
            return static::createFromAnyFormat($date, $tz);
        }
    }

    /**
     * Retourne la date et l'heure au format localisé (ex : 2024-01-31 13:45:00).
     */
    public function toDateTimeString(): string
    {
        return $this->toLocalizedString('yyyy-MM-dd HH:mm:ss');
    }

    /**
     * Retourne la date au format localisé (ex : 2024-01-31).
     */
    public function toDateString(): string
    {
        return $this->toLocalizedString('yyyy-MM-dd');
    }

    /**
     * Retourne la date au format lisible (ex : 31 janv. 2024).
     */
    public function toFormattedDateString(): string
    {
        return $this->toLocalizedString('MMM d, yyyy');
    }

    /**
     * Retourne l'heure au format localisé (ex : 13:45:00).
     */
    public function toTimeString(): string
    {
        return $this->toLocalizedString('HH:mm:ss');
    }

    /**
     * Retourne la date/heure au format localisé selon le format fourni.
     *
     * @param string|null $format Format ICU (ex : yyyy-MM-dd HH:mm:ss)
     */
    public function toLocalizedString(?string $format = null): string
    {
        $format ??= static::$defaultDateFormat;

        // Utilise IntlDateFormatter pour la localisation
        return IntlDateFormatter::formatObject($this->toDateTime(), $format, $this->locale);
    }

    /**
     * Retourne une copie mutable de l'instance sous forme de DateTime.
     */
    public function toDateTime(): DateTime
    {
        $dt = new DateTime('', $this->getTimezone());
        $dt->setTimestamp(parent::getTimestamp());

        return $dt;
    }

    /**
     * Retourne une copie de l'instance.
     */
    public function copy(): static
    {
        return clone $this;
    }

    /**
     * Retourne une copie de l'instance avec des composantes modifiées (année, mois, jour, heure, minute, seconde).
     *
     * @param array $changes [ 'year' => 2024, 'month' => 5, ... ]
     */
    public function copyWith(array $changes): static
    {
        $date   = clone $this;
        $year   = $changes['year'] ?? $date->getYear();
        $month  = $changes['month'] ?? $date->getMonth();
        $day    = $changes['day'] ?? $date->getDay();
        $hour   = $changes['hour'] ?? $date->getHour();
        $minute = $changes['minute'] ?? $date->getMinute();
        $second = $changes['second'] ?? $date->getSecond();

        $date->setDate($year, $month, $day);
        $date->setTime($hour, $minute, $second);

        return $date;
    }

    /**
     * Définit l'heure à partir d'une chaîne (ex : '14:30:00').
     */
    public function setTimeFromTimeString(string $time): static
    {
        [$hour, $minute, $second] = array_pad(explode(':', $time), 3, 0);

        return $this->setTime((int) $hour, (int) $minute, (int) $second);
    }

    /**
     * Définit la date à partir d'une chaîne (ex : '2024-05-01').
     */
    public function setDateFromDateString(string $date): static
    {
        [$year, $month, $day] = array_pad(explode('-', $date), 3, 1);

        return $this->setDate((int) $year, (int) $month, (int) $day);
    }

    /**
     * Retourne la prochaine occurrence d'un jour de la semaine (0=dimanche, 1=lundi, ...).
     */
    public function next(int $dayOfWeek): static
    {
        $date       = clone $this;
        $currentDow = (int) $date->format('w');
        $daysToAdd  = ($dayOfWeek - $currentDow + 7) % 7;
        $daysToAdd  = $daysToAdd === 0 ? 7 : $daysToAdd;
        $date->modify("+{$daysToAdd} days");

        return $date;
    }

    /**
     * Retourne la précédente occurrence d'un jour de la semaine (0=dimanche, 1=lundi, ...).
     */
    public function previous(int $dayOfWeek): static
    {
        $date       = clone $this;
        $currentDow = (int) $date->format('w');
        $daysToSub  = ($currentDow - $dayOfWeek + 7) % 7;
        $daysToSub  = $daysToSub === 0 ? 7 : $daysToSub;
        $date->modify("-{$daysToSub} days");

        return $date;
    }

    /**
     * Formatage avancé façon Carbon (support partiel des patterns ISO/Carbon).
     * Exemples : 'dddd D MMMM YYYY', 'YYYY-MM-DD HH:mm:ss'
     */
    public function isoFormat(string $pattern): string
    {
        // Remplacement basique des patterns Carbon/ISO par les patterns PHP
        $replacements = [
            'YYYY' => 'Y', 'YY' => 'y',
            'MMMM' => 'F', 'MMM' => 'M',
            'MM'   => 'm', 'M' => 'n',
            'DD'   => 'd', 'D' => 'j',
            'dddd' => 'l', 'ddd' => 'D',
            'HH'   => 'H', 'H' => 'G',
            'mm'   => 'i', 'ss' => 's',
        ];
        $phpPattern = strtr($pattern, $replacements);

        return $this->format($phpPattern);
    }

    // --------------------------------------------------------------------
    // Comparaison
    // --------------------------------------------------------------------

    /**
     * Vérifie si la date courante est égale à une autre date.
     */
    public function equalTo(DateTimeInterface|string $date, ?string $timezone = null): bool
    {
        if (is_string($date)) {
            $date = static::create($date, $this->getTimezone());
        }

        $test = clone $this;

        if (null !== $timezone) {
            if (! ($date instanceof DateTime)) {
                if ($date instanceof DateTimeInterface) {
                    $date = new DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
                } else {
                    $date = new DateTime(is_string($date) ? $date : 'now');
                }
            }

            if (! ($test instanceof DateTime)) {
                if ($test instanceof DateTimeInterface) {
                    $test = new DateTime($test->format('Y-m-d H:i:s'), $test->getTimezone());
                } else {
                    $test = new DateTime('now');
                }
            }

            $date = $date->setTimezone(new DateTimeZone($timezone));
            $test = $test->setTimezone(new DateTimeZone($timezone));
        }

        return $test->format('Y-m-d H:i:s') === $date->format('Y-m-d H:i:s');
    }

    /**
     * Vérifie si la date courante est différente d'une autre date.
     */
    public function notEqualTo(DateTimeInterface|string $date, ?string $timezone = null): bool
    {
        return ! $this->equalTo($date, $timezone);
    }

    /**
     * Vérifie si la date courante est postérieure à une autre date.
     */
    public function greaterThan(DateTimeInterface|string $date, ?string $timezone = null): bool
    {
        if (is_string($date)) {
            $date = static::create($date, $this->getTimezone());
        }

        $test = clone $this;

        if (null !== $timezone) {
            if (! ($date instanceof DateTime)) {
                if ($date instanceof DateTimeInterface) {
                    $date = new DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
                } else {
                    $date = new DateTime(is_string($date) ? $date : 'now');
                }
            }

            if (! ($test instanceof DateTime)) {
                if ($test instanceof DateTimeInterface) {
                    $test = new DateTime($test->format('Y-m-d H:i:s'), $test->getTimezone());
                } else {
                    $test = new DateTime('now');
                }
            }

            $date = $date->setTimezone(new DateTimeZone($timezone));
            $test = $test->setTimezone(new DateTimeZone($timezone));
        }

        return $test > $date;
    }

    /**
     * Vérifie si la date courante est postérieure ou égale à une autre date.
     */
    public function greaterOrEqualTo(DateTimeInterface|string $date, ?string $timezone = null): bool
    {
        if (is_string($date)) {
            $date = static::create($date, $this->getTimezone());
        }

        $test = clone $this;

        if (null !== $timezone) {
            if (! ($date instanceof DateTime)) {
                if ($date instanceof DateTimeInterface) {
                    $date = new DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
                } else {
                    $date = new DateTime(is_string($date) ? $date : 'now');
                }
            }

            if (! ($test instanceof DateTime)) {
                if ($test instanceof DateTimeInterface) {
                    $test = new DateTime($test->format('Y-m-d H:i:s'), $test->getTimezone());
                } else {
                    $test = new DateTime('now');
                }
            }

            $date = $date->setTimezone(new DateTimeZone($timezone));
            $test = $test->setTimezone(new DateTimeZone($timezone));
        }

        return $test >= $date;
    }

    /**
     * Vérifie si la date courante est antérieure à une autre date.
     */
    public function lessThan(DateTimeInterface|string $date, ?string $timezone = null): bool
    {
        if (is_string($date)) {
            $date = static::create($date, $this->getTimezone());
        }

        $test = clone $this;

        if (null !== $timezone) {
            if (! ($date instanceof DateTime)) {
                if ($date instanceof DateTimeInterface) {
                    $date = new DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
                } else {
                    $date = new DateTime(is_string($date) ? $date : 'now');
                }
            }

            if (! ($test instanceof DateTime)) {
                if ($test instanceof DateTimeInterface) {
                    $test = new DateTime($test->format('Y-m-d H:i:s'), $test->getTimezone());
                } else {
                    $test = new DateTime('now');
                }
            }

            $date = $date->setTimezone(new DateTimeZone($timezone));
            $test = $test->setTimezone(new DateTimeZone($timezone));
        }

        return $test < $date;
    }

    /**
     * Vérifie si la date courante est antérieure ou égale à une autre date.
     */
    public function lessOrEqualTo(DateTimeInterface|string $date, ?string $timezone = null): bool
    {
        if (is_string($date)) {
            $date = static::create($date, $this->getTimezone());
        }

        $test = clone $this;

        if (null !== $timezone) {
            if (! ($date instanceof DateTime)) {
                if ($date instanceof DateTimeInterface) {
                    $date = new DateTime($date->format('Y-m-d H:i:s'), $date->getTimezone());
                } else {
                    $date = new DateTime(is_string($date) ? $date : 'now');
                }
            }

            if (! ($test instanceof DateTime)) {
                if ($test instanceof DateTimeInterface) {
                    $test = new DateTime($test->format('Y-m-d H:i:s'), $test->getTimezone());
                } else {
                    $test = new DateTime('now');
                }
            }

            $date = $date->setTimezone(new DateTimeZone($timezone));
            $test = $test->setTimezone(new DateTimeZone($timezone));
        }

        return $test <= $date;
    }

    /**
     * Vérifie si la date courante est strictement identique à une autre (avec fuseau).
     */
    public function sameAs(DateTimeInterface|string $testTime, ?string $timezone = null): bool
    {
        if ($testTime instanceof DateTimeInterface) {
            $testTimestamp = $testTime->getTimestamp();
        } else {
            $timezone      = $timezone ?: $this->timezone;
            $timezone      = $timezone instanceof DateTimeZone ? $timezone : new DateTimeZone($timezone);
            $testTime      = new DateTime($testTime, $timezone);
            $testTimestamp = $testTime->getTimestamp();
        }

        $ourTimestamp = $this->timestamp;

        return $testTimestamp === $ourTimestamp;
    }

    /**
     * Vérifie si la date est aujourd'hui.
     */
    public function isToday(): bool
    {
        static $date = null;
        $date ??= static::today($this->getTimezone())->startOfDay();

        return $this->startOfDay()->equalTo($date);
    }

    /**
     * Vérifie si la date est hier.
     */
    public function isYesterday(): bool
    {
        static $date = null;
        $date ??= static::yesterday($this->getTimezone())->startOfDay();

        return $this->startOfDay()->equalTo($date);
    }

    /**
     * Vérifie si la date est demain.
     */
    public function isTomorrow(): bool
    {
        static $date = null;
        $date ??= static::tomorrow($this->getTimezone())->startOfDay();

        return $this->startOfDay()->equalTo($date);
    }

    /**
     * Vérifie si la date est dans X minutes par rapport à maintenant.
     *
     * @param int $value Nombre de minutes dans le futur à vérifier (1 par défaut)
     *
     * @return bool true si la date correspond à maintenant + X minutes (au début de la minute)
     */
    public function isNextMinute(int $value = 1): bool
    {
        static $date = null;
        $date ??= static::now($this->getTimezone())->addMinutes($value)->startOfMinute();

        return $this->startOfMinute()->equalTo($date);
    }

    /**
     * Vérifie si la date est dans X heures par rapport à maintenant.
     *
     * @param int $value Nombre d'heures dans le futur à vérifier (1 par défaut)
     *
     * @return bool true si la date correspond à maintenant + X heures (au début de l'heure)
     */
    public function isNextHour(int $value = 1): bool
    {
        static $date = null;
        $date ??= static::now($this->getTimezone())->addHours($value)->startOfHour();

        return $this->startOfHour()->equalTo($date);
    }

    /**
     * Vérifie si la date est dans X semaines par rapport à maintenant.
     *
     * @param int $value Nombre de semaines dans le futur à vérifier (1 par défaut)
     *
     * @return bool true si la date correspond à maintenant + X semaines (au début de la semaine)
     */
    public function isNextWeek(int $value = 1): bool
    {
        static $date = null;
        $date ??= static::now($this->getTimezone())->addWeeks($value)->startOfWeek();

        return $this->startOfWeek()->equalTo($date);
    }

    /**
     * Vérifie si la date est dans X mois par rapport à maintenant.
     *
     * @param int $value Nombre de mois dans le futur à vérifier (1 par défaut)
     *
     * @return bool true si la date correspond à maintenant + X mois (au début du mois)
     */
    public function isNextMonth(int $value = 1): bool
    {
        static $date = null;
        $date ??= static::now($this->getTimezone())->addMonths($value)->startOfMonth();

        return $this->startOfMonth()->equalTo($date);
    }

    /**
     * Vérifie si la date est dans X trimestres par rapport à maintenant.
     *
     * @param int $value Nombre de trimestres dans le futur à vérifier (1 par défaut)
     *
     * @return bool true si la date correspond à maintenant + X trimestres (au début du trimestre)
     */
    public function isNextQuarter(int $value = 1): bool
    {
        static $date = null;
        $date ??= static::now($this->getTimezone())->addQuarters($value)->startOfQuarter();

        return $this->startOfQuarter()->equalTo($date);
    }

    /**
     * Vérifie si la date est dans X années par rapport à maintenant.
     *
     * @param int $value Nombre d'années dans le futur à vérifier (1 par défaut)
     *
     * @return bool true si la date correspond à maintenant + X années (au début de l'année)
     */
    public function isNextYear(int $value = 1): bool
    {
        static $date = null;
        $date ??= static::now($this->getTimezone())->addYears($value)->startOfYear();

        return $this->startOfYear()->equalTo($date);
    }

    /**
     * Vérifie si la date est un week-end.
     */
    public function isWeekend(): bool
    {
        $dayOfWeek = (int) $this->format('w');

        return $dayOfWeek === 0 || $dayOfWeek === 6;
    }

    /**
     * Vérifie si la date est un jour de semaine.
     */
    public function isWeekday(): bool
    {
        $dayOfWeek = (int) $this->format('w');

        return $dayOfWeek >= 1 && $dayOfWeek <= 5;
    }

    /**
     * Vérifie si l'année est bissextile.
     */
    public function isLeapYear(): bool
    {
        return (bool) $this->format('L');
    }

    /**
     * Vérifie si la date est dans le passé.
     */
    public function isPast(): bool
    {
        return $this < static::now($this->getTimezone());
    }

    /**
     * Vérifie si la date est dans le futur.
     */
    public function isFuture(): bool
    {
        return $this > static::now($this->getTimezone());
    }

    /**
     * Vérifie si la date est le même jour qu'une autre date  (ignore l'heure).
     * Le timezone de l'objet courant est utilisé pour la comparaison.
     */
    public function isSameDay(DateTimeInterface|string $date): bool
    {
        if (is_string($date)) {
            // Optimisation : compare directement les chaînes au format 'Y-m-d'
            if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return $this->format('Y-m-d') === $date;
            }

            $date = new static($date, $this->getTimezone());
        } else {
            /** @var DateTime|DateTimeImmutable $date */
            // S'assure que les timezones sont cohérents
            $date = $date->setTimezone($this->getTimezone());
        }

        return $this->format('Y-m-d') === $date->format('Y-m-d');
    }

    /**
     * Vérifie si la date correspond à l'anniversaire d'une autre date (jour et mois).
     * Prend en compte le timezone de l'objet courant pour la comparaison.
     */
    public function isBirthday(DateTimeInterface|string $date): bool
    {
        if (is_string($date)) {
            $date = new static($date, $this->getTimezone());
        } else {
            /** @var DateTime|DateTimeImmutable $date */
            // S'assure que les timezones sont cohérents
            $date = $date->setTimezone($this->getTimezone());
        }

        return $this->format('m-d') === $date->format('m-d');
    }

    /**
     * Vérifie si la date est un jour férié pour un pays donné.
     *
     * @param string|null $countryCode Le code du pays (ex: 'FR'). Si null, utilise la locale de l'instance.
     */
    public function isHoliday(?string $countryCode = null): bool
    {
        return Holiday::isHoliday($this, $countryCode);
    }

    /**
     * Vérifie si l'heure est en AM (avant midi)
     */
    public function isAm(): bool
    {
        return $this->getHour() < 12;
    }

    /**
     * Vérifie si l'heure est en PM (midi ou après)
     */
    public function isPm(): bool
    {
        return $this->getHour() >= 12;
    }

    // --------------------------------------------------------------------
    // Differences
    // --------------------------------------------------------------------

    /**
     * Calcule la différence entre deux dates
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function difference(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): DateInterval
    {
        $date2 ??= static::now();
        $timezone ??= static::getDefaultTimezone();

        $dt1 = static::convertToDate($date1, $timezone);
        $dt2 = static::convertToDate($date2, $timezone);

        return $dt1->diff($dt2);
    }

    /**
     * Calcule la différence en secondes entre deux dates
     *
     * @return int Nombre de secondes (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffSeconds(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $diff = static::difference($date1, $date2, $timezone);

        $seconds = $diff->days * 86400 + $diff->h * 3600 + $diff->i * 60 + $diff->s;

        return $diff->invert ? -$seconds : $seconds;
    }

    /**
     * Calcule la différence en minutes entre deux dates
     *
     * @return int Nombre de minutes (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffMinutes(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $diff    = static::difference($date1, $date2, $timezone);
        $minutes = ($diff->days * 24 * 60) + ($diff->h * 60) + $diff->i;

        return $diff->invert ? -$minutes : $minutes;
    }

    /**
     * Calcule la différence en heures entre deux dates
     *
     * @return int Nombre d'heures (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffHours(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $diff  = static::difference($date1, $date2, $timezone);
        $hours = ($diff->days * 24) + $diff->h;

        return $diff->invert ? -$hours : $hours;
    }

    /**
     * Calcule la différence en jours entre deux dates
     *
     * @return int Nombre de jours (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffDays(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $diff = static::difference($date1, $date2, $timezone);

        return (int) $diff->format('%r%a'); // Utilise format() pour le signe et les jours complets
    }

    /**
     * Calcule la différence en semaines entre deux dates
     *
     * @return int Nombre de semaines (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffWeeks(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $days = static::diffDays($date1, $date2, $timezone);

        return (int) ($days / 7);
    }

    /**
     * Calcule la différence en mois entre deux dates
     *
     * @return int Nombre de mois (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffMonths(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $diff   = static::difference($date1, $date2, $timezone);
        $months = ($diff->y * 12) + $diff->m;

        return $diff->invert ? -$months : $months;
    }

    /**
     * Calcule la différence en trimestres entre deux dates
     *
     * @return int Nombre de trimestres (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffQuarters(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $months = static::diffMonths($date1, $date2, $timezone);

        return (int) ($months / 3);
    }

    /**
     * Calcule la différence en années entre deux dates
     *
     * @return int Nombre d'années (signé : négatif si $date1 > $date2)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public static function diffYears(DateTimeInterface|string $date1, DateTimeInterface|string|null $date2 = null, DateTimeZone|string|null $timezone = null): int
    {
        $diff = static::difference($date1, $date2, $timezone);

        return $diff->invert ? -$diff->y : $diff->y;
    }

    /**
     * Retourne la différence en secondes avec une autre date.
     *
     * @return int Nombre de secondes (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInSeconds(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffSeconds($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence en minutes avec une autre date.
     *
     * @return int Nombre de minutes (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInMinutes(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffMinutes($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence en heures avec une autre date.
     *
     * @return int Nombre d'heures (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInHours(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffHours($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence en jours avec une autre date.
     *
     * @return int Nombre de jours (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInDays(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffDays($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence en semaines avec une autre date.
     *
     * @return int Nombre de semaines (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInWeeks(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffWeeks($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence en mois avec une autre date.
     *
     * @return int Nombre de mois (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInMonths(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffMonths($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence en trimestres avec une autre date.
     *
     * @return int Nombre de trimestres (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInQuarters(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffQuarters($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence en années avec une autre date.
     *
     * @return int Nombre d'années (négatif si la date courante est postérieure à $date)
     *
     * @throws InvalidArgumentException Si un format de date est invalide
     */
    public function diffInYears(DateTimeInterface|string|null $date = null, DateTimeZone|string|null $timezone = null): int
    {
        return static::diffYears($this->copy(), $date, $timezone ?? $this->getTimezone());
    }

    /**
     * Retourne la différence humaine entre deux dates (ex : "il y a 2 jours", "dans 3 mois").
     *
     * Supporte plusieurs langues via les traductions définies par setTranslations.
     *
     * @param DateTimeInterface|string|null $other    La date à comparer (null pour maintenant).
     * @param bool                          $absolute Affiche sans préfixe de temps ("il y a" ou "dans").
     * @param string|null                   $locale   La locale à utiliser (ex: 'fr', 'en'). Si null, utilise la locale de l'instance.
     *
     * @return string La différence formatée.
     *
     * @throws InvalidArgumentException Si la locale n'a pas de traductions définies.
     */
    public function diffForHumans(DateTimeInterface|string|null $other = null, bool $absolute = false, ?string $locale = null): string
    {
        $locale ??= $this->locale ?? 'fr';
        if (! isset(static::$translations[$locale])) {
            throw new InvalidArgumentException("Aucune traduction définie pour la locale '{$locale}'. Utilisez setTranslations pour la définir.");
        }

        $translations = static::$translations[$locale];
        $now          = $other ? (is_string($other) ? new static($other, $this->getTimezone()) : $other) : new static('now', $this->getTimezone());
        $isFuture     = $this > $now;
        $diff         = $this->diff($now);
        $units        = [
            'year'   => $diff->y,
            'month'  => $diff->m,
            'day'    => $diff->d,
            'hour'   => $diff->h,
            'minute' => $diff->i,
            'second' => $diff->s,
        ];
        $unit  = 'second';
        $value = 0;

        foreach ($units as $k => $v) {
            if ($v > 0) {
                $unit  = $k;
                $value = $v;
                break;
            }
        }

        $str = $value . ' ' . $translations[$unit][$value > 1 && $unit !== 'month' ? 1 : 0];
        if ($absolute) {
            return $str;
        }

        $prefix = ! $isFuture ? $translations['prefix_future'] : $translations['prefix_past'];

        return $prefix . $str;
    }

    /**
     * Redéfinit diffForHumans pour supporter la locale de l'instance (français/anglais).
     *
     * @param bool $absolute Affiche sans "il y a" ou "dans"
     */
    public function diffForHumansLocale(DateTimeInterface|string|null $other = null, bool $absolute = false): string
    {
        return $this->diffForHumans($other, $absolute, $this->locale);
    }

    /**
     * Calcule le nombre de jours ouvrés (hors week-ends et jours fériés) entre cette date et une autre.
     *
     * @param self        $endDate     La date de fin (inclusive).
     * @param string|null $countryCode Le code du pays pour vérifier les jours fériés (ex: 'FR').
     * @param bool        $inclusive   Si true (défaut), inclut start et end ; si false, exclut end.
     */
    public function diffInBusinessDays(self $endDate, ?string $countryCode = null, bool $inclusive = true): int
    {
        if ($this->greaterThan($endDate)) {
            return 0;
        }

        $start = $this->startOfDay();
        $end   = $endDate->startOfDay();

        // Ajuster pour exclusif
        if (! $inclusive) {
            if ($start->equalTo($end)) {
                return 0;
            }
            $end = $end->subDay();
        }

        $totalDays = $start->diffInDays($end) + 1;
        if ($totalDays <= 0) {
            return 0;
        }

        // Seuil pour optimisation (ex. : > 365 jours, utiliser approximation ; sinon itération complète)
        $threshold = 365;
        if ($totalDays <= $threshold) {
            // Itération complète pour précision
            $businessDays = 0;
            $current      = $start->copy();
            $holidays     = Holiday::getHolidaysInPeriod($start, $end, $countryCode);

            while ($current->lessOrEqualTo($end)) {
                if ($current->isWeekday() && ! in_array($current->toDateString(), $holidays, true)) {
                    $businessDays++;
                }
                $current = $current->addDay();
            }

            return $businessDays;
        }

        // Optimisation pour grandes périodes : total weekdays - weekday holidays
        $completeWeeks = (int) ($totalDays / 7);
        $remainingDays = $totalDays % 7;
        $businessDays  = $completeWeeks * 5;

        // Ajouter weekdays des remaining days
        $current = $start->copy()->addDays($completeWeeks * 7);

        for ($i = 0; $i < $remainingDays; $i++) {
            if ($current->isWeekday()) {
                $businessDays++;
            }
            $current = $current->addDay();
        }

        // Soustraire tous les weekday holidays dans la période
        $holidays = Holiday::getHolidaysInPeriod($start, $end, $countryCode);

        foreach ($holidays as $holidayStr) {
            $holidayDate = static::parse($holidayStr);
            if ($holidayDate->isWeekday() && $holidayDate->greaterOrEqualTo($start) && $holidayDate->lessOrEqualTo($end)) {
                $businessDays--;
            }
        }

        return max(0, $businessDays);
    }

    /**
     * Ajoute ou soustrait un nombre de jours ouvrés à la date actuelle, en optimisant pour les grandes plages.
     * Les jours de week-end et les jours fériés sont ignorés. La méthode retourne une nouvelle instance.
     *
     * Cette méthode utilise une approche hybride pour optimiser les performances :
     * - Pour un petit nombre de jours (|days| <= BUSINESS_DAYS_HYBRID_THRESHOLD), elle itère jour par jour.
     * - Pour un grand nombre de jours, elle effectue un saut initial estimé, corrige les jours fériés, puis ajuste précisément.
     *
     * @param int         $days        Nombre de jours ouvrés à ajouter (positif) ou soustraire (négatif).
     * @param string|null $countryCode Le code du pays pour vérifier les jours fériés (ex: 'FR'). Si null, utilise la locale de l'instance.
     *
     * @return static Une nouvelle instance avec la date ajustée.
     *
     * @example
     * // Ajouter 5 jours ouvrés en France
     * $date = Date::create('2025-08-01', 'Europe/Paris', 'fr_FR');
     * $newDate = $date->addBusinessDays(5, 'FR');
     * echo $newDate->toDateString(); // Exemple : "2025-08-08" (si aucun jour férié)
     *
     * // Soustraire 3 jours ouvrés
     * $newDate = $date->addBusinessDays(-3, 'FR');
     * echo $newDate->toDateString(); // Exemple : "2025-07-29"
     *
     * // Ajouter 50 jours ouvrés (optimisation pour grande période)
     * $newDate = $date->addBusinessDays(50, 'FR');
     * echo $newDate->toDateString(); // Exemple : "2025-10-10" (selon jours fériés)
     */
    public function addBusinessDays(int $days, ?string $countryCode = null): static
    {
        if ($days === 0) {
            return $this->copy();
        }

        $newDate   = $this->copy();
        $absDays   = abs($days);
        $direction = $days > 0 ? 1 : -1;
        $modifier  = $days > 0 ? '+1 day' : '-1 day';

        if ($absDays <= static::$BUSINESS_DAYS_HYBRID_THRESHOLD) {
            // Approche itérative pour petites périodes
            $remainingDays = $absDays;

            while ($remainingDays > 0) {
                $newDate->modify($modifier);
                if ($newDate->isWeekday() && ! $newDate->isHoliday($countryCode)) {
                    $remainingDays--;
                }
            }

            return $newDate;
        }

        // Approche optimisée pour grandes périodes
        // Estimation initiale : compte 2 jours de week-end par semaine
        $estimatedDays = $absDays + (int) ($absDays / 5 * 2);
        $newDate->modify(sprintf('%s%d days', $direction > 0 ? '+' : '-', $estimatedDays));

        // Calculer les jours fériés dans la période estimée
        $start        = $days > 0 ? $this : $newDate;
        $end          = $days > 0 ? $newDate : $this;
        $holidays     = Holiday::getHolidaysInPeriod($start, $end, $countryCode);
        $holidayCount = count(array_filter($holidays, static fn ($holiday) => (new static($holiday))->isWeekday()));

        // Ajuster pour les jours fériés
        $newDate->modify(sprintf('%s%d days', $direction > 0 ? '+' : '-', $holidayCount));

        // Ajustement précis pour atteindre exactement le nombre de jours ouvrés
        $actualBusinessDays = $this->diffInBusinessDays($newDate, $countryCode);

        while ($actualBusinessDays !== $days) {
            $newDate->modify($actualBusinessDays < $days ? '+1 day' : '-1 day');
            $actualBusinessDays = $this->diffInBusinessDays($newDate, $countryCode);
        }

        return $newDate;
    }

    /**
     * Soustrait un nombre de jours ouvrés à la date actuelle.
     * Les jours de week-end et les jours fériés sont ignorés.
     *
     * @param int         $days        Nombre de jours ouvrés à soustraire.
     * @param string|null $countryCode Le code du pays pour vérifier les jours fériés (ex: 'FR').
     */
    public function subBusinessDays(int $days, ?string $countryCode = null): static
    {
        return $this->addBusinessDays(-$days, $countryCode);
    }

    // --------------------------------------------------------------------
    // Utilitaires
    // --------------------------------------------------------------------

    /**
     * Retourne le nombre de jours dans le mois courant.
     */
    public function daysInMonth(): int
    {
        return (int) $this->format('t');
    }

    /**
     * Retourne le numéro de la semaine dans l'année (1 à 53).
     */
    public function weekOfYear(): int
    {
        return (int) $this->format('W');
    }

    /**
     * Retourne le trimestre courant (1 à 4).
     */
    public function quarter(): int
    {
        return (int) ceil($this->getMonth() / 3);
    }

    /**
     * Calcule l'âge en années à partir de la date actuelle.
     *
     * @return int L'âge en années.
     */
    public function age(): int
    {
        return $this->diff(static::now($this->getTimezone()))->y;
    }

    /**
     * Place l'instance à la fin de la seconde (999999 microsecondes)
     */
    public function endOfSecond(): static
    {
        $date = $this->copy();

        $date->setTime(
            $date->getHour(),
            $date->getMinute(),
            $date->getSecond(),
            999999
        );

        return $date;
    }

    /**
     * Place l'instance à la fin de la minute (59 secondes)
     */
    public function endOfMinute(): static
    {
        return $this->setSecond(59);
    }

    /**
     * Place l'instance à la fin de l'heure (59 minutes, 59 secondes)
     */
    public function endOfHour(): static
    {
        return $this->setMinute(59)->setSecond(59);
    }

    /**
     * Place l'instance à la fin de la journée (23:59:59).
     */
    public function endOfDay(): static
    {
        return $this->setHour(23)->setMinute(59)->setSecond(59);
    }

    /**
     * Place l'instance à la fin de la semaine (dimanche, 23:59:59).
     */
    public function endOfWeek(): static
    {
        $dayOfWeek = (int) $this->format('w');
        $date      = clone $this;
        $date->modify('+' . (6 - $dayOfWeek) . ' days');

        return $date->endOfDay();
    }

    /**
     * Place l'instance à la fin du mois (dernier jour, 23:59:59).
     */
    public function endOfMonth(): static
    {
        $lastDay = (int) $this->format('t');

        return $this->setDay($lastDay)->endOfDay();
    }

    /**
     * Place l'instance à la fin de l'année (31 décembre, 23:59:59).
     */
    public function endOfYear(): static
    {
        return $this->setMonth(12)->setDay(31)->endOfDay();
    }

    /**
     * Place l'instance à la fin du trimestre courant (dernier jour du trimestre, 23:59:59).
     */
    public function endOfQuarter(): static
    {
        $month   = $this->quarter() * 3;
        $date    = $this->setMonth($month);
        $lastDay = (int) $date->format('t');

        return $date->setDay($lastDay)->endOfDay();
    }

    /**
     * Place l'instance au début de la seconde (0 microseconde)
     */
    public function startOfSecond(): static
    {
        $date = $this->copy();

        $date->setTime(
            $date->getHour(),
            $date->getMinute(),
            $date->getSecond(),
            0
        );

        return $date;
    }

    /**
     * Place l'instance au début de la minute (secondes à 0).
     */
    public function startOfMinute(): static
    {
        return $this->setSecond(0);
    }

    /**
     * Place l'instance au début de l'heure (0 minute, 0 seconde)
     */
    public function startOfHour(): static
    {
        return $this->setMinute(0)->setSecond(0);
    }

    /**
     * Place l'instance au début de la journée (00:00:00).
     */
    public function startOfDay(): static
    {
        return $this->setHour(0)->setMinute(0)->setSecond(0);
    }

    /**
     * Place l'instance au début de la semaine (lundi, 00:00:00).
     */
    public function startOfWeek(): static
    {
        $dayOfWeek = (int) $this->format('w');
        $date      = clone $this;
        $date->modify('-' . $dayOfWeek . ' days');

        return $date->startOfDay();
    }

    /**
     * Place l'instance au début du mois (1er jour, 00:00:00).
     */
    public function startOfMonth(): static
    {
        return $this->setDay(1)->startOfDay();
    }

    /**
     * Place l'instance au début de l'année (1er janvier, 00:00:00).
     */
    public function startOfYear(): static
    {
        return $this->setMonth(1)->setDay(1)->startOfDay();
    }

    /**
     * Place l'instance au début du trimestre courant (1er jour du trimestre, 00:00:00).
     */
    public function startOfQuarter(): static
    {
        $month = (($this->quarter() - 1) * 3) + 1;

        return $this->setMonth($month)->setDay(1)->startOfDay();
    }

    /**
     * Retourne la date au format Atom (ISO 8601).
     */
    public function toAtomString(): string
    {
        return $this->format(DATE_ATOM);
    }

    /**
     * Retourne la date au format ISO 8601.
     */
    public function toIso8601String(): string
    {
        return $this->format('c');
    }

    /**
     * Retourne la date au format RFC 2822.
     */
    public function toRfc2822String(): string
    {
        return $this->format(DATE_RFC2822);
    }

    /**
     * Retourne la date au format RFC 3339.
     */
    public function toRfc3339String(): string
    {
        return $this->format(DATE_RFC3339);
    }

    // --------------------------------------------------------------------
    // Addition/Soustraction
    // --------------------------------------------------------------------

    /**
     * Ajoute un jour à la date (nouvelle instance).
     */
    public function addDay(): static
    {
        return $this->addDays(1);
    }

    /**
     * Ajoute plusieurs jours à la date (nouvelle instance).
     */
    public function addDays(int $value): static
    {
        $date = clone $this;
        $date->modify("+{$value} day");

        return $date;
    }

    /**
     * Ajoute une semaine à la date (nouvelle instance).
     */
    public function addWeek(): static
    {
        return $this->addWeeks(1);
    }

    /**
     * Ajoute un nombre de semaines à la date (nouvelle instance).
     */
    public function addWeeks(int $value): static
    {
        $date = clone $this;
        $date->modify("+{$value} week");

        return $date;
    }

    /**
     * Ajoute un mois à la date (nouvelle instance).
     */
    public function addMonth(bool $overflow = false): static
    {
        return $this->addMonths(1, $overflow);
    }

    /**
     * Ajoute un nombre de mois à la date.
     *
     * Si $overflow = true (défaut), autorise le dépassement (ex. : 31 janv. +1 mois = 3 mars).
     * Si $overflow = false, clamp au dernier jour du mois cible (ex. : 31 janv. +1 mois = 28 fév.).
     *
     * @param float|int $value    Nombre de mois à ajouter (positif ou négatif). Les floats sont arrondis à l'entier le plus proche.
     * @param bool      $overflow Autoriser le dépassement (true par défaut).
     *
     * @return static Une nouvelle instance avec la date ajustée.
     *
     * @throws InvalidArgumentException Si $value n'est pas numérique.
     */
    public function addMonths(float|int $value, bool $overflow = true): static
    {
        if (! is_numeric($value)) {
            throw new InvalidArgumentException('Le paramètre $value doit être numérique.');
        }

        $months = (int) round($value); // Arrondir les floats pour simplicité
        if ($months === 0) {
            return $this->copy();
        }

        $date      = clone $this;
        $sign      = $months > 0 ? '+' : '-';
        $absMonths = abs($months);

        if ($overflow) {
            // Overflow standard : utilise modify
            $date->modify("{$sign}{$absMonths} months");
        } else {
            // No overflow : ajoute les mois, puis clamp le jour
            $currentYear  = $date->year;
            $currentMonth = $date->month;
            $currentDay   = $date->day;

            $newMonth = $currentMonth + $months;

            while ($newMonth > 12) {
                $newMonth -= 12;
                $currentYear++;
            }

            while ($newMonth < 1) {
                $newMonth += 12;
                $currentYear--;
            }

            $daysInNewMonth = $date->createFromDate($currentYear, $newMonth, 1)->daysInMonth;
            $newDay         = min($currentDay, $daysInNewMonth);

            $date->setDate($currentYear, $newMonth, $newDay);
        }

        return $date;
    }

    /**
     * Ajoute un trimestre à la date (nouvelle instance).
     */
    public function addQuarter(): static
    {
        return $this->addQuarters(1);
    }

    /**
     * Ajoute plusieurs trimestres à la date (nouvelle instance).
     */
    public function addQuarters(int $value): static
    {
        return $this->addMonths($value * 3);
    }

    /**
     * Ajoute une année à la date (nouvelle instance).
     */
    public function addYear(): static
    {
        return $this->addYears(1);
    }

    /**
     * Ajoute plusieurs années à la date (nouvelle instance).
     */
    public function addYears(int $value): static
    {
        $date = clone $this;
        $date->modify("+{$value} year");

        return $date;
    }

    /**
     * Ajoute une heure à la date (nouvelle instance).
     */
    public function addHour(): static
    {
        return $this->addHours(1);
    }

    /**
     * Ajoute plusieurs heures à la date (nouvelle instance).
     */
    public function addHours(int $value): static
    {
        $date = clone $this;
        $date->modify("+{$value} hour");

        return $date;
    }

    /**
     * Ajoute une minute à la date (nouvelle instance).
     */
    public function addMinute(): static
    {
        return $this->addMinutes(1);
    }

    /**
     * Ajoute un nombre de minutes à la date (support des floats : ex. 1.5 = 1 min 30 s).
     *
     * @param float|int $value Nombre de minutes à ajouter (positif ou négatif).
     *
     * @return static Une nouvelle instance avec la date ajustée.
     */
    public function addMinutes(float|int $value): static
    {
        $date = clone $this;

        if ($value === 0) {
            return $date;
        }

        $minutes = (int) floor(abs($value));
        $seconds = (int) ((abs($value) - $minutes) * 60);

        $interval = new DateInterval(sprintf('PT%sM%sS', $minutes, $seconds));

        if ($value < 0) {
            $date->sub($interval);
        } else {
            $date->add($interval);
        }

        return $date;
    }

    /**
     * Ajoute une seconde à la date (nouvelle instance).
     */
    public function addSecond(): static
    {
        return $this->addSeconds(1);
    }

    /**
     * Ajoute plusieurs secondes à la date (nouvelle instance).
     */
    public function addSeconds(float|int $value): static
    {
        $date = clone $this;
        $date->modify("+{$value} second");

        return $date;
    }

    /**
     * Soustrait un jour à la date (nouvelle instance).
     */
    public function subDay(): static
    {
        return $this->subDays(1);
    }

    /**
     * Soustrait plusieurs jours à la date (nouvelle instance).
     */
    public function subDays(float|int $value): static
    {
        $date = clone $this;
        $date->modify("-{$value} day");

        return $date;
    }

    /**
     * Soustrait une semaine à la date (nouvelle instance).
     */
    public function subWeek(): static
    {
        return $this->subWeeks(1);
    }

    /**
     * Soustrait un nombre de semaines à la date (nouvelle instance).
     */
    public function subWeeks(int $value): static
    {
        $date = clone $this;
        $date->modify("-{$value} week");

        return $date;
    }

    /**
     * Soustrait un mois à la date (nouvelle instance).
     */
    public function subMonth(): static
    {
        return $this->subMonths(1);
    }

    /**
     * Soustrait plusieurs mois à la date (nouvelle instance).
     */
    public function subMonths(int $value): static
    {
        $date = clone $this;
        $date->modify("-{$value} month");

        return $date;
    }

    /**
     * Soustrait un trimestre à la date (nouvelle instance).
     */
    public function subQuarter(): static
    {
        return $this->subQuarters(1);
    }

    /**
     * Soustrait plusieurs trimestres à la date (nouvelle instance).
     */
    public function subQuarters(int $value): static
    {
        return $this->subMonths($value * 3);
    }

    /**
     * Soustrait une année à la date (nouvelle instance).
     */
    public function subYear(): static
    {
        return $this->subYears(1);
    }

    /**
     * Soustrait plusieurs années à la date (nouvelle instance).
     */
    public function subYears(int $value): static
    {
        $date = clone $this;
        $date->modify("-{$value} year");

        return $date;
    }

    /**
     * Soustrait une heure à la date (nouvelle instance).
     */
    public function subHour(): static
    {
        return $this->subHours(1);
    }

    /**
     * Soustrait plusieurs heures à la date (nouvelle instance).
     */
    public function subHours(int $value): static
    {
        $date = clone $this;
        $date->modify("-{$value} hour");

        return $date;
    }

    /**
     * Soustrait une minute à la date (nouvelle instance).
     */
    public function subMinute(): static
    {
        return $this->subMinutes(1);
    }

    /**
     * Soustrait plusieurs minutes à la date (nouvelle instance).
     */
    public function subMinutes(int $value): static
    {
        $date = clone $this;
        $date->modify("-{$value} minute");

        return $date;
    }

    /**
     * Soustrait une seconde à la date (nouvelle instance).
     */
    public function subSecond(): static
    {
        return $this->subSeconds(1);
    }

    /**
     * Soustrait plusieurs secondes à la date (nouvelle instance).
     */
    public function subSeconds(int $value): static
    {
        $date = clone $this;
        $date->modify("-{$value} second");

        return $date;
    }

    // --------------------------------------------------------------------
    // Timezones
    // --------------------------------------------------------------------

    /**
     * Définit le fuseau horaire par défaut
     *
     * @param string $timezone Le fuseau horaire à définir (ex: 'Europe/Paris')
     * @param bool   $globaly  Si true, change aussi le fuseau horaire par défaut de PHP
     */
    public static function setDefaultTimezone(string $timezone, bool $globaly = false): void
    {
        // Valide le fuseau horaire avant de le définir
        try {
            new DateTimeZone($timezone);
        } catch (Exception) {
            throw new InvalidArgumentException("Le fuseau horaire '{$timezone}' n'est pas valide");
        }

        static::$DEFAULT_TIMEZONE = $timezone;

        if ($globaly) {
            date_default_timezone_set($timezone);
        }
    }

    /**
     * Retourne le fuseau horaire par défaut
     *
     * Si aucun fuseau par défaut n'a été défini explicitement,
     * retourne le fuseau horaire par défaut de PHP
     */
    public static function getDefaultTimezone(): string
    {
        return static::$DEFAULT_TIMEZONE ?? date_default_timezone_get();
    }

    /**
     * Retourne une nouvelle instance dans le fuseau horaire donné.
     */
    public function withTimezone(DateTimeZone|string $timezone): static
    {
        $date = clone $this;
        $date->setTimezone($timezone);

        return $date;
    }

    /**
     * Retourne l'abréviation du fuseau horaire (ex : 'CET', 'UTC').
     */
    public function timezoneAbbr(): string
    {
        return $this->format('T');
    }

    /**
     * Renvoie le nom du fuseau horaire actuel.
     */
    public function timezoneName(): string
    {
        return $this->getTimezone()?->getName() ?? static::getDefaultTimezone();
    }

    /**
     * Retourne la liste des fuseaux horaires disponibles (optionnel : filtrer par région ex 'Europe').
     */
    public static function listTimezones(?string $region = null): array
    {
        return DateTimeZone::listIdentifiers($region ? DateTimeZone::PER_COUNTRY : DateTimeZone::ALL, $region);
    }

    /**
     * Convertit la date dans un autre fuseau horaire (nouvelle instance).
     */
    public function convertToTimezone(DateTimeZone|string $timezone): static
    {
        return $this->withTimezone($timezone);
    }

    /**
     * Convertit la date dans le fuseau horaire de l'utilisateur (si fourni).
     */
    public function toUserTimezone(?string $userTimezone = null): static
    {
        if ($userTimezone) {
            return $this->withTimezone($userTimezone);
        }

        return $this;
    }

    /**
     * Vérifie si la date est en UTC.
     */
    public function isUtc(): bool
    {
        if (false === $timezone = $this->getTimezone()) {
            return false;
        }

        return $timezone->getName() === 'UTC' || $timezone->getOffset($this) === 0;
    }

    /**
     * Vérifie si la date est dans le fuseau local par défaut.
     */
    public function isLocal(): bool
    {
        return $this->timezoneName() === date_default_timezone_get();
    }

    /**
     * Vérifie si nous sommes actuellement à l'heure d'été ?
     */
    public function isDst(): bool
    {
        return $this->format('I') === '1';
    }

    /**
     * Retourne le décalage horaire en heures par rapport à UTC.
     */
    public function offsetHours(): int
    {
        return (int) ($this->getOffset() / 3600);
    }

    /**
     * Retourne le décalage horaire en minutes par rapport à UTC.
     */
    public function offsetMinutes(): int
    {
        return (int) ($this->getOffset() / 60);
    }

    /**
     * Formate la date selon la locale et le format ICU (ex : 'EEEE d MMMM y').
     */
    public function formatLocalized(string $format, ?string $locale = null): string
    {
        $locale ??= $this->locale ?? 'fr_FR';
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL, $this->getTimezone(), null, $format);

        return $formatter->format($this);
    }

    /**
     * Parse une date localisée (format ICU) en instance Date.
     */
    public static function parseLocalized(string $date, string $format, ?string $locale = null, DateTimeZone|string|null $timezone = null): static
    {
        $locale ??= 'fr_FR';
        $timezone ??= date_default_timezone_get();
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::FULL, IntlDateFormatter::FULL, $timezone, null, $format);
        $timestamp = $formatter->parse($date);

        if ($timestamp === false) {
            throw new InvalidArgumentException("Impossible de parser la date localisée : {$date}");
        }

        return new static(date('Y-m-d H:i:s', $timestamp), $timezone, $locale);
    }

    /**
     * Crée un itérateur pour une période de temps.
     *
     * Permet de boucler sur une plage de dates avec un intervalle défini.
     *
     * @param string $interval Intervalle de modification (ex: '1 day', '2 weeks').
     *
     * @return Generator<static>
     */
    public static function period(DateTimeInterface|string $start, DateTimeInterface|string $end, string $interval = '1 day'): Generator
    {
        $startDate = ($start instanceof DateTimeInterface) ? static::createFromInstance($start) : static::parse($start);
        $endDate   = ($end instanceof DateTimeInterface) ? static::createFromInstance($end) : static::parse($end);

        for ($current = $startDate->copy(); $current <= $endDate; $current->modify('+' . $interval)) {
            yield $current->copy();
        }
    }

    /**
     * Retourne la date et l'heure au format localisé court (ex : 31/01/2024 13:45).
     */
    public function toLocaleString(?string $locale = null): string
    {
        $locale ??= $this->locale ?? 'fr_FR';
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::SHORT, $this->getTimezone());

        return $formatter->format($this);
    }

    /**
     * Retourne la date au format localisé court (ex : 31/01/2024).
     */
    public function toLocaleDateString(?string $locale = null): string
    {
        $locale ??= $this->locale ?? 'fr_FR';
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::SHORT, IntlDateFormatter::NONE, $this->getTimezone());

        return $formatter->format($this);
    }

    /**
     * Retourne l'heure au format localisé court (ex : 13:45).
     */
    public function toLocaleTimeString(?string $locale = null): string
    {
        $locale ??= $this->locale ?? 'fr_FR';
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::SHORT, $this->getTimezone());

        return $formatter->format($this);
    }

    /**
     * Formate une plage de dates de manière intelligente et localisée.
     *
     * Exemples de sortie :
     * - "5 janvier 2024" (si les dates sont identiques)
     * - "5 - 10 janvier 2024" (même mois, même année)
     * - "28 janvier - 5 février 2024" (même année, mois différents)
     * - "30 décembre 2023 - 5 janvier 2024" (années différentes)
     *
     * @param string|null $locale La locale à utiliser pour le formatage (ex: 'fr_FR', 'en_US').
     *                            Si null, utilise la locale de l'instance ou le défaut.
     *
     * @return string La chaîne de caractères formatée.
     */
    public function toRangeString(self $endDate, ?string $locale = null): string
    {
        $locale ??= $this->locale ?? 'fr_FR';

        if ($this->isSameDay($endDate)) {
            return $this->formatLocalized('d MMMM yyyy', $locale);
        }

        if ($this->getYear() !== $endDate->getYear()) {
            return $this->formatLocalized('d MMMM yyyy', $locale) . ' - ' . $endDate->formatLocalized('d MMMM yyyy', $locale);
        }

        return $this->formatLocalized($this->getMonth() !== $endDate->getMonth() ? 'd MMMM' : 'd', $locale) . ' - ' . $endDate->formatLocalized('d MMMM yyyy', $locale);
    }

    /**
     * Renvoie l'objet IntlCalendar utilisé pour cet objet.
     */
    public function getCalendar(): IntlCalendar
    {
        return IntlCalendar::fromDateTime($this, $this->locale);
    }

    // --------------------------------------------------------------------
    // Pour les tests unitaires
    // --------------------------------------------------------------------

    /**
     * Crée une instance de Date qui sera renvoyée pendant le test
     * lors de l'appel de « Date::now() » à la place de l'heure actuelle.
     */
    public static function setTestNow(DateTimeInterface|string|null $datetime = null, DateTimeZone|string|null $timezone = null, ?string $locale = null)
    {
        // Réinitialiser l'instance de test
        if ($datetime === null) {
            static::$testNow = null;

            return;
        }

        // Convertir en instance Date
        if (is_string($datetime)) {
            $datetime = new static($datetime, $timezone, $locale);
        } elseif ($datetime instanceof DateTimeInterface && ! $datetime instanceof self) {
            $datetime = new static($datetime->format('Y-m-d H:i:s'), $timezone);
        }

        static::$testNow = $datetime;
    }

    /**
     * Indique si une instance testNow est enregistrée.
     */
    public static function hasTestNow(): bool
    {
        return static::$testNow !== null;
    }

    // --------------------------------------------------------------------
    // Méthodes magiques
    // --------------------------------------------------------------------

    /**
     * Permet d'accéder dynamiquement à des propriétés (getX, isX) comme dans Carbon.
     *
     * Exemple : $date->year, $date->isLeapYear
     */
    public function __get(string $name): mixed
    {
        if (method_exists($this, $getter = 'get' . ucfirst($name))) {
            return $this->{$getter}();
        }

        if (method_exists($this, $is = 'is' . ucfirst($name))) {
            return $this->{$is}();
        }

        return null;
    }

    /**
     * Permet de définir dynamiquement des propriétés (setX) et des propriétés calculées.
     * Exemple : $date->year = 2025; $date->dayOfWeek = 3;
     *
     * @param mixed $value
     */
    public function __set(string $name, $value): void
    {
        if (method_exists($this, $setter = 'set' . ucfirst($name))) {
            $this->{$setter}($value);

            return;
        }

        // Gestion des propriétés calculées dynamiques
        if ($name === 'dayOfWeek') {
            $currentDow = (int) $this->format('w');
            $diff       = (int) $value - $currentDow;
            $this->modify("{$diff} days");

            return;
        }

        throw new BadMethodCallException("La propriété '{$name}' ne peut pas être définie sur " . static::class);
    }

    /**
     * Permet de vérifier dynamiquement l'existence d'une propriété (getX, isX).
     */
    public function __isset(string $name): bool
    {
        $getter = 'get' . ucfirst($name);
        $is     = 'is' . ucfirst($name);

        return method_exists($this, $getter) || method_exists($this, $is);
    }

    /**
     * Permet d'appeler dynamiquement les méthodes magiques Carbon-like (ne, lt, equals, etc.),
     * ainsi que les méthodes getX, setX, isX dynamiquement.
     */
    public function __call(string $method, array $parameters = []): mixed
    {
        if (isset($this->facadesMethodsMapping[$method])) {
            return call_user_func_array([$this, $this->facadesMethodsMapping[$method]], $parameters);
        }

        if (substr($method, 0, 3) === 'get') {
            return $this->getDateAttribute(substr($method, 3));
        }

        throw new BadMethodCallException("La méthode magique '{$method}' n'existe pas sur " . static::class);
    }

    /**
     * Affiche une version abrégée de la date et de l'heure.
     *
     * L'affichage n'est PAS localisé intentionnellement.
     */
    public function __toString(): string
    {
        return $this->format(static::$defaultDateFormat);
    }

    /**
     * Cette méthode est appelée lorsque nous désérialisons l'objet Date.
     */
    public function __wakeup(): void
    {
        if (null === $this->timezone) {
            $this->timezone = new DateTimeZone(static::getDefaultTimezone());
        }

        parent::__construct($this->date, $this->timezone);
    }

    /**
     * Get a date attribute.
     */
    protected function getDateAttribute(string $attribute): mixed
    {
        return match ($attribute) {
            'DaysInMonth'       => $this->daysInMonth(),
            'DaySuffix'         => $this->format('S'),
            'GmtDifference'     => $this->format('O'),
            'SecondsSinceEpoch' => (int) $this->format('U'),
            default             => throw new InvalidArgumentException('The date attribute [' . $attribute . '] could not be found.')
        };
    }
}
