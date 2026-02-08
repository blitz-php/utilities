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

/**
 * Pluriel et singulier des mots anglais.
 * L'inflector permet de mettre au pluriel et au singulier les noms anglais.
 *
 * @credit 		<a href="https://cakephp.org">CakePHP - Cake\Utilities\Inflector</a>
 */
class Inflector
{
    /**
     * Règles de pluriel
     *
     * @var array<string, string>
     */
    protected static array $_plural = [
        '/(s)tatus$/i'                                                       => '\1tatuses',
        '/(quiz)$/i'                                                         => '\1zes',
        '/^(ox)$/i'                                                          => '\1\2en',
        '/([m|l])ouse$/i'                                                    => '\1ice',
        '/(matr|vert|ind)(ix|ex)$/i'                                         => '\1ices',
        '/(x|ch|ss|sh)$/i'                                                   => '\1es',
        '/([^aeiouy]|qu)y$/i'                                                => '\1ies',
        '/(hive)$/i'                                                         => '\1s',
        '/(chef)$/i'                                                         => '\1s',
        '/(?:([^f])fe|([lre])f)$/i'                                          => '\1\2ves',
        '/sis$/i'                                                            => 'ses',
        '/([ti])um$/i'                                                       => '\1a',
        '/(p)erson$/i'                                                       => '\1eople',
        '/(?<!u)(m)an$/i'                                                    => '\1en',
        '/(c)hild$/i'                                                        => '\1hildren',
        '/(buffal|tomat)o$/i'                                                => '\1\2oes',
        '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin)us$/i' => '\1i',
        '/us$/i'                                                             => 'uses',
        '/(alias)$/i'                                                        => '\1es',
        '/(ax|cris|test)is$/i'                                               => '\1es',
        '/(m)an$/'                                                           => '\1en',        // man, woman, spokesman
        '/(bu|campu)s$/'                                                     => '\1\2ses',     // bus, campus
        '/(octop)us$/'                                                       => '\1i',         // octopus
        '/s$/'                                                               => 's',
        '/^$/'                                                               => '',
        '/$/'                                                                => 's',
    ];

    /**
     * Règles de singulier
     *
     * @var array<string, string>
     */
    protected static array $_singular = [
        '/(s)tatuses$/i'                                                          => '\1\2tatus',
        '/^(.*)(menu)s$/i'                                                        => '\1\2',
        '/(quiz)zes$/i'                                                           => '\\1',
        '/(matr)ices$/i'                                                          => '\1ix',
        '/(vert|ind)ices$/i'                                                      => '\1ex',
        '/^(ox)en/i'                                                              => '\1',
        '/(alias)(es)*$/i'                                                        => '\1',
        '/(alumn|bacill|cact|foc|fung|nucle|radi|stimul|syllab|termin|viri?)i$/i' => '\1us',
        '/([ftw]ax)es/i'                                                          => '\1',
        '/(cris|ax|test)es$/i'                                                    => '\1is',
        '/(shoe)s$/i'                                                             => '\1',
        '/(o)es$/i'                                                               => '\1',
        '/ouses$/'                                                                => 'ouse',
        '/([^a])uses$/'                                                           => '\1us',
        '/([m|l])ice$/i'                                                          => '\1ouse',
        '/(x|ch|ss|sh)es$/i'                                                      => '\1',
        '/(m)ovies$/i'                                                            => '\1\2ovie',
        '/(s)eries$/i'                                                            => '\1\2eries',
        '/([^aeiouy]|qu)ies$/i'                                                   => '\1y',
        '/(tive)s$/i'                                                             => '\1',
        '/(hive)s$/i'                                                             => '\1',
        '/(drive)s$/i'                                                            => '\1',
        '/([le])ves$/i'                                                           => '\1f',
        '/([^rfoa])ves$/i'                                                        => '\1fe',
        '/(^analy)ses$/i'                                                         => '\1sis',
        '/(analy|diagno|^ba|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/i'             => '\1\2sis',
        '/([ti])a$/i'                                                             => '\1um',
        '/(p)eople$/i'                                                            => '\1\2erson',
        '/(m)en$/i'                                                               => '\1an',
        '/(c)hildren$/i'                                                          => '\1\2hild',
        '/(n)ews$/i'                                                              => '\1\2ews',
        '/eaus$/'                                                                 => 'eau',
        '/^(.*us)$/'                                                              => '\\1',
        '/s$/i'                                                                   => '',
        '/([octop|vir])i$/'                                                       => '\1us',
        '/(bus|campus)es$/'                                                       => '\1',
        '/([lr])ves$/'                                                            => '\1f',
        '/(tive)s$/'                                                              => '\1',
        '/((a)naly|(b)a|(d)iagno|(p)arenthe|(p)rogno|(s)ynop|(t)he)ses$/'         => '\1\2sis',
    ];

