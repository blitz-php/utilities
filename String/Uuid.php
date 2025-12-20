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

use InvalidArgumentException;
use Random\Randomizer;
use RuntimeException;

/**
 * Génération et validation d'UUID (Universally Unique Identifier)
 *
 * Implémente les versions 3, 4 et 5 selon la norme RFC 4122.
 *
 * @credit		https://www.php.net/manual/en/function.uniqid.php#94959
 * @see         https://tools.ietf.org/html/rfc4122 RFC 4122
 */
class Uuid
{
    /**
     * Namespace DNS pré-défini (pour les UUID basés sur les noms)
     */
    public const NAMESPACE_DNS = '6ba7b810-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Namespace URL pré-défini (pour les UUID basés sur les noms)
     */
    public const NAMESPACE_URL = '6ba7b811-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Namespace OID pré-défini (pour les UUID basés sur les noms)
     */
    public const NAMESPACE_OID = '6ba7b812-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Namespace X500 pré-défini (pour les UUID basés sur les noms)
     */
    public const NAMESPACE_X500 = '6ba7b814-9dad-11d1-80b4-00c04fd430c8';

    /**
     * Version UUID (pour la validation)
     */
    private const UUID_VERSION = [
        3 => 'v3', // Basé sur MD5
        4 => 'v4', // Aléatoire
        5 => 'v5', // Basé sur SHA-1
    ];

    /**
     * Pattern de validation UUID
     */
    private const UUID_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';

    /**
     * Pattern de validation UUID avec version spécifique
     */
    private const UUID_VERSION_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-%s[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i';

    /**
     * Générateur de nombres aléatoires (PHP 8.2+)
     *
     * @var Randomizer|null
     */
    private static ?Randomizer $randomizer = null;

    /**
     * Génère un UUID de type v1 (basé sur le temps)
     *
     * Note: Non implémenté dans la version originale, ajouté pour complétude.
     *
     * @return string UUID version 1
     * @throws RuntimeException Si l'extension ext-uuid n'est pas disponible
     */
    public static function v1(): string
    {
        if (function_exists('uuid_create')) {
            return uuid_create(UUID_TYPE_TIME);
        }

        if (class_exists('Ramsey\Uuid\Uuid')) {
            return \Ramsey\Uuid\Uuid::uuid1()->toString();
        }

        throw new RuntimeException(
            'UUID v1 non supporté. ' .
            'Installez l\'extension PECL uuid ou le package ramsey/uuid.'
        );
    }

    /**
     * Génère un UUID de type v2 (DCE Security)
     *
     * Note: Rarement utilisé, implémentation basique.
     *
     * @param int $domain Domaine DCE (0-2)
     * @param int|null $identifier Identifiant local
	 *
     * @return string UUID version 2
	 *
     * @throws RuntimeException Si non supporté
     */
    public static function v2(int $domain = 0, ?int $identifier = null): string
    {
        if (! in_array($domain, [0, 1, 2], true)) {
            throw new InvalidArgumentException('Le domaine DCE doit être 0, 1 ou 2');
        }

        if (function_exists('uuid_create')) {
            // L'extension uuid ne supporte pas v2 directement
            // Nous générons un v4 et modifions les bits de version
            $uuid = uuid_create(UUID_TYPE_RANDOM);
            $bytes = uuid_parse($uuid);

            // Définit la version à 2 (bits 4-7 de time_hi_and_version = 0x20)
            $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x20);

            // Définit le domaine DCE (bits 0-7 de clock_seq_hi_and_reserved)
            $bytes[8] = chr(ord($bytes[8]) & 0x3F | ($domain << 6));

            return uuid_unparse($bytes);
        }

        throw new RuntimeException('UUID v2 non supporté dans cette implémentation');
    }

