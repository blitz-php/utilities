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

use InvalidArgumentException;

/**
 * Gestionnaire de jours fériés.
 *
 * Calcule les jours fériés pour un pays et une année donnés.
 * Actuellement, seul 'FR' (France) est supporté à titre d'exemple.
 */
class Holiday
{
    /**
     * Cache des jours fériés calculés par pays et par année.
     *
     * @var array<string, array<int, array<string, string>>>
     */
    protected static array $holidaysCache = [];

    /**
     * Cache des dates de Pâques.
     *
     * @var array<int, Date>
     */
    protected static array $easterCache = [];

    /**
     * Définitions personnalisées des jours fériés par pays.
     *
     * @var array<string, array<string, string>>
     */
    protected static array $customHolidays = [];

    /**
     * Vérifie si une date donnée est un jour férié pour un pays.
     *
     * @param Date        $date        La date à vérifier.
     * @param string|null $countryCode Le code du pays (ex: 'FR'). Si null, utilise la locale de la date.
     */
    public static function isHoliday(Date $date, ?string $countryCode = null): bool
    {
        $countryCode = self::resolveCountryCode($countryCode, $date->getLocale());
        $year        = $date->getYear();

        if (! isset(self::$holidaysCache[$countryCode][$year])) {
            self::loadHolidays($year, $countryCode);
        }

        $dateString = $date->toDateString();

        return in_array($dateString, self::$holidaysCache[$countryCode][$year] ?? [], true);
    }

    /**
     * Définit les jours fériés personnalisés pour un pays donné.
     *
     * Cette méthode permet de remplacer ou d'ajouter des définitions de jours fériés pour un code de pays.
     * Les règles doivent suivre l'un des formats suivants :
     * - Date fixe : 'MM-DD' (ex. : '01-01' pour le Jour de l'An).
     * - Date relative à Pâques : 'easter + N' ou 'easter - N' (ex. : 'easter + 1' pour le Lundi de Pâques).
     * - Jour de la semaine spécifique : 'nth_weekday:weekday:n:month' (ex. : 'nth_weekday:monday:3:january' pour le 3e lundi de janvier).
     * Les jours fériés définis ici remplacent les définitions par défaut pour le pays spécifié.
     * Le cache des jours fériés pour les années concernées est invalidé après cette opération.
     *
     * @param string                $countryCode Le code du pays (ex. : 'FR', 'US', 'CM').
     * @param array<string, string> $holidays    Tableau associatif des jours fériés, où la clé est le nom du jour férié et la valeur est la règle.
     *
     * @throws InvalidArgumentException Si une règle de jour férié est invalide.
     *
     * @example
     * // Définir des jours fériés personnalisés pour les États-Unis
     * Holiday::setHolidays('US', [
     *     'independence_day' => '07-04',                    // 4 juillet
     *     'mlk_day'          => 'nth_weekday:monday:3:january', // 3e lundi de janvier
     *     'easter_monday'    => 'easter + 1',               // Lundi de Pâques
     * ]);
     *
     * // Vérifier si une date est un jour férié
     * $date = Date::create('2025-07-04', 'America/New_York', 'en_US');
     * echo $date->isHoliday('US') ? 'Jour férié' : 'Pas férié'; // Affiche "Jour férié"
     */
    public static function setHolidays(string $countryCode, array $holidays): void
    {
        $countryCode = strtoupper($countryCode);

        // Valider les règles
        foreach ($holidays as $name => $rule) {
            if (! is_string($name) || empty($name)) {
                throw new InvalidArgumentException("Le nom du jour férié doit être une chaîne non vide pour le pays '{$countryCode}'.");
            }
            if (! is_string($rule) || (
                ! preg_match('/^\d{2}-\d{2}$/', $rule)
                && ! preg_match('/^easter\s*[+-]?\s*\d*$/', $rule)
                && ! preg_match('/^nth_weekday:[a-z]+:\d+:[a-z]+$/', $rule)
            )) {
                throw new InvalidArgumentException("La règle '{$rule}' pour '{$name}' est invalide. Utilisez 'MM-DD', 'easter [+/-] N', ou 'nth_weekday:weekday:n:month'.");
            }
        }

        // Enregistrer les définitions personnalisées
        static::$customHolidays[$countryCode] = $holidays;

        // Invalider le cache pour ce pays
        unset(static::$holidaysCache[$countryCode]);
    }

