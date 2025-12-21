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

use Exception;
use Firebase\JWT\JWT as Firebase;
use Firebase\JWT\Key;
use Throwable;

/**
 * Utilitaires de manipulation de token JWT (JSON Web Token)
 */
class Jwt
{
    /**
     * Configuration JWT
     *
     * @var array
     */
    private $config;

    /**
     * Instance singleton de la classe
     *
     * @var self|null
     */
    private static $_instance;

    /**
     * Constructeur
     *
     * @param array $config Configuration JWT
     */
    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'key'       => 'blitz-php-jwt-key',
            'exp_time'  => 5,                        // 5 minutes
            'merge'     => false,
            'algorithm' => 'HS256',
            'base_url'  => Helpers::findBaseUrl(),
        ], $config);

        $this->config['public_key'] ??= $this->config['key'];
    }

    /**
     * Récupère l'instance singleton
     *
     * @param array $config Configuration JWT (si création de nouvelle instance)
     *
     * @return self
     */
    public static function instance(array $config = []): self
    {
        if (null === static::$_instance) {
            static::$_instance = new static($config);
        }

        return static::$_instance;
    }

    /**
     * Retourne les configurations JWT appropriées
     *
     * @param array $config Configuration additionnelle
     *
     * @return object Configuration JWT sous forme d'objet
     */
    private static function config(array $config = []): object
    {
        return (object) array_merge(self::instance()->config, $config);
    }

    /**
     * Génère un token JWT d'authentification
     *
     * @param array $data   Données à inclure dans le token
     * @param array $config Configuration spécifique pour cet encodage
     *
     * @return string Token JWT encodé
     *
     * @throws Exception Si l'encodage échoue
     */
    public static function encode(array $data = [], array $config = []): string
    {
        $config = self::config($config);

        $payload = [
            'iat' => time(),
            'iss' => $config->base_url,
            'exp' => time() + (60 * $config->exp_time),
        ];

        if ($config->merge !== true) {
            $payload['data'] = $data;
        } else {
            $payload = array_merge($payload, $data);
        }

        try {
            return Firebase::encode($payload, $config->key, $config->algorithm);
        } catch (Throwable $e) {
            throw new Exception('JWT Exception : ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère le payload du token d'entrée
     *
     * @param bool  $full   Si true, retourne le payload complet avec les métadonnées
     * @param array $config Configuration spécifique pour la décodage
     *
     * @return mixed Données du payload
     *
     * @throws Exception Si le token n'est pas trouvé ou est invalide
     */
    public static function payload(bool $full = false, array $config = [])
    {
        $token  = self::getToken();
        $config = self::config($config);

        if (empty($token)) {
            throw new Exception('Access token not found.');
        }

        $payload = self::decode($token, (array) $config);

        $returned = $payload;
        if ($config->merge !== true) {
            $returned = $payload->data ?? $payload;
        }

        if (! $full) {
            unset($returned->iat, $returned->iss, $returned->exp);
        }

        return $returned;
    }

    /**
     * Décode un token JWT d'authentification
     *
     * @param string $token  Token JWT à décoder
     * @param array  $config Configuration spécifique pour le décodage
     *
     * @return object Payload décodé
     *
     * @throws Exception Si le décodage échoue
     */
    public static function decode(string $token, array $config = []): object
    {
        $config = self::config($config);

        try {
            return Firebase::decode(
                $token,
                new Key($config->public_key, $config->algorithm)
            );
        } catch (Throwable $e) {
            throw new Exception('JWT Exception : ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Récupère le token d'accès à partir des headers HTTP
     *
     * @return string|null Token JWT ou null si non trouvé
     */
    public static function getToken(): ?string
    {
        $authorization = self::getAuthorization();

        if (! empty($authorization) && preg_match('/Bearer\s(\S+)/', $authorization, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Récupère le header "Authorization" de la requête HTTP
     *
     * @return string|null Contenu du header Authorization ou null si non présent
     */
    public static function getAuthorization(): ?string
    {
        if (isset($_SERVER['Authorization'])) {
            return trim($_SERVER['Authorization']);
        }

        if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
            // Nginx ou fast CGI
            return trim($_SERVER['HTTP_AUTHORIZATION']);
        }

        if (function_exists('apache_request_headers')) {
            $requestHeaders = apache_request_headers();

            $requestHeaders = array_combine(
                array_map('ucwords', array_keys($requestHeaders)),
                array_values(($requestHeaders))
            );

            if (isset($requestHeaders['Authorization'])) {
                return trim($requestHeaders['Authorization']);
            }
        }

        return null;
    }
}