    /**
     * Règles irrégulières
     *
     * @var array<string, string>
     */
    protected static array $_irregular = [
        'atlas'     => 'atlases',
        'beef'      => 'beefs',
        'brief'     => 'briefs',
        'brother'   => 'brothers',
        'cafe'      => 'cafes',
        'child'     => 'children',
        'cookie'    => 'cookies',
        'corpus'    => 'corpuses',
        'cow'       => 'cows',
        'criterion' => 'criteria',
        'ganglion'  => 'ganglions',
        'genie'     => 'genies',
        'genus'     => 'genera',
        'graffito'  => 'graffiti',
        'hoof'      => 'hoofs',
        'loaf'      => 'loaves',
        'man'       => 'men',
        'money'     => 'monies',
        'mongoose'  => 'mongooses',
        'move'      => 'moves',
        'mythos'    => 'mythoi',
        'niche'     => 'niches',
        'numen'     => 'numina',
        'occiput'   => 'occiputs',
        'octopus'   => 'octopuses',
        'opus'      => 'opuses',
        'ox'        => 'oxen',
        'penis'     => 'penises',
        'person'    => 'people',
        'sex'       => 'sexes',
        'soliloquy' => 'soliloquies',
        'testis'    => 'testes',
        'trilby'    => 'trilbys',
        'turf'      => 'turfs',
        'potato'    => 'potatoes',
        'hero'      => 'heroes',
        'tooth'     => 'teeth',
        'goose'     => 'geese',
        'foot'      => 'feet',
        'foe'       => 'foes',
        'sieve'     => 'sieves',
        'cache'     => 'caches',
    ];

    /**
     * Mots qui ne doivent pas être infléchis
     *
     * @var list<string>
     */
    protected static array $_uninflected = [
        '.*[nrlm]ese', '.*data', '.*deer', '.*fish', '.*measles', '.*ois',
        '.*pox', '.*sheep', 'people', 'feedback', 'stadia', '.*?media',
        'chassis', 'clippers', 'debris', 'diabetes', 'equipment', 'gallows',
        'graffiti', 'headquarters', 'information', 'innings', 'news', 'nexus',
        'pokemon', 'proceedings', 'research', 'sea[- ]bass', 'series', 'species', 'weather',
    ];