    /**
     * Récupère la liste des jours fériés dans une période donnée.
     *
     * @param Date        $start       La date de début (inclusive).
     * @param Date        $end         La date de fin (inclusive).
     * @param string|null $countryCode Le code du pays (ex: 'FR', 'CM', 'US'). Si null, utilise la locale de la date de début.
     *
     * @return list<string> Liste des dates de jours fériés au format 'Y-m-d'.
     */
    public static function getHolidaysInPeriod(Date $start, Date $end, ?string $countryCode = null): array
    {
        $countryCode = self::resolveCountryCode($countryCode, $start->getLocale());
        $startYear   = $start->getYear();
        $endYear     = $end->getYear();
        $holidays    = [];

        // Charger les jours fériés pour toutes les années concernées
        for ($year = $startYear; $year <= $endYear; $year++) {
            if (! isset(self::$holidaysCache[$countryCode][$year])) {
                self::loadHolidays($year, $countryCode);
            }

            foreach (self::$holidaysCache[$countryCode][$year] as $date) {
                if ($date >= $start->toDateString() && $date <= $end->toDateString()) {
                    $holidays[] = $date;
                }
            }
        }

        return array_unique($holidays);
    }

    /**
     * Charge et calcule les jours fériés pour une année et un pays donnés.
     */
    protected static function loadHolidays(int $year, string $countryCode): void
    {
        $definitions                              = self::getDefinitions($countryCode);
        self::$holidaysCache[$countryCode][$year] = [];

        foreach ($definitions as $name => $rule) {
            $holidayDate = self::calculateDate($rule, $year);
            if ($holidayDate) {
                self::$holidaysCache[$countryCode][$year][$name] = $holidayDate->toDateString();
            }
        }
    }

    /**
     * Calcule une date de jour férié à partir d'une règle.
     */
    protected static function calculateDate(string $rule, int $year): ?Date
    {
        // Règle pour une date fixe (MM-DD)
        if (preg_match('/^(\d{2})-(\d{2})$/', $rule, $matches)) {
            return Date::createFromDate($year, (int) $matches[1], (int) $matches[2])->startOfDay();
        }

        // Règle pour une date relative à Pâques
        if (str_contains($rule, 'easter')) {
            $easterDate = self::getEasterDate($year);
            $rule       = str_replace(' ', '', $rule);

            if (str_contains($rule, '+')) {
                [, $days] = explode('+', $rule);

                return $easterDate->addDays((int) $days);
            }
            if (str_contains($rule, '-')) {
                [, $days] = explode('-', $rule);

                return $easterDate->subDays((int) $days);
            }

            return $easterDate;
        }

        // Règle pour un jour de la semaine spécifique (nth_weekday:weekday:n:month)
        if (preg_match('/^nth_weekday:([a-z]+):(-?\d+):([a-z]+)$/', $rule, $matches)) {
            $weekday = strtolower($matches[1]);
            $n       = (int) $matches[2]; // Peut être négatif pour "dernier"
            $month   = strtolower($matches[3]);

            // Mapper les mois
            $monthMap = [
                'january'  => 1, 'february' => 2, 'march' => 3, 'april' => 4, 'may' => 5,
                'june'     => 6, 'july' => 7, 'august' => 8, 'september' => 9, 'october' => 10,
                'november' => 11, 'december' => 12,
            ];
            if (! isset($monthMap[$month])) {
                return null;
            }
            $monthNumber = $monthMap[$month];

            // Mapper les jours de la semaine
            $weekdayMap = [
                'sunday'   => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3,
                'thursday' => 4, 'friday' => 5, 'saturday' => 6,
            ];
            if (! isset($weekdayMap[$weekday])) {
                return null;
            }
            $weekdayNumber = $weekdayMap[$weekday];

            // Calculer la date
            $date = Date::createFromDate($year, $monthNumber, 1)->startOfDay();
            if ($n > 0) {
                // Trouver le Nième jour de la semaine
                $firstWeekday = (int) $date->format('w');
                $daysToAdd    = ($weekdayNumber - $firstWeekday + 7) % 7 + ($n - 1) * 7;

                return $date->addDays($daysToAdd);
            }
            if ($n < 0) {
                // Trouver le dernier jour de la semaine (ex. : dernier lundi de mai)
                $date           = $date->endOfMonth();
                $lastWeekday    = (int) $date->format('w');
                $daysToSubtract = ($lastWeekday - $weekdayNumber + 7) % 7 + (abs($n) - 1) * 7;

                return $date->subDays($daysToSubtract);
            }
        }

        return null;
    }

