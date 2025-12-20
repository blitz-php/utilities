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

use ArrayAccess;
use BlitzPHP\Traits\Conditionable;
use BlitzPHP\Traits\Macroable;
use BlitzPHP\Traits\Support\Tappable;
use BlitzPHP\Utilities\DateTime\Date;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\Iterable\Collection;
use Closure;
use Countable;
use Exception;
use JsonSerializable;
use RuntimeException;
use Stringable as NativeStringable;

/**
 * Wrapper orienté objet pour les opérations sur les chaînes de caractères
 *
 * Fournit une API fluide et expressive pour manipuler les chaînes,
 * inspirée de l'interface Stringable de Laravel.
 *
 * @implements JsonSerializable<string>
 */
class Stringable implements JsonSerializable, ArrayAccess, NativeStringable
{
	use Conditionable, Macroable, Tappable;

    /**
     * La valeur de chaîne sous-jacente.
     */
    protected string $value;

    /**
     * Crée une nouvelle instance de la classe.
     *
     * @param string $value Valeur initiale
     */
    public function __construct(string $value = '')
    {
        $this->value = $value;
    }

    /**
     * Retourne le reste d'une chaîne après la première occurrence d'une valeur donnée.
     *
     * @param string $search Valeur à rechercher
     */
    public function after(string $search): static
    {
        return new static(Text::after($this->value, $search));
    }

    /**
     * Retourne le reste d'une chaîne après la dernière occurrence d'une valeur donnée.
     *
     * @param string $search Valeur à rechercher
     */
    public function afterLast(string $search): static
    {
        return new static(Text::afterLast($this->value, $search));
    }

    /**
     * Ajoute les valeurs données à la chaîne.
     *
     * @param string ...$values Valeurs à ajouter
     */
    public function append(string ...$values): static
    {
        return new static($this->value . implode('', $values));
    }

    /**
     * Ajoute une nouvelle ligne à la chaîne.
     *
     * @param int $count Nombre de nouvelles lignes (par défaut: 1)
     */
    public function newLine(int $count = 1): static
    {
        return $this->append(str_repeat(PHP_EOL, $count));
    }

    /**
     * Translittère une valeur UTF-8 en ASCII.
     *
     * @param string $language Langue pour les caractères spécifiques (par défaut: 'en')
     */
    public function ascii(string $language = 'en'): static
    {
        return new static(Text::ascii($this->value, $language));
    }

    /**
     * Récupère le composant de fin du chemin.
     *
     * @param string $suffix Suffixe à retirer
     */
    public function basename(string $suffix = ''): static
    {
        return new static(basename($this->value, $suffix));
    }

    /**
     * Récupère la partie d'une chaîne avant la première occurrence d'une valeur donnée.
     *
     * @param string $search Valeur à rechercher
     */
    public function before(string $search): static
    {
        return new static(Text::before($this->value, $search));
    }

    /**
     * Récupère la partie d'une chaîne avant la dernière occurrence d'une valeur donnée.
     *
     * @param string $search Valeur à rechercher
     */
    public function beforeLast(string $search): static
    {
        return new static(Text::beforeLast($this->value, $search));
    }

    /**
     * Récupère la partie d'une chaîne entre deux valeurs données.
     *
     * @param string $from Début de la sélection
     * @param string $to Fin de la sélection
     */
    public function between(string $from, string $to): static
    {
        return new static(Text::between($this->value, $from, $to));
    }

    /**
     * Récupère la plus petite partie possible d'une chaîne entre deux valeurs données.
     *
     * @param string $from Début de la sélection
     * @param string $to Fin de la sélection
     */
    public function betweenFirst(string $from, string $to): static
    {
        return new static(Text::betweenFirst($this->value, $from, $to));
    }

    /**
     * Convertit une valeur en camelCase.
     */
    public function camel(): static
    {
        return new static(Text::camel($this->value));
    }

    /**
     * Récupère le caractère à l'index spécifié.
     *
     * @param int $index Position du caractère
     */
    public function charAt(int $index): mixed
    {
        return Text::charAt($this->value, $index);
    }

    /**
     * Supprime la chaîne donnée si elle existe au début de la chaîne courante.
     *
     * @param array|string $needle Chaîne(s) à supprimer
     */
    public function chopStart(array|string $needle): static
    {
        return new static(Text::chopStart($this->value, $needle));
    }

    /**
     * Supprime la chaîne donnée si elle existe à la fin de la chaîne courante.
     *
     * @param array|string $needle Chaîne(s) à supprimer
     */
    public function chopEnd(array|string $needle): static
    {
        return new static(Text::chopEnd($this->value, $needle));
    }

    /**
     * Récupère le basename du chemin de classe.
     */
    public function classBasename(): static
    {
        return new static(Helpers::classBasename($this->value));
    }

    /**
     * Détermine si la chaîne contient une sous-chaîne donnée.
     *
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
     * @param bool $ignoreCase Ignorer la casse (par défaut: false)
     */
    public function contains(iterable|string $needles, bool $ignoreCase = false): bool
    {
        return Text::contains($this->value, $needles, $ignoreCase);
    }