    /**
     * Table de correspondance par défaut des caractères accentués et spéciaux vers les caractères ASCII
     *
     * @var array<string, string>
     */
    protected static array $_transliteration = [
        'ä' => 'ae',
        'æ' => 'ae',
        'ǽ' => 'ae',
        'ö' => 'oe',
        'œ' => 'oe',
        'ü' => 'ue',
        'Ä' => 'Ae',
        'Ü' => 'Ue',
        'Ö' => 'Oe',
        'À' => 'A',
        'Á' => 'A',
        'Â' => 'A',
        'Ã' => 'A',
        'Å' => 'A',
        'Ǻ' => 'A',
        'Ā' => 'A',
        'Ă' => 'A',
        'Ą' => 'A',
        'Ǎ' => 'A',
        'à' => 'a',
        'á' => 'a',
        'â' => 'a',
        'ã' => 'a',
        'å' => 'a',
        'ǻ' => 'a',
        'ā' => 'a',
        'ă' => 'a',
        'ą' => 'a',
        'ǎ' => 'a',
        'ª' => 'a',
        'Ç' => 'C',
        'Ć' => 'C',
        'Ĉ' => 'C',
        'Ċ' => 'C',
        'Č' => 'C',
        'ç' => 'c',
        'ć' => 'c',
        'ĉ' => 'c',
        'ċ' => 'c',
        'č' => 'c',
        'Ð' => 'D',
        'Ď' => 'D',
        'Đ' => 'D',
        'ð' => 'd',
        'ď' => 'd',
        'đ' => 'd',
        'È' => 'E',
        'É' => 'E',
        'Ê' => 'E',
        'Ë' => 'E',
        'Ē' => 'E',
        'Ĕ' => 'E',
        'Ė' => 'E',
        'Ę' => 'E',
        'Ě' => 'E',
        'è' => 'e',
        'é' => 'e',
        'ê' => 'e',
        'ë' => 'e',
        'ē' => 'e',
        'ĕ' => 'e',
        'ė' => 'e',
        'ę' => 'e',
        'ě' => 'e',
        'Ĝ' => 'G',
        'Ğ' => 'G',
        'Ġ' => 'G',
        'Ģ' => 'G',
        'Ґ' => 'G',
        'ĝ' => 'g',
        'ğ' => 'g',
        'ġ' => 'g',
        'ģ' => 'g',
        'ґ' => 'g',
        'Ĥ' => 'H',
        'Ħ' => 'H',
        'ĥ' => 'h',
        'ħ' => 'h',
        'І' => 'I',
        'Ì' => 'I',
        'Í' => 'I',
        'Î' => 'I',
        'Ї' => 'Yi',
        'Ï' => 'I',
        'Ĩ' => 'I',
        'Ī' => 'I',
        'Ĭ' => 'I',
        'Ǐ' => 'I',
        'Į' => 'I',
        'İ' => 'I',
        'і' => 'i',
        'ì' => 'i',
        'í' => 'i',
        'î' => 'i',
        'ï' => 'i',
        'ї' => 'yi',
        'ĩ' => 'i',
        'ī' => 'i',
        'ĭ' => 'i',
        'ǐ' => 'i',
        'į' => 'i',
        'ı' => 'i',
        'Ĵ' => 'J',
        'ĵ' => 'j',
        'Ķ' => 'K',
        'ķ' => 'k',
        'Ĺ' => 'L',
        'Ļ' => 'L',
        'Ľ' => 'L',
        'Ŀ' => 'L',
        'Ł' => 'L',
        'ĺ' => 'l',
        'ļ' => 'l',
        'ľ' => 'l',
        'ŀ' => 'l',
        'ł' => 'l',
        'Ñ' => 'N',
        'Ń' => 'N',
        'Ņ' => 'N',
        'Ň' => 'N',
        'ñ' => 'n',
        'ń' => 'n',
        'ņ' => 'n',
        'ň' => 'n',
        'ŉ' => 'n',
        'Ò' => 'O',
        'Ó' => 'O',
        'Ô' => 'O',
        'Õ' => 'O',
        'Ō' => 'O',
        'Ŏ' => 'O',
        'Ǒ' => 'O',
        'Ő' => 'O',
        'Ơ' => 'O',
        'Ø' => 'O',
        'Ǿ' => 'O',
        'ò' => 'o',
        'ó' => 'o',
        'ô' => 'o',
        'õ' => 'o',
        'ō' => 'o',
        'ŏ' => 'o',
        'ǒ' => 'o',
        'ő' => 'o',
        'ơ' => 'o',
        'ø' => 'o',
        'ǿ' => 'o',
        'º' => 'o',
        'Ŕ' => 'R',
        'Ŗ' => 'R',
        'Ř' => 'R',
        'ŕ' => 'r',
        'ŗ' => 'r',
        'ř' => 'r',
        'Ś' => 'S',
        'Ŝ' => 'S',
        'Ş' => 'S',
        'Ș' => 'S',
        'Š' => 'S',
        'ẞ' => 'SS',
        'ś' => 's',
        'ŝ' => 's',
        'ş' => 's',
        'ș' => 's',
        'š' => 's',
        'ſ' => 's',
        'Ţ' => 'T',
        'Ț' => 'T',
        'Ť' => 'T',
        'Ŧ' => 'T',
        'ţ' => 't',
        'ț' => 't',
        'ť' => 't',
        'ŧ' => 't',
        'Ù' => 'U',
        'Ú' => 'U',
        'Û' => 'U',
        'Ũ' => 'U',
        'Ū' => 'U',
        'Ŭ' => 'U',
        'Ů' => 'U',
        'Ű' => 'U',
        'Ų' => 'U',
        'Ư' => 'U',
        'Ǔ' => 'U',
        'Ǖ' => 'U',
        'Ǘ' => 'U',
        'Ǚ' => 'U',
        'Ǜ' => 'U',
        'ù' => 'u',
        'ú' => 'u',
        'û' => 'u',
        'ũ' => 'u',
        'ū' => 'u',
        'ŭ' => 'u',
        'ů' => 'u',
        'ű' => 'u',
        'ų' => 'u',
        'ư' => 'u',
        'ǔ' => 'u',
        'ǖ' => 'u',
        'ǘ' => 'u',
        'ǚ' => 'u',
        'ǜ' => 'u',
        'Ý' => 'Y',
        'Ÿ' => 'Y',
        'Ŷ' => 'Y',
        'ý' => 'y',
        'ÿ' => 'y',
        'ŷ' => 'y',
        'Ŵ' => 'W',
        'ŵ' => 'w',
        'Ź' => 'Z',
        'Ż' => 'Z',
        'Ž' => 'Z',
        'ź' => 'z',
        'ż' => 'z',
        'ž' => 'z',
        'Æ' => 'AE',
        'Ǽ' => 'AE',
        'ß' => 'ss',
        'Ĳ' => 'IJ',
        'ĳ' => 'ij',
        'Œ' => 'OE',
        'ƒ' => 'f',
        'Þ' => 'TH',
        'þ' => 'th',
        'Є' => 'Ye',
        'є' => 'ye',
    ];

