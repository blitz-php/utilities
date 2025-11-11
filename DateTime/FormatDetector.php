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

use DateTime;
use Exception;

class FormatDetector
{
    private const FORMAT_PATTERNS = [
        // Formats ISO et standards
        'Y-m-d\TH:i:s.uP' => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\.\d{6}[+-]\d{2}:\d{2}$/',   // ISO 8601 avec microsecondes
        'Y-m-d\TH:i:sP'   => '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/',          // ISO 8601
        'Y-m-d H:i:s.u'   => '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\.\d{6}$/',                  // DateTime avec microsecondes
        'Y-m-d H:i:s'     => '/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/',
        'Y-m-d'           => '/^\d{4}-\d{2}-\d{2}$/',

		// Formats américains
        'm/d/Y H:i:s' => '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/',
        'm-d-Y H:i:s' => '/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/',
        'm/d/Y'       => '/^\d{2}\/\d{2}\/\d{4}$/',
        'm-d-Y'       => '/^\d{2}-\d{2}-\d{4}$/',

		// Formats européens
        'd/m/Y H:i:s' => '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/',
        'd.m.Y H:i:s' => '/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}$/',
        'd/m/Y'       => '/^\d{2}\/\d{2}\/\d{4}$/',
        'd.m.Y'       => '/^\d{2}\.\d{2}\.\d{4}$/',

        // Formats spéciaux
        'Ymd'              => '/^\d{8}$/',
        'D, d M Y H:i:s O' => '/^[A-Za-z]{3}, \d{2} [A-Za-z]{3} \d{4} \d{2}:\d{2}:\d{2} [+-]\d{4}$/',   // RFC 2822
        'U'                => '/^\d{10}$/',                                                             // Timestamp Unix
        'U.u'              => '/^\d{10}\.\d{1,6}$/',                                                    // Timestamp avec microsecondes
    ];

    public static function detect(string $dateString): ?string
	{
		// Vérification rapide pour les timestamps
		if (ctype_digit($dateString) && strlen($dateString) === 10) {
			return 'U';
		}

		if (preg_match('/^\d{10}\.\d{1,6}$/', $dateString)) {
			return 'U.u';
		}

		// Patterns ambigus (slash) gérés séparément
		$slashPattern = '/^\d{2}\/\d{2}\/\d{4}( \d{2}:\d{2}:\d{2})?$/';
		if (preg_match($slashPattern, $dateString) && null !== $format = self::detectSlashFormat($dateString)) {
			return $format;
		}

		// Autres patterns (non ambigus)
		foreach (self::FORMAT_PATTERNS as $format => $pattern) {
			if (preg_match($pattern, $dateString)) {
				$date = DateTime::createFromFormat($format, $dateString);
				if ($date && $date->format($format) === $dateString) {
					return $format;
				}
			}
		}

		// Tentative de parsing automatique pour les formats non reconnus
		try {
			new DateTime($dateString);
			return self::guessFormatFromParsedDate($dateString);
		} catch (Exception $e) {
			return null;
		}
	}

	/**
	 * Détecte le format pour les chaînes avec slash (ambiguïté DD/MM vs MM/DD).
	 *
	 * @param string $dateString La chaîne à analyser (ex. : '01/08/2025').
	 *
	 * @return ?string Le format détecté ('d/m/Y' ou 'm/d/Y') ou null en cas d'ambiguïté.
	 */
	private static function detectSlashFormat(string $dateString): ?string
	{
		$formats = [
			'with_time' => [
				'd/m/Y H:i:s' => ['regex' => '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/'],
				'm/d/Y H:i:s' => ['regex' => '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/'],
			],
			'without_time' => [
				'd/m/Y' => ['regex' => '/^\d{2}\/\d{2}\/\d{4}$/'],
				'm/d/Y' => ['regex' => '/^\d{2}\/\d{2}\/\d{4}$/'],
			],
		];

		// Déterminer si avec ou sans temps
		$hasTime = strpos($dateString, ' ') !== false;

		$candidates = $hasTime ? $formats['with_time'] : $formats['without_time'];

		$possibleFormats = [];
		foreach ($candidates as $format => $info) {
			if (preg_match($info['regex'], $dateString)) {
				$date = DateTime::createFromFormat($format, $dateString);
				if ($date && $date->format($format) === $dateString) {
					$possibleFormats[$format] = [
						'date' => $date,
						'day' => (int) $date->format('d'),
						'month' => (int) $date->format('m'),
					];
				}
			}
		}

		// Si un seul candidat valide, le retourner
		if (count($possibleFormats) === 1) {
			return key($possibleFormats);
		}

		// Si zéro, fallback sur parsing auto
		if (empty($possibleFormats)) {
			return self::guessFormatFromParsedDate($dateString);
		}

		// Ambiguïté : appliquer heuristique
		// Extraire les valeurs parsed pour chaque format
		$ddmm = $possibleFormats['d/m/Y'] ?? null; // Ou avec H:i:s
		$mmdd = $possibleFormats['m/d/Y'] ?? null; // Ou avec H:i:s

		// Si jour >12 dans DD/MM, impossible → préférer MM/DD
		if ($ddmm && $ddmm['day'] > 12) {
			return 'm/d/Y';
		}

		// Si mois >12 dans MM/DD, impossible → préférer DD/MM
		if ($mmdd && $mmdd['month'] > 12) {
			return 'd/m/Y';
		}

		// Sinon ambigu (≤12 pour les deux) : biais européen DD/MM
		return null; // on laisse le foreach s'en occuper suivant l'ordre de definition des regex
	}

    private static function guessFormatFromParsedDate(string $dateString): string
    {
        $date  = new DateTime($dateString);

        // Analyse la structure originale pour déterminer les séparateurs
        if (str_contains($dateString, 'T')) {
            $format = 'Y-m-d\TH:i:s';
            if (str_contains($dateString, '.')) {
                $format .= '.u';
            }
            if (preg_match('/[+-]\d{2}:\d{2}$/', $dateString)) {
                $format .= 'P';
            }

            return $format;
        }

        // Détection des séparateurs
        $separator = self::detectSeparator($dateString);

        // Construction du format basé sur la position des éléments
        if (preg_match('/\d{4}' . preg_quote($separator) . '\d{2}' . preg_quote($separator) . '\d{2}/', $dateString)) {
            $format = 'Y' . $separator . 'm' . $separator . 'd';
        } else {
            $format = 'd' . $separator . 'm' . $separator . 'Y';
        }

        // Ajout du temps si présent
        if (str_contains($dateString, ' ')) {
            $format .= ' H:i:s';
            if (str_contains($dateString, '.')) {
                $format .= '.u';
            }
        }

        return $format;
    }

    private static function detectSeparator(string $dateString): string
    {
        foreach (['-', '/', '.', ' '] as $sep) {
            if (str_contains($dateString, $sep)) {
                return $sep;
            }
        }

        return '-'; // Par défaut
    }
}