    /**
     * Détermine si la chaîne contient toutes les valeurs d'un tableau.
     *
     * @param iterable<string> $needles Sous-chaînes à rechercher
     * @param bool $ignoreCase Ignorer la casse (par défaut: false)
     */
    public function containsAll(iterable $needles, bool $ignoreCase = false): bool
    {
        return Text::containsAll($this->value, $needles, $ignoreCase);
    }

    /**
     * Convertit la casse d'une chaîne.
     *
     * @param int $mode Mode de conversion (par défaut: MB_CASE_FOLD)
     * @param string|null $encoding Encodage (par défaut: 'UTF-8')
     */
    public function convertCase(int $mode = MB_CASE_FOLD, ?string $encoding = 'UTF-8'): static
    {
        return new static(Text::convertCase($this->value, $mode, $encoding));
    }

    /**
     * Remplace les instances consécutives d'un caractère donné par un seul caractère.
     *
     * @param string $character Caractère à dédupliquer (par défaut: espace)
     */
    public function deduplicate(string $character = ' ')
    {
        return new static(value: Text::deduplicate($this->value, $character));
    }

    /**
     * Récupère le chemin du répertoire parent.
     *
     * @param int $levels Nombre de niveaux à remonter (par défaut: 1)
     */
    public function dirname(int $levels = 1): static
    {
        return new static(dirname($this->value, $levels));
    }

	/**
     * Détermine si la chaîne ne contient pas une sous-chaîne donnée.
     *
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
     * @param bool $ignoreCase Ignorer la casse (par défaut: false)
     */
    public function doesntContain($needles, $ignoreCase = false)
    {
        return Text::doesntContain($this->value, $needles, $ignoreCase);
    }

    /**
     * Détermine si la chaîne ne se termine pas par une sous-chaîne donnée.
     *
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
     * @return bool true si la chaîne ne se termine pas par l'une des sous-chaînes
     */
    public function doesntEndWith($needles): bool
    {
        return Text::doesntEndWith($this->value, $needles);
    }

    /**
     * Détermine si la chaîne se termine par une sous-chaîne donnée.
     *
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
     * @return bool true si la chaîne se termine par l'une des sous-chaînes
     */
    public function endsWith(iterable|string $needles): bool
    {
        return Text::endsWith($this->value, $needles);
    }

    /**
     * Détermine si la chaîne correspond exactement à la valeur donnée.
     *
     * @param string|Stringable $value Valeur à comparer
     * @return bool true si les chaînes sont identiques
     */
    public function exactly(string|Stringable $value): bool
    {
        if ($value instanceof Stringable) {
            $value = $value->toString();
        }

        return $this->value === $value;
    }

    /**
     * Extrait un extrait de texte qui correspond à la première instance d'une phrase.
     *
     * @param string $phrase Phrase à rechercher (vide pour tronquer)
     * @param array<string, mixed> $options Options d'extrait
     * @return string|null L'extrait ou null si non trouvé
     */
    public function excerpt(string $phrase = '', array $options = []): ?string
    {
        return Text::excerpt($this->value, $phrase, $options);
    }

    /**
     * Explode la chaîne en tableau.
     *
     * @param string $delimiter Délimateur
     * @param int $limit Limite d'éléments (par défaut: PHP_INT_MAX)
     * @return Collection Collection des éléments
     */
    public function explode(string $delimiter, int $limit = PHP_INT_MAX): Collection
    {
        return new Collection(explode($delimiter, $this->value, $limit));
    }

    /**
     * Divise une chaîne en utilisant une expression régulière ou par longueur.
     *
     * @param int|string $pattern Pattern regex ou longueur de morceaux
     * @param int $limit Limite d'éléments (par défaut: -1)
     * @param int $flags Flags preg_split (par défaut: 0)
	 *
     * @return Collection<int, string> Collection des segments
     */
    public function split(int|string $pattern, int $limit = -1, int $flags = 0): Collection
    {
		if (is_int($pattern) || ctype_digit((string) $pattern)) {
            return new Collection(mb_str_split($this->value, (int) $pattern));
        }

		// Si ce n'est pas une regex valide, on l'échappe pour en faire un pattern littéral
		if (! Text::isRegex($pattern)) {
			$delimiter = array_filter(
				['/', '#', '~', '%', '|', '!', '@', '_'],
				fn(string $del) => strpos($pattern, $del) === false
			)[0] ?? '/';

			// Échapper le pattern et construire la regex
			$pattern = $delimiter . preg_quote($pattern, $delimiter) . $delimiter;
		}

		$segments = preg_split($pattern, $this->value, $limit, $flags);

        return !empty($segments) ? new Collection($segments) : new Collection();
    }

    /**
     * Termine une chaîne avec une seule instance d'une valeur donnée.
     *
     * @param string $cap Valeur à ajouter à la fin
     */
    public function finish(string $cap): static
    {
        return new static(Text::finish($this->value, $cap));
    }

    /**
     * Détermine si la chaîne correspond à un motif donné.
     *
     * @param iterable<string>|string $pattern Motif(s) à comparer
     * @return bool true si la chaîne correspond à l'un des motifs
     */
    public function is(iterable|string $pattern): bool
    {
        return Text::is($pattern, $this->value);
    }