    /**
     * Configuration de langue active
     */
    protected static string $_language = 'en';

    /**
     * Règles de pluriel par langue
     *
     * @var array<string, array<string, string>>
     */
    protected static array $_pluralRules = [
        'en' => [], // Utilise les règles par défaut $_plural
        'fr' => [
            '/(s|x|z)$/i' => '\\1',  // Garde la terminaison, donc pas de changement

            // Règles générales françaises
            '/([^aeiou])eau$/i' => '\1eaux',          // château → châteaux
            '/([^aeiou])eu$/i'  => '\1eux',           // jeu → jeux
            '/(au|eu|eau)$/i'   => '\1x',             // tuyau → tuyaux
            '/(ou)$/i'          => '\1s',             // bijou → bijous (exception: bijou → bijoux)
            '/al$/i'            => 'aux',             // cheval → chevaux
            '/ail$/i'           => 'aux',             // travail → travaux
            // '/s$/i'             => 's',               // Déjà au pluriel
            // '/x$/i'             => 'x',               // Déjà au pluriel
            // '/z$/i'             => 'z',               // Déjà au pluriel
            '/([aeiou])l$/i' => '\1ls',            // détail → détails
            '/([^sxz])$/i'   => '\1s',             // Règle générale: ajout de 's'
        ],
    ];

    /**
     * Règles de singulier par langue
     *
     * @var array<string, array<string, string>>
     */
    protected static array $_singularRules = [
        'en' => [], // Utilise les règles par défaut $_singular
        'fr' => [
            // Règles générales françaises
            '/([^aeiou])eaux$/i' => '\1eau',          // châteaux → château
            '/([^aeiou])eux$/i'  => '\1eu',           // jeux → jeu
            '/aux$/i'            => 'al',             // chevaux → cheval
            '/(au|eu|eau)x$/i'   => '\1',             // tuyaux → tuyau
            '/(ou)s$/i'          => '\1',             // bijous → bijou
            '/ails$/i'           => 'ail',            // travaux → travail
            '/s$/i'              => '',               // Retrait du 's'
            '/x$/i'              => '',               // Retrait du 'x'
            '/z$/i'              => '',               // Retrait du 'z'
            '/([aeiou])ls$/i'    => '\1l',            // détails → détail
        ],
    ];

    /**
     * Mots irréguliers par langue
     *
     * @var array<string, array<string, string>>
     */
    protected static array $_irregularRules = [
        'en' => [], // Utilise $_irregular par défaut
        'fr' => [
            // Irréguliers français
            'bijou'        => 'bijoux',
            'caillou'      => 'cailloux',
            'chou'         => 'choux',
            'genou'        => 'genoux',
            'hibou'        => 'hiboux',
            'joujou'       => 'joujoux',
            'pou'          => 'poux',
            'madame'       => 'mesdames',
            'mademoiselle' => 'mesdemoiselles',
            'monsieur'     => 'messieurs',
            'œil'          => 'yeux',
            'oeil'         => 'yeux',
            'ciel'         => 'cieux',
            'aïeul'        => 'aïeux',
            'travail'      => 'travaux',
            'corail'       => 'coraux',
            'émail'        => 'émaux',
            'vitrail'      => 'vitraux',
            'bail'         => 'baux',
            'soupirail'    => 'soupiraux',
        ],
    ];

