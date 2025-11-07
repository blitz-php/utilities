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

        // Formats européens
        'd/m/Y H:i:s' => '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/',
        'd.m.Y H:i:s' => '/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}$/',
        'd/m/Y'       => '/^\d{2}\/\d{2}\/\d{4}$/',
        'd.m.Y'       => '/^\d{2}\.\d{2}\.\d{4}$/',

        // Formats américains
        'm/d/Y H:i:s' => '/^\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2}$/',
        'm-d-Y H:i:s' => '/^\d{2}-\d{2}-\d{4} \d{2}:\d{2}:\d{2}$/',
        'm/d/Y'       => '/^\d{2}\/\d{2}\/\d{4}$/',
        'm-d-Y'       => '/^\d{2}-\d{2}-\d{4}$/',

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

    private static function guessFormatFromParsedDate(string $dateString): string
    {
        $date  = new DateTime($dateString);
        $parts = [
            'Y' => $date->format('Y'),
            'm' => $date->format('m'),
            'd' => $date->format('d'),
            'H' => $date->format('H'),
            'i' => $date->format('i'),
            's' => $date->format('s'),
        ];

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