    /**
     * Détermine si la chaîne est en ASCII 7 bits.
     *
     * @return bool true si la chaîne est en ASCII
     */
    public function isAscii(): bool
    {
        return Text::isAscii($this->value);
    }

    /**
     * Détermine si la chaîne est un JSON valide.
     *
     * @return bool true si la chaîne est un JSON valide
     */
    public function isJson(): bool
    {
        return Text::isJson($this->value);
    }

    /**
     * Détermine si la chaîne est une URL valide.
     *
     * @param array $protocols Protocoles autorisés
	 *
	 * @return bool true si la chaîne est une URL valide
     */
    public function isUrl(array $protocols = [])
    {
        return Text::isUrl($this->value, $protocols);
    }

    /**
     * Détermine si la chaîne est un UUID valide.
     *
     * @param int<0, 8>|'max'|null $version Version de l'UUID
     * @return bool true si la chaîne est un UUID valide
     */
    public function isUuid($version = null): bool
    {
        return Text::isUuid($this->value, $version);
    }

    /**
     * Détermine si la chaîne est un ULID valide.
     *
     * @return bool true si la chaîne est un ULID valide
     */
    public function isUlid(): bool
    {
        return Text::isUlid($this->value);
    }

    /**
     * Détermine si la chaîne est vide.
     *
     * @return bool true si la chaîne est vide
     */
    public function isEmpty(): bool
    {
        return $this->value === '';
    }

    /**
     * Détermine si la chaîne n'est pas vide.
     *
     * @return bool true si la chaîne n'est pas vide
     */
    public function isNotEmpty(): bool
    {
        return !$this->isEmpty();
    }

    /**
     * Convertit une chaîne en kebab-case.
     *
     * @return static Nouvelle instance avec le résultat
     */
    public function kebab(): static
    {
        return new static(Text::kebab($this->value));
    }

    /**
     * Retourne la longueur de la chaîne.
     *
     * @param string|null $encoding Encodage à utiliser
	 * @return int Longueur de la chaîne
     */
    public function length(?string $encoding = null): int
    {
        return Text::length($this->value, $encoding);
    }

    /**
     * Limite le nombre de caractères dans la chaîne.
     *
     * @param int $limit Limite de caractères (par défaut: 100)
     * @param string $end Suffixe si tronqué (par défaut: '...')
     */
    public function limit(int $limit = 100, string $end = '...'): static
    {
        return new static(Text::limit($this->value, $limit, $end));
    }

    /**
     * Convertit la chaîne en minuscules.
     */
    public function lower(): static
    {
        return new static(Text::lower($this->value));
    }

    /**
     * Convertit le Markdown GitHub en HTML.
     *
     * @param array<string, mixed> $options Options de conversion
     */
    public function markdown(array $options = []): static
    {
        return new static(Text::markdown($this->value, $options));
    }

    /**
     * Convertit le Markdown inline en HTML.
     *
     * @param array<string, mixed> $options Options de conversion
     */
    public function inlineMarkdown(array $options = []): static
    {
        return new static(Text::inlineMarkdown($this->value, $options));
    }

    /**
     * Masque une portion d'une chaîne avec un caractère répété.
     *
     * @param string $character Caractère de masquage
     * @param int $index Position de départ du masquage
     * @param int|null $length Longueur à masquer (null = jusqu'à la fin)
     * @param string $encoding Encodage (par défaut: 'UTF-8')
     */
    public function mask(string $character, int $index, ?int $length = null, string $encoding = 'UTF-8'): static
    {
        return new static(Text::mask($this->value, $character, $index, $length, $encoding));
    }

    /**
     * Récupère la chaîne correspondant au motif donné.
     *
     * @param string $pattern Pattern regex
     */
    public function match(string $pattern): static
    {
        return new static(Text::match($pattern, $this->value));
    }

    /**
     * Détermine si une chaîne donnée correspond à un pattern donné.
     *
     * @param string|iterable<string> $pattern Pattern(s) à comparer
	 *
     * @return bool true si la chaîne correspond à l'un des patterns
     */
    public function isMatch(string|iterable $pattern): bool
    {
        return Text::isMatch($pattern, $this->value);
    }

    /**
     * Récupère toutes les chaînes correspondant au motif donné.
     *
     * @param string $pattern Pattern regex
	 * @return Collection Collection des correspondances
     */
    public function matchAll(string $pattern): Collection
    {
        return Text::matchAll($pattern, $this->value);
    }

    /**
     * Détermine si la chaîne correspond au motif donné.
     *
     * @param string $pattern Pattern regex
	 * @return bool true si une correspondance est trouvée
     */
    public function test(string $pattern): bool
    {
        return $this->isMatch($pattern);
    }

    /**
     * Supprime tous les caractères non numériques d'une chaîne.
     */
    public function numbers(): static
    {
        return new static(Text::numbers($this->value));
    }

    /**
     * Remplit les deux côtés de la chaîne avec un autre caractère.
     *
     * @param int $length Longueur totale souhaitée
     * @param string $pad Caractère de remplissage (par défaut: espace)
     */
    public function padBoth(int $length, string $pad = ' '): static
    {
        return new static(Text::padBoth($this->value, $length, $pad));
    }