    /**
     * Mots invariables par langue
     *
     * @var array<string, list<string>>
     */
    protected static array $_uninflectedRules = [
        'en' => [], // Utilise $_uninflected par défaut
        'fr' => [
            // Mots invariables français
            /* retirer car problematique
            '.*ois',
            '.*ais',           // français, anglais, etc.
            '.*z',            // gaz, nez, etc.
            '.*s',            // déjà au pluriel
            '.*x',            // déjà au pluriel
            '.*eau',          // déjà au pluriel (eau)
            '.*ieu',          // lieu, dieu
            */

            // Mots avec pluriels généralement privilégiés
            'archives', 'fiançailles', 'intempéries', 'obsèques', 'vacances',

            'acces', 'acné', 'agenda', 'alcool', 'amour', 'ananas', 'argent', 'art', 'as', 'atlas', 'autobus', 'automne', 'avant-bras', 'avis',
            'bas', 'bois', 'bonus', 'box', 'bras', 'bronze', 'bus',
            'cactus', 'caoutchouc', 'car', 'cas', 'cassis', 'chaos', 'châssis', 'chassis', 'choix', 'corps', 'cours', 'croix', 'cuir',
            'début', 'délai', 'dés', 'discours',
            'échec', 'ennui', 'envers',
            'fait', 'faux', 'fax', 'feu', 'fils', 'foi', 'fois', 'frais',
            'gars', 'gaz', 'gentilhomme', 'glas', 'gris', 'gros',
            'huis', 'humour',
            'impôt', 'index', 'iris',
            'jeans', 'jus',
            'las', 'lilas', 'lynx', 'lys',
            'mars', 'mois', 'myosotis',
            'nez', 'noix', 'nord', 'nœud',
            'œil', 'œuf', 'or', 'os', 'ouest',
            'pais', 'palais', 'paradis', 'parc', 'pardon', 'parfum', 'pas', 'pays', 'poids', 'pois', 'pomme de terre', 'prix', 'proces', 'progrès', 'puis', 'pus',
            'quart', 'quiz', 'quinze',
            'ras', 'relais', 'repas', 'revers', 'rez-de-chaussée', 'rhinocéros', 'riz', 'rubis',
            'sang', 'sans', 'sec', 'secret', 'sel', 'selle', 'sens', 'shorts', 'silex', 'six', 'soixante', 'sol', 'souris', 'sous', 'sueur', 'sur', 'sûr',
            'tapis', 'tas', 'temps', 'tennis', 'ton', 'tort', 'tôt', 'tout', 'tribu', 'trois', 'trop', 'truc',
            'univers',
            'velours', 'vers', 'vice', 'virus', 'voix',
            'yeux',
        ],
    ];

    /**
     * Cache des méthodes.
     *
     * @var array<string, array<string, mixed>>
     */
    protected static array $_cache = [];

    /**
     * État initial de l'Inflector pour la réinitialisation.
     *
     * @var array<string, mixed>
     */
    protected static array $_initialState = [];

    /**
     * Définit la langue active pour l'inflection
     *
     * @param string $language Code de langue (ex: 'en', 'fr')
     */
    public static function setLanguage(string $language): void
    {
        static::$_language = $language;
        static::$_cache    = []; // Vide le cache lors du changement de langue
    }

    /**
     * Récupère la langue active
     *
     * @return string Code de langue actuelle
     */
    public static function getLanguage(): string
    {
        return static::$_language;
    }

    /**
     * Ajoute des règles d'inflection personnalisées pour une langue spécifique
     *
     * @param string $language Code de langue
     * @param string $type     Type de règle ('plural', 'singular', 'irregular', 'uninflected')
     * @param array  $rules    Tableau de règles
     * @param bool   $reset    Si true, remplace toutes les règles existantes
     */
    public static function addLanguageRules(string $language, string $type, array $rules, bool $reset = false): void
    {
        $var = '_' . $type . 'Rules';

        if (! isset(static::${$var}[$language])) {
            static::${$var}[$language] = [];
        }

        if ($reset) {
            static::${$var}[$language] = $rules;
        } else {
            static::${$var}[$language] = array_merge(static::${$var}[$language], $rules);
        }

        static::$_cache = []; // Vide le cache
    }

    /**
     * Récupère les règles pour la langue active
     *
     * @param string $type Type de règle
     *
     * @return array Tableau de règles
     */
    protected static function getRules(string $type): array
    {
        $rulesVar   = '_' . $type . 'Rules';
        $defaultVar = '_' . $type;

        // Récupère les règles pour la langue active
        $languageRules = static::${$rulesVar}[static::$_language] ?? [];

        // Si la langue n'a pas de règles spécifiques, utilise les règles par défaut
        if (empty($languageRules) && $type !== 'irregular' && $type !== 'uninflected') {
            return static::${$defaultVar};
        }

        return $languageRules;
    }