    /**
     * Calcule et met en cache la date de Pâques pour une année donnée.
     */
    protected static function getEasterDate(int $year): Date
    {
        if (! isset(self::$easterCache[$year])) {
            $timestamp = easter_date($year);
            // On travaille en UTC pour éviter les problèmes de décalage horaire
            self::$easterCache[$year] = Date::createFromTimestamp($timestamp)->setTimezone('UTC');
        }

        return self::$easterCache[$year]->copy();
    }

    /**
     * Résout le code pays à utiliser.
     */
    private static function resolveCountryCode(?string $countryCode, ?string $locale): string
    {
        if ($countryCode) {
            return strtoupper($countryCode);
        }
        if ($locale && str_contains($locale, '_')) {
            return strtoupper(explode('_', $locale)[1]);
        }

        return 'FR'; // Défaut
    }

    /**
     * Récupère les définitions des jours fériés pour un pays.
     * Priorise les définitions personnalisées si elles existent.
     *
     * @param string $countryCode Le code du pays (ex: 'FR', 'CM', 'US').
     *
     * @return array<string, string> Tableau des définitions des jours fériés.
     */
    protected static function getDefinitions(string $countryCode): array
    {
        // Vérifier si des définitions personnalisées existent
        if (isset(static::$customHolidays[$countryCode])) {
            return static::$customHolidays[$countryCode];
        }

        // Définitions par défaut
        return match (strtoupper($countryCode)) {
            'FR' => [
                'new_year'       => '01-01',       // Jour de l'An
                'easter_monday'  => 'easter + 1',  // Lundi de Pâques
                'labour_day'     => '05-01',       // Fête du Travail
                'victory_day'    => '05-08',       // Victoire 1945
                'ascension_day'  => 'easter + 39', // Ascension
                'whit_monday'    => 'easter + 50', // Lundi de Pentecôte
                'national_day'   => '07-14',       // Fête Nationale
                'assumption_day' => '08-15',       // Assomption
                'all_saints_day' => '11-01',       // Toussaint
                'armistice_day'  => '11-11',       // Armistice 1918
                'christmas_day'  => '12-25',       // Noël
            ],
            'CM' => [
                'new_year'       => '01-01',       // Jour de l'An
                'youth_day'      => '02-11',       // Journée de la jeunesse
                'good_friday'    => 'easter - 2',  // Vendredi Saint
                'easter'         => 'easter',       // Pâques
                'labour_day'     => '05-01',       // Fête du Travail
                'ascension_day'  => 'easter + 39', // Ascension
                'national_day'   => '05-20',       // Journée nationale
                'eid_al_adha'    => '06-07',       // Fête du mouton (approximatif, basé sur 2025)
                'assumption_day' => '08-15',       // Assomption
                'all_saints_day' => '11-01',       // Toussaint
                'christmas_day'  => '12-25',       // Noël
            ],
            'US' => [
                'new_year'         => '01-01',                    // Jour de l'An
                'mlk_day'          => 'nth_weekday:monday:3:january', // Martin Luther King Jr. Day
                'presidents_day'   => 'nth_weekday:monday:3:february', // Presidents' Day
                'memorial_day'     => 'nth_weekday:monday:-1:may',    // Memorial Day (dernier lundi de mai)
                'juneteenth'       => '06-19',                    // Juneteenth
                'independence_day' => '07-04',                    // Jour de l'Indépendance
                'labor_day'        => 'nth_weekday:monday:1:september', // Labor Day
                'columbus_day'     => 'nth_weekday:monday:2:october',  // Columbus Day / Indigenous Peoples' Day
                'veterans_day'     => '11-11',                    // Veterans Day
                'thanksgiving'     => 'nth_weekday:thursday:4:november', // Thanksgiving
                'christmas_day'    => '12-25',                    // Noël
            ],
            default => [],
        };
    }
}