    /**
     * Remplit le côté gauche de la chaîne avec un autre caractère.
     *
     * @param int $length Longueur totale souhaitée
     * @param string $pad Caractère de remplissage (par défaut: espace)
     */
    public function padLeft(int $length, string $pad = ' '): static
    {
        return new static(Text::padLeft($this->value, $length, $pad));
    }

    /**
     * Remplit le côté droit de la chaîne avec un autre caractère.
     *
     * @param int $length Longueur totale souhaitée
     * @param string $pad Caractère de remplissage (par défaut: espace)
     */
    public function padRight(int $length, string $pad = ' '): static
    {
        return new static(Text::padRight($this->value, $length, $pad));
    }

    /**
     * Parse un callback de style Class@method en classe et méthode.
     *
     * @param string|null $default Valeur par défaut pour la méthode
	 * @return array<int, string|null> [classe, méthode]
     */
    public function parseCallback(?string $default = null): array
    {
        return Text::parseCallback($this->value, $default);
    }

    /**
     * Passe la chaîne au callback donné et retourne une nouvelle chaîne.
     *
     * @param callable $callback Callback à appliquer
     */
    public function pipe(callable $callback): static
    {
        return new static($callback($this));
    }

    /**
     * Récupère la forme plurielle d'un mot anglais.
     *
     * @param array|Countable|int $count Nombre pour décider du pluriel (par défaut: 2)
     */
    public function plural(array|Countable|int $count = 2): static
    {
        return new static(Text::plural($this->value, $count));
    }

    /**
     * Met au pluriel le dernier mot d'une chaîne en StudlyCaps.
     *
     * @param array|Countable|int $count Nombre pour décider du pluriel (par défaut: 2)
     */
    public function pluralStudly(array|Countable|int $count = 2): static
    {
        return new static(Text::pluralStudly($this->value, $count));
    }

    /**
     * Met au pluriel le dernier mot d'une chaîne en PascalCaps.
     *
     * @param array|Countable|int $count Nombre pour décider du pluriel (par défaut: 2)
     */
    public function pluralPascal(array|Countable|int $count = 2): static
    {
        return $this->pluralStudly($count);
    }

    /**
     * Ajoute les valeurs données au début de la chaîne.
     *
     * @param string ...$values Valeurs à ajouter
     */
    public function prepend(string ...$values): static
    {
        return new static(implode('', $values) . $this->value);
    }

    /**
     * Supprime toute occurrence de la chaîne donnée dans le sujet.
     *
     * @param iterable<string>|string $search Valeur(s) à supprimer
     * @param bool $caseSensitive Sensible à la casse (par défaut: true)
     */
    public function remove(iterable|string $search, bool $caseSensitive = true): static
    {
        return new static(Text::remove($search, $this->value, $caseSensitive));
    }

    /**
     * Inverse la chaîne.
     */
    public function reverse(): static
    {
        return new static(Text::reverse($this->value));
    }

    /**
     * Répète la chaîne.
     *
     * @param int $times Nombre de répétitions
     */
    public function repeat(int $times): static
    {
        return new static(str_repeat($this->value, $times));
    }

    /**
     * Remplace la valeur donnée dans la chaîne.
     *
     * @param iterable<string>|string $search Valeur(s) à rechercher
     * @param iterable<string>|string $replace Valeur(s) de remplacement
     */
    public function replace(iterable|string $search, iterable|string $replace): static
    {
        return new static(Text::replace($search, $replace, $this->value));
    }

    /**
     * Remplace une valeur donnée dans la chaîne séquentiellement avec un tableau.
     *
     * @param string $search Valeur à rechercher
     * @param iterable<string> $replace Valeurs de remplacement
     */
    public function replaceArray(string $search, iterable $replace): static
    {
        return new static(Text::replaceArray($search, $replace, $this->value));
    }

    /**
     * Remplace la première occurrence d'une valeur donnée dans la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     */
    public function replaceFirst(string $search, string $replace): static
    {
        return new static(Text::replaceFirst($search, $replace, $this->value));
    }

	/**
     * Remplace la première occurrence de la valeur donnée si elle apparaît au début de la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     */
    public function replaceStart(string $search, string $replace): static
    {
        return new static(Text::replaceStart($search, $replace, $this->value));
    }

    /**
     * Remplace la dernière occurrence d'une valeur donnée dans la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     */
    public function replaceLast(string $search, string $replace): static
    {
        return new static(Text::replaceLast($search, $replace, $this->value));
    }

    /**
     * Remplace la dernière occurrence d'une valeur donnée si elle apparaît à la fin de la chaîne.
     *
     * @param string $search Valeur à rechercher
     * @param string $replace Valeur de remplacement
     */
    public function replaceEnd($search, $replace)
    {
        return new static(Text::replaceEnd($search, $replace, $this->value));
    }

    /**
     * Remplace les motifs correspondant à l'expression régulière donnée.
     *
     * @param string $pattern Pattern regex
     * @param Closure|string $replace Callback ou chaîne de remplacement
     * @param int $limit Limite de remplacements (par défaut: -1)
     */
    public function replaceMatches(string $pattern, Closure|string $replace, int $limit = -1): static
    {
        if ($replace instanceof Closure) {
            return new static(preg_replace_callback($pattern, $replace, $this->value, $limit) ?? $this->value);
        }

        return new static(preg_replace($pattern, $replace, $this->value, $limit) ?? $this->value);
    }