    /**
     * Récupère les mots irréguliers pour la langue active
     *
     * @return array Tableau de mots irréguliers
     */
    protected static function getIrregular(): array
    {
        $languageIrregular = static::$_irregularRules[static::$_language] ?? [];

        // Combine avec les irréguliers anglais si nécessaire
        if (static::$_language !== 'en' && ! empty(static::$_irregular)) {
            return array_merge(static::$_irregular, $languageIrregular);
        }

        return ! empty($languageIrregular) ? $languageIrregular : static::$_irregular;
    }

    /**
     * Récupère les mots invariables pour la langue active
     *
     * @return array Tableau de mots invariables
     */
    protected static function getUninflected(): array
    {
        $languageUninflected = static::$_uninflectedRules[static::$_language] ?? [];

        // Combine avec les invariables anglais si nécessaire
        if (static::$_language !== 'en' && ! empty(static::$_uninflected)) {
            return array_merge(static::$_uninflected, $languageUninflected);
        }

        return ! empty($languageUninflected) ? $languageUninflected : static::$_uninflected;
    }

    /**
     * Met en cache les valeurs infléchies et les retourne si déjà disponibles
     *
     * @param string      $type  Type d'inflection
     * @param string      $key   Valeur originale
     * @param bool|string $value Valeur infléchie
     *
     * @return false|string Valeur infléchie en cas de cache hit ou false en cas de cache miss
     */
    protected static function _cache(string $type, string $key, $value = false)
    {
        $key  = '_' . $key;
        $type = '_' . $type;
        if ($value !== false) {
            static::$_cache[$type][$key] = $value;

            return $value;
        }
        if (! isset(static::$_cache[$type][$key])) {
            return false;
        }

        return static::$_cache[$type][$key];
    }

    /**
     * Vide les caches de valeurs infléchies de l'Inflector. Et réinitialise les règles
     * d'inflection aux valeurs initiales.
     */
    public static function reset(): void
    {
        if (empty(static::$_initialState)) {
            static::$_initialState = get_class_vars(__CLASS__);

            return;
        }

        foreach (static::$_initialState as $key => $val) {
            if ($key !== '_initialState') {
                static::${$key} = $val;
            }
        }
    }

    /**
     * Ajoute des règles d'inflection personnalisées, de type 'plural', 'singular',
     * 'uninflected', 'irregular' ou 'transliteration'.
     *
     * ### Utilisation :
     *
     * ```
     * Inflector::rules('plural', ['/^(inflect)or$/i' => '\1ables']);
     * Inflector::rules('irregular', ['red' => 'redlings']);
     * Inflector::rules('uninflected', ['dontinflectme']);
     * Inflector::rules('transliteration', ['/å/' => 'aa']);
     * ```
     *
     * @param string $type  Le type d'inflection, soit 'plural', 'singular',
     *                      'uninflected' ou 'transliteration'.
     * @param array  $rules Tableau de règles à ajouter.
     * @param bool   $reset Si true, supprimera les inflections par défaut pour toutes
     *                      les nouvelles règles définies dans $rules.
     */
    public static function rules(string $type, array $rules, bool $reset = false): void
    {
        $var = '_' . $type;

        if ($reset) {
            static::${$var} = $rules;
        } elseif ($type === 'uninflected') {
            static::$_uninflected = array_merge(
                $rules,
                static::$_uninflected
            );
        } else {
            static::${$var} = $rules + static::${$var};
        }

        static::$_cache = [];
    }