    /**
     * Génère un UUID de type v3 (basé sur MD5)
     *
     * @param string $namespace Namespace UUID (doit être un UUID valide)
     * @param string $name Nom à hache
	 * r
     * @return string UUID version 3
	 *
     * @throws InvalidArgumentException Si le namespace n'est pas un UUID valide
     */
    public static function v3(string $namespace, string $name): string
    {
        if (! static::isValid($namespace)) {
            throw new InvalidArgumentException(
                sprintf('Le namespace doit être un UUID valide, "%s" donné', $namespace)
            );
        }

        // Convertit le namespace en binaire
        $nhex = str_replace(['-', '{', '}'], '', $namespace);
        $nstr = '';

        for ($i = 0; $i < strlen($nhex); $i += 2) {
            $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        // Calcule le hash MD5
        $hash = md5($nstr . $name);

        // Construit l'UUID selon RFC 4122
        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0FFF) | 0x3000, // Version 3
            (hexdec(substr($hash, 16, 4)) & 0x3FFF) | 0x8000, // Variant RFC 4122
            substr($hash, 20, 12)
        );
    }

    /**
     * Génère un UUID de type v4 (aléatoire)
     *
     * Utilise random_bytes() pour la cryptographie sécurisée.
     *
     * @return string UUID version 4
     */
    public static function v4(): string
    {
        // Génère 16 octets aléatoires sécurisés
        $bytes = random_bytes(16);

        // Définit la version à 4 (bits 4-7 de time_hi_and_version = 0x40)
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40);

        // Définit la variante à RFC 4122 (bits 6-7 de clock_seq_hi_and_reserved = 0x80)
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        // Formatte en UUID
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Génère un UUID de type v4 avec mt_rand (moins sécurisé, mais déterministe)
     *
     * Utile pour les tests ou quand random_bytes() n'est pas disponible.
     *
     * @return string UUID version 4 (non cryptographique)
     * @deprecated Utilisez v4() pour une version cryptographique
     */
    public static function v4NonSecure(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0x0FFF) | 0x4000, // Version 4
            mt_rand(0, 0x3FFF) | 0x8000, // Variant RFC 4122
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF),
            mt_rand(0, 0xFFFF)
        );
    }

    /**
     * Génère un UUID de type v5 (basé sur SHA-1)
     *
     * Similaire à v3 mais utilise SHA-1 au lieu de MD5.
     *
     * @param string $namespace Namespace UUID (doit être un UUID valide)
     * @param string $name Nom à hacher
	 *
     * @return string UUID version 5
	 *
     * @throws InvalidArgumentException Si le namespace n'est pas un UUID valide
     */
    public static function v5(string $namespace, string $name): string
    {
        if (! static::isValid($namespace)) {
            throw new InvalidArgumentException(
                sprintf('Le namespace doit être un UUID valide, "%s" donné', $namespace)
            );
        }

        // Convertit le namespace en binaire
        $nhex = str_replace(['-', '{', '}'], '', $namespace);
        $nstr = '';

        for ($i = 0; $i < strlen($nhex); $i += 2) {
            $nstr .= chr(hexdec($nhex[$i] . $nhex[$i + 1]));
        }

        // Calcule le hash SHA-1
        $hash = sha1($nstr . $name);

        // Construit l'UUID selon RFC 4122
        return sprintf(
            '%08s-%04s-%04x-%04x-%12s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            (hexdec(substr($hash, 12, 4)) & 0x0FFF) | 0x5000, // Version 5
            (hexdec(substr($hash, 16, 4)) & 0x3FFF) | 0x8000, // Variant RFC 4122
            substr($hash, 20, 12)
        );
    }

    /**
     * Génère un UUID de type v6 (basé sur le temps, version réordonnée)
     *
     * Similaire à v1 mais avec les champs réordonnés pour un meilleur tri.
     *
     * @return string UUID version 6
	 *
     * @throws RuntimeException Si non supporté
     */
    public static function v6(): string
    {
        if (class_exists('Ramsey\Uuid\Uuid')) {
            return \Ramsey\Uuid\Uuid::uuid6()->toString();
        }

        // Implémentation manuelle basique (simplifiée)
        $time = microtime(true);
        $sec = (int) $time;
        $usec = (int) (($time - $sec) * 1000000);

        // Format 60-bit timestamp (RFC 4122 rev draft)
        $timestamp = sprintf(
            '%012x%04x',
            $sec,
            $usec
        );

        // Génère les octets aléatoires pour la partie "node"
        $node = random_bytes(6);
        $clockSeq = random_bytes(2);

        // Construit l'UUID v6
        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($timestamp, 0, 8),
            substr($timestamp, 8, 4),
            '6' . substr($timestamp, 12, 3), // Version 6
            (ord($clockSeq[0]) & 0x3F | 0x80) . substr(bin2hex($clockSeq), 1, 3),
            bin2hex($node)
        );
    }

    /**
     * Génère un UUID de type v7 (basé sur le temps Unix avec millisecondes)
     *
     * Nouvelle version pour un tri temporel plus efficace.
     *
     * @return string UUID version 7
     */
    public static function v7(): string
	{
		// Timestamp Unix en millisecondes (48 bits)
		$timestampMs = (int) (microtime(true) * 1000);

		// Extraire les parties du timestamp
		$timeHigh = ($timestampMs >> 12) & 0xFFFFFFFF; // 32 bits hauts
		$timeMid = ($timestampMs >> 28) & 0xFFFF;      // 16 bits suivants
		$timeLow = $timestampMs & 0xFFF;               // 12 bits bas

		// Version 7 (4 bits)
		$version = 0x7;

		// Bits aléatoires (74 bits)
		$randBytes = random_bytes(10);
		$randHex = bin2hex($randBytes);

		// Extraire les parties aléatoires
		$rand1 = hexdec(substr($randHex, 0, 4)) & 0x3FFF; // 14 bits
		$rand2 = substr($randHex, 4, 12);                 // 48 bits

		// Construire l'UUID
		return sprintf(
			'%08x-%04x-%04x-%04x-%012s',
			$timeHigh,                     // 32 bits hauts du timestamp
			$timeMid,                      // 16 bits suivants
			($timeLow << 4) | $version,    // 12 bits bas + version
			0x8000 | $rand1,               // Variant RFC 4122 + 14 bits aléatoires
			$rand2                         // 48 bits aléatoires
		);
	}

    /**
     * Génère un UUID de type v8 (personnalisé/spécifique)
     *
     * Pour les implémentations personnalisées.
     *
     * @param string $customData Données personnalisées (16 octets)
	 *
     * @return string UUID version 8
	 *
     * @throws InvalidArgumentException Si les données ne font pas 16 octets
     */
    public static function v8(string $customData): string
    {
        if (strlen($customData) !== 16) {
            throw new InvalidArgumentException('Les données personnalisées doivent faire exactement 16 octets');
        }

        // Version 8 (bits 4-7 = 0x80)
        $bytes = $customData;
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x80);
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /**
     * Vérifie si une chaîne donnée est un UUID valide.
     *
     * @param string $value Chaîne à vérifier
	 *
     * @return bool true si la chaîne est un UUID valide
     */
    public static function isValid(string $value): bool
    {
        return preg_match(self::UUID_PATTERN, $value) === 1;
    }

    /**
     * Vérifie si une chaîne donnée est un UUID valide d'une version spécifique.
     *
     * @param string $value Chaîne à vérifier
     * @param int $version Version UUID à vérifier (3, 4, 5)
	 *
     * @return bool true si la chaîne est un UUID valide de la version spécifiée
     */
    public static function isValidVersion(string $value, int $version): bool
    {
        if (!isset(self::UUID_VERSION[$version])) {
            throw new InvalidArgumentException(sprintf(
                'Version UUID invalide : %s. Versions supportées : %s',
                $version,
                implode(', ', array_keys(self::UUID_VERSION))
            ));
        }

        $versionHex = dechex($version);
        $pattern = sprintf(self::UUID_VERSION_PATTERN, $versionHex);

        return preg_match($pattern, $value) === 1;
    }

    /**
     * Extrait la version d'un UUID
     *
     * @param string $uuid UUID à analyser
	 *
     * @return int Version de l'UUID (0 si non valide ou version inconnue)
     */
    public static function getVersion(string $uuid): int
    {
        if (! static::isValid($uuid)) {
            return 0;
        }

        $parts = explode('-', $uuid);
        if (count($parts) !== 5) {
            return 0;
        }

        $versionHex = $parts[2][0] ?? '0';
        $version = hexdec($versionHex);

        return isset(self::UUID_VERSION[$version]) ? $version : 0;
    }

    /**
	 * Extrait la variante d'un UUID
	 *
	 * @param string $uuid UUID à analyser
	 *
	 * @return string Variante de l'UUID ('unknown', 'ncs', 'rfc4122', 'microsoft', 'future')
	 */
	public static function getVariant(string $uuid): string
	{
		if (! static::isValid($uuid)) {
			return 'unknown';
		}

		$parts = explode('-', $uuid);
		if (count($parts) !== 5) {
			return 'unknown';
		}

		// Prendre les deux premiers caractères hexadécimaux du segment 3 (octet clock_seq_hi)
		$clockSeqHiHex = substr($parts[3], 0, 2);
		$clockSeqHi = hexdec($clockSeqHiHex);

		// Variant bits: positions 7-6 (en partant du MSB)
		// Note: En PHP, les opérations bitwise fonctionnent sur les entiers

		// Masque 0xC0 = 11000000 (bits 7 et 6)
		// Masque 0xE0 = 11100000 (bits 7, 6 et 5)

		if (($clockSeqHi & 0x80) === 0x00) {
			return 'ncs'; // NCS (bit 7 = 0)
		} elseif (($clockSeqHi & 0xC0) === 0x80) {
			return 'rfc4122'; // RFC 4122 (bits 7-6 = 10)
		} elseif (($clockSeqHi & 0xE0) === 0xC0) {
			return 'microsoft'; // Microsoft (bits 7-6 = 11, bit 5 = 0)
		} elseif (($clockSeqHi & 0xE0) === 0xE0) {
			return 'future'; // Future (bits 7-6-5 = 111)
		}

		return 'unknown';
	}

    /**
     * Vérifie si un UUID est nul (tous les bits à 0)
     *
     * @param string $uuid UUID à vérifier
	 *
     * @return bool true si l'UUID est nul
     */
    public static function isNil(string $uuid): bool
    {
        return $uuid === '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Génère un UUID nul
     *
     * @return string UUID nul
     */
    public static function nil(): string
    {
        return '00000000-0000-0000-0000-000000000000';
    }

    /**
     * Génère un UUID max (tous les bits à 1)
     *
     * @return string UUID max
     */
    public static function max(): string
    {
        return 'ffffffff-ffff-ffff-ffff-ffffffffffff';
    }

    /**
     * Compare deux UUID
     *
     * @param string $uuid1 Premier UUID
     * @param string $uuid2 Deuxième UUID
	 *
     * @return int -1 si $uuid1 < $uuid2, 0 si égaux, 1 si $uuid1 > $uuid2
     */
    public static function compare(string $uuid1, string $uuid2): int
    {
        // Normalise les UUID (minuscules, sans tirets)
        $norm1 = strtolower(str_replace('-', '', $uuid1));
        $norm2 = strtolower(str_replace('-', '', $uuid2));

        return strcmp($norm1, $norm2);
    }

    /**
     * Convertit un UUID en représentation binaire (16 octets)
     *
     * @param string $uuid UUID à convertir
	 *
     * @return string Représentation binaire
	 *
     * @throws InvalidArgumentException Si l'UUID n'est pas valide
     */
    public static function toBinary(string $uuid): string
    {
        if (!static::isValid($uuid)) {
            throw new InvalidArgumentException('UUID invalide');
        }

        $hex = str_replace('-', '', $uuid);

        // Conversion hexadécimale vers binaire
        $binary = '';
        for ($i = 0; $i < strlen($hex); $i += 2) {
            $binary .= chr(hexdec($hex[$i] . $hex[$i + 1]));
        }

        return $binary;
    }

    /**
     * Convertit une représentation binaire en UUID
     *
     * @param string $binary Représentation binaire (16 octets)
	 *
     * @return string UUID formaté
	 *
     * @throws InvalidArgumentException Si la donnée binaire n'est pas de 16 octets
     */
    public static function fromBinary(string $binary): string
    {
        if (strlen($binary) !== 16) {
            throw new InvalidArgumentException('La donnée binaire doit faire exactement 16 octets');
        }

        $hex = bin2hex($binary);

        // Formatte en UUID
        return substr($hex, 0, 8) . '-' .
               substr($hex, 8, 4) . '-' .
               substr($hex, 12, 4) . '-' .
               substr($hex, 16, 4) . '-' .
               substr($hex, 20, 12);
    }

    /**
     * Génère un UUID déterministe à partir d'une chaîne
     *
     * Alias pour v5 avec namespace DNS par défaut.
     *
     * @param string $name Nom à hacher
     * @param string $namespace Namespace (par défaut: NAMESPACE_DNS)
	 *
     * @return string UUID version 5
     */
    public static function fromString(string $name, string $namespace = self::NAMESPACE_DNS): string
    {
        return static::v5($namespace, $name);
    }

    /**
     * Génère un UUID à partir d'un entier (128 bits)
     *
     * @param string $int Entier sous forme de chaîne (peut être très grand)
	 *
     * @return string UUID correspondant
	 *
     * @throws RuntimeException Si l'extension GMP n'est pas installée
     * @throws InvalidArgumentException Si l'entier est trop grand
     */
    public static function fromInteger(string $int): string
    {
		if (! extension_loaded('gmp')) {
			throw new RuntimeException('Extension GMP requis');
		}

        // Convertit l'entier en hexadécimal
        $hex = gmp_strval(gmp_init($int, 10), 16);

        // Pad à 32 caractères hexadécimaux
        $hex = str_pad($hex, 32, '0', STR_PAD_LEFT);

        // Si trop long, on tronque
        if (strlen($hex) > 32) {
            $hex = substr($hex, -32);
        }

        // Formatte en UUID
        return substr($hex, 0, 8) . '-' .
               substr($hex, 8, 4) . '-' .
               substr($hex, 12, 4) . '-' .
               substr($hex, 16, 4) . '-' .
               substr($hex, 20, 12);
    }

    /**
     * Convertit un UUID en entier (128 bits)
     *
     * @param string $uuid UUID à convertir
	 *
     * @return string Entier sous forme de chaîne
	 *
     * @throws RuntimeException Si l'extension GMP n'est pas installée
     * @throws InvalidArgumentException Si l'UUID n'est pas valide
     */
    public static function toInteger(string $uuid): string
    {
        if (! extension_loaded('gmp')) {
			throw new RuntimeException('Extension GMP requis');
		}

        if (! static::isValid($uuid)) {
            throw new InvalidArgumentException('UUID invalide');
        }

        $hex = str_replace('-', '', $uuid);

        return gmp_strval(gmp_init($hex, 16), 10);
    }

    /**
     * Génère un UUID séquentiel (pseudo-ordonné)
     *
     * Utile pour le tri dans les bases de données.
     *
     * @return string UUID pseudo-ordonné
     */
    public static function sequential(): string
    {
        // Préfixe basé sur le temps (6 bytes)
        $time = microtime(true);
        $prefix = pack('J', (int) ($time * 1000000)); // Microsecondes

        // Partie aléatoire (10 bytes)
        $suffix = random_bytes(10);

        // Combine et formate en UUID v4
        $bytes = substr($prefix, 2) . $suffix; // 16 bytes au total
        $bytes[6] = chr(ord($bytes[6]) & 0x0F | 0x40); // Version 4
        $bytes[8] = chr(ord($bytes[8]) & 0x3F | 0x80); // Variant RFC 4122

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }
}