    /**
     * Parse l'entrée d'une chaîne vers une collection, selon un format.
     *
     * @param string $format Format à utiliser (comme sscanf)
	 * @return Collection Collection des valeurs parsées
     */
    public function scan(string $format): Collection
    {
        $result = sscanf($this->value, $format);

        return new Collection($result !== false ? $result : []);
    }

    /**
     * Supprime tous les espaces "extra" de la chaîne.
     */
    public function squish(): static
    {
        return new static(Text::squish($this->value));
    }

    /**
     * Commence une chaîne avec une seule instance d'une valeur donnée.
     *
     * @param string $prefix Préfixe à ajouter
     */
    public function start(string $prefix): static
    {
        return new static(Text::start($this->value, $prefix));
    }

    /**
     * Supprime les balises HTML et PHP de la chaîne.
     *
     * @param string|null $allowedTags Balises autorisées
     */
    public function stripTags(?string $allowedTags = null): static
    {
        return new static(strip_tags($this->value, $allowedTags));
    }

    /**
     * Convertit la chaîne en majuscules.
     */
    public function upper(): static
    {
        return new static(Text::upper($this->value));
    }

    /**
     * Convertit la chaîne en title case.
     */
    public function title(): static
    {
        return new static(Text::title($this->value));
    }

    /**
     * Convertit la chaîne en title case pour chaque mot (format titre).
     */
    public function headline(): static
    {
        return new static(Text::headline($this->value));
    }

    /**
     * Convertit la chaîne donnée en casse de titre style APA.
     */
    public function apa(): static
    {
        return new static(Text::apa($this->value));
    }

    /**
     * Translittère une chaîne vers sa représentation ASCII la plus proche.
     *
     * @param string|null $unknown Caractère de remplacement pour caractères inconnus
     * @param bool|null $strict Mode strict
     */
    public function transliterate(?string $unknown = '?', ?bool $strict = false): static
    {
        return new static(Text::transliterate($this->value, $unknown, $strict));
    }

    /**
     * Récupère la forme singulière d'un mot anglais.
     */
    public function singular(): static
    {
        return new static(Text::singular($this->value));
    }

    /**
     * Génère un "slug" convivial pour les URL.
     *
     * @param string $separator Séparateur (par défaut: '-')
     * @param string|null $language Langue pour la translittération
     * @param array<string, string> $dictionary Dictionnaire de remplacements
     */
    public function slug(string $separator = '-', ?string $language = 'en', array $dictionary = ['@' => 'at']): static
    {
        return new static(Text::slug($this->value, $separator, $language, $dictionary));
    }

    /**
     * Convertit une chaîne en snake_case.
     *
     * @param string $delimiter Délimateur (par défaut: '_')
     */
    public function snake(string $delimiter = '_'): static
    {
        return new static(Text::snake($this->value, $delimiter));
    }

    /**
     * Détermine si la chaîne commence par une sous-chaîne donnée.
     *
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
	 * @return bool true si la chaîne commence par l'une des sous-chaînes
     */
    public function startsWith(iterable|string $needles): bool
    {
        return Text::startsWith($this->value, $needles);
    }

    /**
     * Détermine si la chaîne ne commence pas par une sous-chaîne donnée.
     *
     * @param iterable<string>|string $needles Sous-chaîne(s) à rechercher
	 * @return bool true si la chaîne ne commence pas par l'une des sous-chaînes
     */
    public function doesntStartWith(iterable|string $needles)
    {
        return Text::doesntStartWith($this->value, $needles);
    }

    /**
     * Convertit une valeur en StudlyCaps.
     *
     * @return static Nouvelle instance avec le résultat
     */
    public function studly(): static
    {
        return new static(Text::studly($this->value));
    }

    /**
     * Convertit la chaîne en Pascal case.
     */
    public function pascal(): static
    {
        return new static(Text::pascal($this->value));
    }

    /**
     * Retourne la partie de chaîne spécifiée par les paramètres start et length.
     *
     * @param int $start Position de départ
     * @param int|null $length Longueur à extraire (null = jusqu'à la fin)
     * @param string $encoding Encodage (par défaut: 'UTF-8')
     */
    public function substr(int $start, ?int $length = null, string $encoding = 'UTF-8'): static
    {
        return new static(Text::substr($this->value, $start, $length, $encoding));
    }

    /**
     * Retourne le nombre d'occurrences d'une sous-chaîne.
     *
     * @param string $needle Sous-chaîne à compter
     * @param int $offset Position de départ
     * @param int|null $length Longueur à parcourir
     */
    public function substrCount(string $needle, int $offset = 0, ?int $length = null): int
    {
        return Text::substrCount($this->value, $needle, $offset, $length);
    }

    /**
     * Remplace du texte dans une partie d'une chaîne.
     *
     * @param list<string>|string $replace Texte de remplacement
     * @param int|list<int> $offset Position de départ
     * @param int|list<int>|null $length Longueur à remplacer
     */
    public function substrReplace(array|string $replace, int|array $offset = 0, int|array|null $length = null): static
    {
        return new static(Text::substrReplace($this->value, $replace, $offset, $length));
    }