    /**
     * Retourne $word au pluriel.
     *
     * @param string $word Mot au singulier
     *
     * @return string Mot au pluriel
     */
    public static function pluralize(string $word): string
    {
        // Clé de cache spécifique à la langue
        $cacheKey = 'pluralize_' . static::$_language;

        if (isset(static::$_cache[$cacheKey][$word])) {
            return static::$_cache[$cacheKey][$word];
        }

        $irregular = static::getIrregular();

        if (! isset(static::$_cache['irregular'][$cacheKey])) {
            static::$_cache['irregular'][$cacheKey] = '(?:' . implode('|', array_keys($irregular)) . ')';
        }

        // Vérifie les mots irréguliers
        if (preg_match('/(.*?(?:\\b|_))(' . static::$_cache['irregular'][$cacheKey] . ')$/i', $word, $regs)) {
            $result = $regs[1];
            $value  = $irregular[strtolower($regs[2])];

            if ($value[0] !== $regs[2][0]) { // cas de oeil => yeux
                $result .= $value;
            } else {
                $result .= substr($regs[2], 0, 1) . substr($value, 1);
            }

            static::$_cache[$cacheKey][$word] = $result;

            return $result;
        }

        $uninflected = static::getUninflected();

        if (! isset(static::$_cache['uninflected'][$cacheKey])) {
            static::$_cache['uninflected'][$cacheKey] = '(?:' . implode('|', $uninflected) . ')';
        }

        // Vérifie les mots invariables
        if (preg_match('/^(' . static::$_cache['uninflected'][$cacheKey] . ')$/i', $word, $regs)) {
            static::$_cache[$cacheKey][$word] = $word;

            return $word;
        }

        // Applique les règles de pluriel
        $pluralRules = static::getRules('plural');

        foreach ($pluralRules as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                $result                           = preg_replace($rule, $replacement, $word);
                static::$_cache[$cacheKey][$word] = $result;

                return $result;
            }
        }

        // Règle par défaut : ajout de 's'
        if (static::$_language === 'fr') {
            $result                           = $word . 's';
            static::$_cache[$cacheKey][$word] = $result;

            return $result;
        }

        static::$_cache[$cacheKey][$word] = $word;