    /**
     * Échange plusieurs mots-clés dans la chaîne avec d'autres mots-clés.
     *
     * @param array<string, string> $map Tableau de correspondances
     */
    public function swap(array $map): static
    {
        return new static(Text::swap($map, $this->value));
    }

    /**
     * Prend les premiers ou derniers caractères.
     *
     * @param int $limit Nombre de caractères (positif pour le début, négatif pour la fin)
     */
    public function take(int $limit)
    {
        if ($limit < 0) {
            return $this->substr($limit);
        }

        return $this->substr(0, $limit);
    }

    /**
     * Trim la chaîne des caractères donnés.
     *
     * @param string|null $characters Caractères à retirer
     */
    public function trim(?string $characters = null): static
    {
       return new static(Text::trim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Trim gauche de la chaîne des caractères donnés.
     *
     * @param string|null $characters Caractères à retirer
     */
    public function ltrim(?string $characters = null): static
    {
        return new static(Text::ltrim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Trim droit de la chaîne des caractères donnés.
     *
     * @param string|null $characters Caractères à retirer
     */
    public function rtrim(?string $characters = null): static
    {
		return new static(Text::rtrim(...array_merge([$this->value], func_get_args())));
    }

    /**
     * Met le premier caractère de la chaîne en minuscule.
     */
    public function lcfirst(): static
    {
        return new static(Text::lcfirst($this->value));
    }

    /**
     * Met le premier caractère de la chaîne en majuscule.
     */
    public function ucfirst(): static
    {
        return new static(Text::ucfirst($this->value));
    }

    /**
     * Met en majuscule le premier caractère de chaque mot dans une chaîne.
     *
     * @param string $separators Séparateurs de mots (par défaut: " \t\r\n\f\v")
     */
    public function ucwords(string $separators = " \t\r\n\f\v")
    {
        return new static(Text::ucwords($this->value, $separators));
    }

    /**
     * Divise une chaîne par caractères majuscules.
     *
     * @return Collection<int, string> Collection des morceaux
     */
    public function ucsplit(): Collection
    {
        return new Collection(Text::ucsplit($this->value));
    }

    /**
     * Exécute le callback donné si la chaîne contient une sous-chaîne donnée.
     *
     * @param string|iterable<string> $needles Sous-chaîne(s) à rechercher
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenContains(iterable|string $needles, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->contains($needles), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne contient toutes les valeurs du tableau.
     *
     * @param iterable<string> $needles Sous-chaînes à rechercher
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenContainsAll(array $needles, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->containsAll($needles), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne est vide.
     *
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isEmpty(), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne n'est pas vide.
     *
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenNotEmpty(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isNotEmpty(), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne se termine par une sous-chaîne donnée.
     *
     * @param string|iterable<string> $needles Sous-chaîne(s) à rechercher
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenEndsWith(iterable|string $needles, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->endsWith($needles), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne ne se termine pas par une sous-chaîne donnée.
     *
     * @param string|iterable<string> $needles Sous-chaîne(s) à rechercher
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenDoesntEndWith(iterable|string $needles, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->doesntEndWith($needles), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne correspond exactement à la valeur donnée.
     *
     * @param string $value Valeur à comparer
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenExactly(string $value, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->exactly($value), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne ne correspond pas exactement à la valeur donnée.
     *
     * @param string $value Valeur à comparer
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenNotExactly(string $value, callable $callback, ?callable $default = null): static
    {
        return $this->when(! $this->exactly($value), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne correspond à un pattern donné.
     *
     * @param string|iterable<string> $pattern Pattern(s) à comparer
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenIs(iterable|string $pattern, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->is($pattern), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne est en ASCII 7 bits.
     *
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenIsAscii(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isAscii(), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne est un UUID valide.
     *
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenIsUuid(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isUuid(), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne est un ULID valide.
     *
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenIsUlid(callable $callback, ?callable $default = null): static
    {
        return $this->when($this->isUlid(), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne commence par une sous-chaîne donnée.
     *
     * @param string|iterable<string> $needles Sous-chaîne(s) à rechercher
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenStartsWith(iterable|string $needles, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->startsWith($needles), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne ne commence pas par une sous-chaîne donnée.
     *
     * @param string|iterable<string> $needles Sous-chaîne(s) à rechercher
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenDoesntStartWith(iterable|string $needles, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->doesntStartWith($needles), $callback, $default);
    }

    /**
     * Exécute le callback donné si la chaîne correspond au pattern donné.
     *
     * @param string $pattern Pattern regex
     * @param callable $callback Callback à exécuter
     * @param callable|null $default Callback par défaut (optionnel)
     */
    public function whenTest(string $pattern, callable $callback, ?callable $default = null): static
    {
        return $this->when($this->test($pattern), $callback, $default);
    }

    /**
     * Limite le nombre de mots dans la chaîne.
     *
     * @param int $words Nombre maximum de mots (par défaut: 100)
     * @param string $end Suffixe si tronqué (par défaut: '...')
     */
    public function words(int $words = 100, string $end = '...'): static
    {
        return new static(Text::words($this->value, $words, $end));
    }

    /**
     * Récupère le nombre de mots dans la chaîne.
     *
     * @param string|null $characters Caractères supplémentaires considérés comme faisant partie des mots
	 * @return int Nombre de mots
     */
    public function wordCount(?string $characters = null): int
    {
        return Text::wordCount($this->value, $characters);
    }

    /**
     * Wrap une chaîne sur un nombre donné de caractères.
     *
     * @param int $characters Nombre de caractères par ligne (par défaut: 75)
     * @param string $break Caractère de rupture de ligne (par défaut: "\n")
     * @param bool $cutLongWords Couper les mots longs (par défaut: false)
     */
    public function wordWrap(int $characters = 75, string $break = "\n", bool $cutLongWords = false): static
    {
        return new static(Text::wordWrap($this->value, $characters, $break, $cutLongWords));
    }

    /**
     * Encapsule la chaîne avec les chaînes données.
     *
     * @param string $before Chaîne à placer avant
     * @param string|null $after Chaîne à placer après (null = utilise $before)
     */
    public function wrap(string $before, ?string $after = null): static
    {
        return new static(Text::wrap($this->value, $before, $after));
    }

    /**
     * Désencapsule la chaîne avec les chaînes données.
     *
     * @param string $before Chaîne à retirer devant
     * @param string|null $after Chaîne à retirer derrière (null = utilise $before)
     */
    public function unwrap(string $before, ?string $after = null): static
    {
        return new static(Text::unwrap($this->value, $before, $after));
    }

    /**
     * Convertit la chaîne en encodage Base64.
     */
    public function toBase64(): static
    {
        return new static(base64_encode($this->value));
    }

    /**
     * Décode la chaîne encodée en Base64.
     *
     * @param bool $strict Mode strict
     */
    public function fromBase64(bool $strict = false): static
    {
        return new static(base64_decode($this->value, $strict));
    }

    /**
     * Hache la chaîne en utilisant l'algorithme donné.
     *
     * @param string $algorithm Algorithme de hachage
     */
    public function hash(string $algorithm): static
    {
        return new static(hash($algorithm, $this->value));
    }

    /**
     * Crypte la chaîne.
     *
     * @param bool $serialize Sérialiser les données (par défaut: false)
	 * @return static
     */
    public function encrypt(bool $serialize = false)
    {
		if (function_exists('service')) {
			return new static(service('encrypter')->encrypt($this->value));
		}

		throw new RuntimeException();
    }

    /**
     * Décrypte la chaîne.
     *
     * @param bool $serialize Sérialiser les données (par défaut: false)
     */
    public function decrypt(bool $serialize = false): static
    {
		if (function_exists('service')) {
			return new static(service('encrypter')->decrypt($this->value));
		}

		throw new RuntimeException();
    }

    /**
     * Récupère la valeur de chaîne sous-jacente.
     *
     * @return string Valeur de la chaîne
     */
    public function value(): string
    {
        return $this->toString();
    }

    /**
     * Récupère la valeur de chaîne sous-jacente.
     *
     * @return string Valeur de la chaîne
     */
    public function toString(): string
    {
        return $this->value;
    }

    /**
     * Récupère la valeur de chaîne sous-jacente sous forme d'entier.
     *
     * @param int $base Base numérique (par défaut: 10)
	 *
     * @return int Valeur entière
     */
    public function toInteger(int $base = 10): int
    {
        return intval($this->value, $base);
    }

    /**
     * Récupère la valeur de chaîne sous-jacente sous forme de flottant.
     *
     * @return float Valeur flottante
     */
    public function toFloat(): float
    {
        return (float) $this->value;
    }

    /**
     * Récupère la valeur de chaîne sous-jacente sous forme de booléen.
     *
     * Retourne true quand la valeur est "1", "true", "on", ou "yes".
     * Sinon, retourne false.
     *
     * @return bool Valeur booléenne
     */
    public function toBoolean(): bool
    {
        return filter_var($this->value, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * Récupère la valeur de chaîne sous-jacente sous forme d'instance Date.
     *
     * @param string|null $format Format de date spécifique
     * @param string|null $tz Fuseau horaire
	 * @return Date Instance Date
	 * @throws Exception Si le format est invalide
     */
    public function toDate(?string $format = null, ?string $tz = null): Date
    {
        if (null === $format) {
            return Date::parse($this->value, $tz);
        }

        return Date::createFromFormat($format, $this->value, $tz);
    }

    /**
     * Convertit l'objet en chaîne lorsqu'encodé en JSON.
     *
     * @return string Chaîne JSON encodée
     */
    public function jsonSerialize(): string
    {
        return $this->__toString();
    }

    /**
     * Détermine si l'offset donné existe.
     *
     * @param mixed $offset Offset à vérifier
     */
    public function offsetExists(mixed $offset): bool
    {
        return isset($this->value[$offset]);
    }

    /**
     * Récupère la valeur à l'offset donné.
     *
     * @param mixed $offset Offset à récupérer
     */
    public function offsetGet(mixed $offset): string
    {
        return $this->value[$offset];
    }

    /**
     * Définit la valeur à l'offset donné.
     *
     * @param mixed $offset Offset à définir
     * @param mixed $value Valeur à assigner
     */
    public function offsetSet(mixed $offset, mixed $value): void
    {
        $this->value[$offset] = $value;
    }

    /**
     * Supprime la valeur à l'offset donné.
     *
     * @param mixed $offset Offset à supprimer
     */
    public function offsetUnset(mixed $offset): void
    {
        unset($this->value[$offset]);
    }

    /**
     * Proxy des propriétés dynamiques vers les méthodes.
     *
     * @param string $key Nom de la propriété
     * @return mixed Résultat de la méthode correspondante
     */
    public function __get(string $key): mixed
    {
        $value = $this->{$key}();

		if ($value instanceof self) {
			return $value->toString();
		}

		return $value;
    }

    /**
     * Récupère la valeur brute de la chaîne.
     *
     * @return string Valeur de la chaîne
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Transforme la chaîne en utilisant un callback et retourne le résultat.
     *
     * @param callable $callback Callback de transformation
     * @return mixed Résultat de la transformation
     */
    public function transform(callable $callback): mixed
    {
        return $callback($this);
    }


    /**
     * Compare la chaîne avec une autre (insensible à la casse par défaut).
     *
     * @param string|Stringable $value Valeur à comparer
     * @param bool $caseSensitive Sensible à la casse (par défaut: false)
     * @return int -1 si inférieur, 0 si égal, 1 si supérieur
     */
    public function compare(string|Stringable $value, bool $caseSensitive = false): int
    {
        if ($value instanceof Stringable) {
            $value = $value->toString();
        }

        if ($caseSensitive) {
            return strcmp($this->value, $value);
        }

        return strcasecmp($this->value, $value);
    }

    /**
     * Vérifie si la chaîne est égale à une autre (insensible à la casse par défaut).
     *
     * @param string|Stringable $value Valeur à comparer
     * @param bool $caseSensitive Sensible à la casse (par défaut: false)
     * @return bool true si les chaînes sont égales
     */
    public function equals(string|Stringable $value, bool $caseSensitive = false): bool
    {
        if ($value instanceof Stringable) {
            $value = $value->toString();
        }

        if ($caseSensitive) {
            return $this->value === $value;
        }

        return strcasecmp($this->value, $value) === 0;
    }

    /**
     * Récupère la position de la première occurrence d'une sous-chaîne.
     *
     * @param string $needle Sous-chaîne à rechercher
     * @param int $offset Position de départ
     * @param string|null $encoding Encodage utilisé
	 * @return int|false Position ou false si non trouvé
     */
    public function position(string $needle, int $offset = 0, ?string $encoding = null): int|false
    {
        return Text::position($this->value, $needle, $offset, $encoding);
    }

    /**
     * Récupère la position de la dernière occurrence d'une sous-chaîne.
     *
     * @param string $needle Sous-chaîne à rechercher
     * @param int $offset Position de départ
     * @param bool $caseSensitive Sensible à la casse (par défaut: true)
     * @return int|false Position ou false si non trouvé
     */
    public function lastPosition(string $needle, int $offset = 0, bool $caseSensitive = true): int|false
    {
        if ($caseSensitive) {
            return strrpos($this->value, $needle, $offset);
        }

        return strripos($this->value, $needle, $offset);
    }

    /**
     * Vérifie si la chaîne est composée uniquement de caractères alphabétiques.
     *
     * @return bool true si la chaîne est alphabétique
     */
    public function isAlpha(): bool
    {
        return ctype_alpha($this->value);
    }

    /**
     * Vérifie si la chaîne est composée uniquement de chiffres.
     *
     * @return bool true si la chaîne est numérique
     */
    public function isNumeric(): bool
    {
        return ctype_digit($this->value);
    }

    /**
     * Vérifie si la chaîne est composée uniquement de caractères alphanumériques.
     *
     * @return bool true si la chaîne est alphanumérique
     */
    public function isAlnum(): bool
    {
        return ctype_alnum($this->value);
    }

    /**
     * Vérifie si la chaîne est composée uniquement de caractères imprimables.
     *
     * @return bool true si tous les caractères sont imprimables
     */
    public function isPrintable(): bool
    {
        return ctype_print($this->value);
    }

    /**
     * Convertit la chaîne en encodage spécifié.
     *
     * @param string $toEncoding Encodage de destination
     * @param string $fromEncoding Encodage source (par défaut: détection automatique)
     * @return static Nouvelle instance avec le résultat
     */
    public function convertEncoding(string $toEncoding, string $fromEncoding = 'UTF-8'): static
    {
        return new static(mb_convert_encoding($this->value, $toEncoding, $fromEncoding));
    }

    /**
     * Normalise la chaîne selon la forme Unicode spécifiée.
     *
     * @param int $form Forme de normalisation (par défaut: Normalizer::FORM_C)
     * @return static Nouvelle instance avec le résultat
     */
    public function normalize(int $form = \Normalizer::FORM_C): static
    {
        if (class_exists('Normalizer') && \Normalizer::isNormalized($this->value, $form)) {
            return new static(\Normalizer::normalize($this->value, $form));
        }

        return $this;
    }
}