        return $word;
    }

    /**
     * Retourne $word au singulier.
     *
     * @param string $word Mot au pluriel
     *
     * @return string Mot au singulier
     */
    public static function singularize(string $word): string
    {
        // Clé de cache spécifique à la langue
        $cacheKey = 'singularize_' . static::$_language;

        if (isset(static::$_cache[$cacheKey][$word])) {
            return static::$_cache[$cacheKey][$word];
        }

        $irregular = static::getIrregular();

        if (! isset(static::$_cache['irregular_singular'][$cacheKey])) {
            static::$_cache['irregular_singular'][$cacheKey] = '(?:' . implode('|', $irregular) . ')';
        }

        // Vérifie les mots irréguliers (inverse)
        if (preg_match('/(.*?(?:\\b|_))(' . static::$_cache['irregular_singular'][$cacheKey] . ')$/i', $word, $regs)) {
            $singular = array_search(strtolower($regs[2]), $irregular, true);
            if ($singular !== false) {
                $result = $regs[1];
                $value  = $singular;

                if ($value[0] !== $regs[2][0]) { // cas de yeux => oeil
                    $result .= $value;
                } else {
                    $result .= substr($regs[2], 0, 1) . substr($value, 1);
                }

                static::$_cache[$cacheKey][$word] = $result;

                return $result;
            }
        }

        $uninflected = static::getUninflected();

        if (! isset(static::$_cache['uninflected'][$cacheKey])) {
            static::$_cache['uninflected'][$cacheKey] = '(?:' . implode('|', $uninflected) . ')';
        }

        // Vérifie les mots invariables
        if (preg_match('/^(' . static::$_cache['uninflected'][$cacheKey] . ')$/i', $word, $regs)) {
            static::$_cache[$cacheKey][$word] = $word;

            return $word;
        }

        // Applique les règles de singulier
        $singularRules = static::getRules('singular');

        foreach ($singularRules as $rule => $replacement) {
            if (preg_match($rule, $word)) {
                $result                           = preg_replace($rule, $replacement, $word);
                static::$_cache[$cacheKey][$word] = $result;

                return $result;
            }
        }

        // Règle par défaut pour le français : retire le 's' final
        if (static::$_language === 'fr' && preg_match('/^(.*)s$/i', $word, $matches)) {
            // Vérifie que ce n'est pas un mot qui se termine par 's' au singulier
            $exceptions = ['bras', 'as', 'tas', 'cas', 'gas', 'glas', 'hélas', 'fils', 'os', 'repas', 'sens', 'avis', 'bois', 'bonus', 'box', 'bus', 'chaos', 'chassis', 'choix', 'corps', 'cours', 'croix', 'discours', 'fax', 'fois', 'frais', 'gaz', 'index', 'las', 'lynx', 'mars', 'mois', 'nez', 'prix', 'proces', 'quiz', 'ras', 'revers', 'riz', 'virus', 'voix'/* etc. */];
            if (! in_array(strtolower($word), $exceptions, true)) {
                $result                           = $matches[1];
                static::$_cache[$cacheKey][$word] = $result;

                return $result;
            }
        }

        static::$_cache[$cacheKey][$word] = $word;

        return $word;
    }

    /**
     * Retourne la chaîne lower_case_delimited_string sous forme de camelCasedString.
     *
     * @param string $string    Chaîne à caméliser
     * @param string $delimiter Le délimiteur dans la chaîne d'entrée
     *
     * @return string Chaîne camélisée commeCeci.
     */
    public static function camelize(string $string, string $delimiter = '_'): string
    {
        $converted = str_replace($delimiter, '_', $string);

        return Text::camel($converted);
    }

    /**
     * Retourne la chaîne lower_case_delimited_string sous forme de PascalCasedString.
     *
     * @param string $string Chaîne à pascaliser
     *
     * @return string Chaîne pascalisée PommeCeci.
     */
    public static function pascalize(string $string): string
    {
        return Text::pascal($string);
    }

    /**
     * Retourne la chaîne CamelCasedString sous forme de underscored_string.
     *
     * Remplace également les tirets par des underscores
     *
     * @param string $string CamelCasedString à "underscoriser"
     *
     * @return string Version avec underscores de la chaîne d'entrée
     */
    public static function underscore(string $string): string
    {
        return Text::snake($string, '_');
    }

    /**
     * Retourne la chaîne CamelCasedString sous forme de dashed-string.
     *
     * Remplace également les underscores par des tirets
     *
     * @param string $string La chaîne à transformer en tirets
     *
     * @return string Version avec tirets de la chaîne d'entrée
     */
    public static function dasherize(string $string): string
    {
        $snake = static::underscore($string);

        return str_replace('_', '-', $snake);
    }

    /**
     * Retourne la chaîne lower_case_delimited_string sous forme 'A Human Readable String'.
     * (Les underscores sont remplacés par des espaces et les mots suivants sont capitalisés.)
     *
     * @param string $string    Chaîne à humaniser
     * @param string $delimiter Le caractère à remplacer par un espace
     *
     * @return string Chaîne lisible par un humain
     */
    public static function humanize(string $string, string $delimiter = '_'): string
    {
        $cacheKey = __FUNCTION__ . $delimiter;

        $result = static::_cache($cacheKey, $string);

        if ($result === false) {
            $converted  = str_replace($delimiter, '_', $string);
            $snakeCase  = Text::snake($converted);
            $withSpaces = str_replace('_', ' ', $snakeCase);
            $result     = Text::title($withSpaces);

            static::_cache($cacheKey, $string, $result);
        }

        return $result;
    }

    /**
     * Attend une CamelCasedInputString, et produit une lower_case_delimited_string
     *
     * @param string $string    Chaîne à délimiter
     * @param string $delimiter Le caractère à utiliser comme délimiteur
     *
     * @return string Chaîne délimitée
     */
    public static function delimit(string $string, string $delimiter = '_'): string
    {
        return Text::snake($string, $delimiter);
    }

    /**
     * Retourne le nom de table correspondant pour le nom de classe $className donné. ("people" pour la classe de modèle "Person").
     *
     * @param string $className Nom de la classe pour obtenir le nom de table de base de données
     *
     * @return string Nom de la table de base de données pour la classe donnée
     */
    public static function tableize(string $className): string
    {
        $result = static::_cache(__FUNCTION__, $className);

        if ($result === false) {
            $result = static::pluralize(static::underscore($className));
            static::_cache(__FUNCTION__, $className, $result);
        }

        return $result;
    }

    /**
     * Retourne le nom de la classe de modèle ("Person" pour la table de base de données "people".) pour une table de base de données donnée.
     *
     * @param string $tableName Nom de la table de base de données pour obtenir le nom de classe
     *
     * @return string Nom de classe
     */
    public static function classify(string $tableName): string
    {
        $result = static::_cache(__FUNCTION__, $tableName);

        if ($result === false) {
            $result = static::pascalize(static::singularize($tableName));
            static::_cache(__FUNCTION__, $tableName, $result);
        }

        return $result;
    }

    /**
     * Retourne la version camelBacked d'une chaîne avec underscores.
     *
     * @param string $string Chaîne à convertir.
     *
     * @return string Sous forme de variable
     */
    public static function variable(string $string): string
    {
        $result = static::_cache(__FUNCTION__, $string);

        if ($result === false) {
            $camelized = Text::camel($string);
            $result    = lcfirst($camelized);
            static::_cache(__FUNCTION__, $string, $result);
        }

        return $result;
    }
}
