<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\Reflection;

use InvalidArgumentException;
use ReflectionClass as PhpReflectionClass;
use ReflectionException;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Classe utilitaire de réflexion étendant la fonctionnalité native de PHP.
 *
 * Cette classe fournit des méthodes simplifiées pour accéder et manipuler
 * les membres privés et protégés d'une classe, ainsi que des fonctionnalités
 * supplémentaires utiles pour le testing et l'introspection.
 *
 * @template T of object
 * @extends PhpReflectionClass<T>
 *
 * @method string getName() Récupère le nom de la classe
 * @method bool isInstantiable() Vérifie si la classe est instanciable
 * @method ReflectionClass|null getParentClass() Récupère la classe parente
 * @method ReflectionMethod getMethod(string $name) Récupère une ReflectionMethod pour une méthode de la classe
 * @method ReflectionProperty[] getProperties(int|null $filter = null) Récupère les propriétés de la classe
 * @method array getConstants() Récupère les constantes de la classe
 * @method bool hasMethod(string $name) Vérifie si la classe possède une méthode
 * @method bool hasProperty(string $name) Vérifie si la classe possède une propriété
 * @method bool hasConstant(string $name) Vérifie si la classe possède une constante
 * @method bool isSubclassOf(string|object $class) Vérifie si la classe est une sous-classe
 * @method bool implementsInterface(string $interface) Vérifie si la classe implémente une interface
 * @method string getNamespaceName() Récupère le nom de l'espace de noms
 * @method string getShortName() Récupère le nom court de la classe (sans le namespace)
 * @method object newInstance(mixed ...$args) Crée une nouvelle instance de la classe
 * @method object newInstanceWithoutConstructor() Crée une nouvelle instance sans appeler le constructeur
 * @method object newInstanceArgs(array $args = []) Crée une nouvelle instance en utilisant un tableau d'arguments
 */
class ReflectionClass extends PhpReflectionClass
{
    /**
     * Cache des méthodes pour améliorer les performances.
     */
    private array $methodCache = [];

    /**
     * Cache des propriétés pour améliorer les performances.
     */
    private array $propertyCache = [];

    // =========================================================================
    // SECTION: CONSTRUCTEUR ET FACTORY
    // =========================================================================

    /**
     * {@inheritDoc}
     *
     * @param object|string $objectOrClass Instance d'objet ou nom fully-qualified de la classe à analyser
     */
    public function __construct(protected object|string $objectOrClass)
    {
        parent::__construct($objectOrClass);
    }

    /**
     * Factory method pour créer une nouvelle instance de ReflectionClass.
     *
     * Cette méthode permet une création plus expressive et fluide.
     *
     * @param object|class-string<T> $objectOrClass L'objet ou le nom de la classe à analyser.
     *
     * @return static Une nouvelle instance de ReflectionClass.
     *
     * @throws ReflectionException Si la classe n'existe pas.
     *
     * @example
     * $reflection = ReflectionClass::make(MyClass::class);
     * $reflection = ReflectionClass::make($object);
     */
    public static function make(object|string $objectOrClass): static
    {
        return new static($objectOrClass);
    }

    // =========================================================================
    // SECTION: INSTANCIATION ET CONSTRUCTEURS
    // =========================================================================

    /**
     * Crée une nouvelle instance de la classe, même si le constructeur est privé/protégé.
     *
     * Cette méthode étend la fonctionnalité native en permettant d'instancier des classes
     * avec des constructeurs non-publics.
     *
     * @return object Nouvelle instance de la classe
     *
     * @throws ReflectionException Si l'instanciation échoue
     *
     * @example
     * $instance = $reflection->newInstance($arg1, $arg2);
     */
    public function newInstance(mixed ...$args): object
    {
        $constructor = $this->getConstructor();

        // Si pas de constructeur OU constructeur public
        if ($constructor === null || $constructor->isPublic()) {
            try {
                return parent::newInstance(...$args);
            } catch (\ReflectionException) {
                // Fallback pour certains cas
            }
        }

        // Pour les constructeurs non-publics
        $instance = $this->createInstanceWithoutConstructor();

        if ($constructor !== null) {
            $constructor->setAccessible(true);
            $constructor->invokeArgs($instance, $args);
        }

        return $instance;
    }

    /**
     * Crée une instance sans appeler le constructeur (compatible PHP 7.x et 8.x).
     *
     * @return object Instance créée sans constructeur
     *
     * @internal Méthode interne utilisée par newInstance()
     */
    private function createInstanceWithoutConstructor(): object
    {
        if (method_exists(parent::class, 'newInstanceWithoutConstructor')) {
            return parent::newInstanceWithoutConstructor();
        }

        // Fallback pour PHP < 8.0
        $className = $this->getName();

        return (function() use ($className) {
            $object = unserialize(
                sprintf('O:%d:"%s":0:{}', strlen($className), $className)
            );

            // Si la classe a __wakeup(), on le rend inaccessible temporairement
            if (method_exists($object, '__wakeup')) {
                $refMethod = new ReflectionMethod($object, '__wakeup');
                $refMethod->setAccessible(false);
            }

            return $object;
        })();
    }

    // =========================================================================
    // SECTION: VÉRIFICATIONS D'EXISTENCE
    // =========================================================================

    /**
     * Vérifie si une méthode existe (dans la hiérarchie) sans lever d'exception.
     *
     * @example
     * if ($reflection->hasMethod('privateMethod')) {
     *     // La méthode existe
     * }
     */
    public function hasMethod(string $name): bool
    {
        try {
            $this->getMethod($name);
            return true;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Vérifie si une propriété existe (dans la hiérarchie) sans lever d'exception.
     *
     * @example
     * if ($reflection->hasProperty('privateProperty')) {
     *     // La propriété existe
     * }
     */
    public function hasProperty(string $name): bool
    {
        try {
            $this->getProperty($name);
            return true;
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Vérifie si la classe ou l'objet possède une propriété avec une valeur spécifique.
	 *
     * @param bool   $strict   Utiliser la comparaison stricte (===)
     *
     * @return bool True si la propriété existe et a la valeur spécifiée
     *
     * @example
     * if ($reflection->hasValue('status', 'active')) {
     *     // La propriété 'status' vaut 'active'
     * }
     */
    public function hasValue(string $property, mixed $value, bool $strict = true): bool
    {
        try {
            $propertyValue = $this->getValue($property);
            return $strict ? $propertyValue === $value : $propertyValue == $value;
        } catch (ReflectionException) {
            return false;
        }
    }

    // =========================================================================
    // SECTION: MÉTHODES (ACCÈS ET MANIPULATION)
    // =========================================================================

    /**
     * Récupère une méthode en la rendant accessible (même si privée ou protégée).
     *
     * @throws ReflectionException Si la méthode n'existe pas
     *
     * @example
     * $method = $reflection->getMethod('privateMethod');
     */
    public function getMethod(string $method): ReflectionMethod
    {
        $key = is_object($this->objectOrClass)
            ? spl_object_hash($this->objectOrClass) . '::' . $method
            : $this->objectOrClass . '::' . $method;

        if (!isset($this->methodCache[$key])) {
            $refMethod = new ReflectionMethod($this->objectOrClass, $method);
            $refMethod->setAccessible(true);
            $this->methodCache[$key] = $refMethod;
        }

        return $this->methodCache[$key];
    }

    /**
     * Invoque une méthode de la classe ou de l'objet.
     *
     * Cette méthode permet d'appeler une méthode même si elle est privée ou protégée.
     * Elle gère automatiquement l'invocation statique ou d'instance.
     *
     * @throws ReflectionException Si la méthode n'existe pas
     *
     * @example
     * $result = $reflection->invoke('privateMethod', $arg1, $arg2);
     */
    public function invoke(string $method, mixed ...$args): mixed
    {
        $object = (gettype($this->objectOrClass) === 'object') ? $this->objectOrClass : null;
        return $this->getMethod($method)->invokeArgs($object, $args);
    }

    /**
     * Récupère les méthodes de la classe avec un filtre optionnel.
     *
     * Cette méthode étend la fonctionnalité native en permettant d'utiliser
     * un opérateur AND bitwise pour les filtres (par défaut), contrairement
     * au comportement OR bitwise natif de PHP.
     *
     * @param int|null $filter          Flags de filtrage (ex. ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC)
     * @param bool     $useAndOperator  Si true, utilise un AND bitwise strict pour le filtrage (par défaut)
     *                                  Si false, utilise le comportement natif OR bitwise de PHP
     *
     * @return ReflectionMethod[] Tableau d'objets ReflectionMethod
     *
     * @example
     * // Récupère uniquement les méthodes publiques ET statiques
     * $methods = $reflection->getMethods(
     *     ReflectionMethod::IS_PUBLIC | ReflectionMethod::IS_STATIC,
     *     true
     * );
     *
     * @see ReflectionMethod::IS_PUBLIC
     * @see ReflectionMethod::IS_PROTECTED
     * @see ReflectionMethod::IS_PRIVATE
     * @see ReflectionMethod::IS_STATIC
     */
    public function getMethods(?int $filter = null, bool $useAndOperator = true): array
    {
        if ($useAndOperator !== true || $filter === null) {
            return parent::getMethods($filter);
        }

        $methods = parent::getMethods($filter);
        $results = [];

        foreach ($methods as $method) {
            if (($method->getModifiers() & $filter) === $filter) {
                $results[] = $method;
            }
        }

        return $results;
    }

    /**
     * Vérifie si une méthode est abstraite.
     *
     * @example
     * if ($reflection->isAbstractMethod('calculate')) {
     *     // La méthode 'calculate' est abstraite
     * }
     */
    public function isAbstractMethod(string $method): bool
    {
        return $this->getMethod($method)->isAbstract();
    }

    /**
     * Vérifie si une méthode est finale.
     *
     * @example
     * if ($reflection->isFinalMethod('process')) {
     *     // La méthode 'process' est finale et ne peut être surchargée
     * }
     */
    public function isFinalMethod(string $method): bool
    {
        return $this->getMethod($method)->isFinal();
    }

    /**
     * Extrait les types de paramètres d'une méthode.
     *
     * @return array<string, string> Tableau associatif [nomParamètre => type]
     *
     * @example
     * $types = $reflection->getMethodParameterTypes('process');
     * // Retourne: ['name' => 'string', 'age' => 'int', 'options' => 'mixed']
     */
    public function getMethodParameterTypes(string $method): array
    {
        $parameters = $this->getMethod($method)->getParameters();
        $types      = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();
            $types[$parameter->getName()] = $type ? $type->getName() : 'mixed';
        }

        return $types;
    }

    // =========================================================================
    // SECTION: PROPRIÉTÉS (ACCÈS ET MANIPULATION)
    // =========================================================================

    /**
     * Trouve et rend accessible une propriété de la classe ou de l'objet.
     *
     * @throws ReflectionException Si la propriété n'existe pas
     *
     * @example
     * $property = $reflection->getProperty('privateProperty');
     */
    public function getProperty(string $name): ReflectionProperty
    {
        $key = is_object($this->objectOrClass)
            ? spl_object_hash($this->objectOrClass) . '::$' . $name
            : $this->objectOrClass . '::$' . $name;

        if (!isset($this->propertyCache[$key])) {
            // Cherche d'abord dans la classe courante
            try {
                $property = new ReflectionProperty($this->getName(), $name);
            } catch (ReflectionException $e) {
                // Si pas trouvé, utilise la méthode parente qui cherche dans l'héritage
                $property = parent::getProperty($name);
            }

            $property->setAccessible(true);
            $this->propertyCache[$key] = $property;
        }

        return $this->propertyCache[$key];
    }

    /**
     * Récupère les propriétés avec un filtrage optionnel.
     *
     * Ajoute le paramètre $useAndOperator pour un filtrage AND strict sur les modifiers.
     *
     * @param int|null $filter          Flags de filtrage (ex. ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_STATIC)
     * @param bool     $useAndOperator  Si true, utilise un filtrage AND strict (par défaut)
     *
     * @return ReflectionProperty[] Tableau de ReflectionProperty
     *
     * @example
     * // Récupère uniquement les propriétés privées ET statiques
     * $properties = $reflection->getProperties(
     *     ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_STATIC,
     *     true
     * );
     */
    public function getProperties(?int $filter = null, bool $useAndOperator = true): array
    {
        if ($useAndOperator !== true || $filter === null) {
            return parent::getProperties($filter);
        }

        $properties = parent::getProperties($filter);
        $results    = [];

        foreach ($properties as $property) {
            if (($property->getModifiers() & $filter) === $filter) {
                $results[] = $property;
            }
        }

        return $results;
    }

    /**
     * Récupère la valeur d'une propriété privée ou protégée.
     *
     * @throws ReflectionException Si la propriété n'existe pas
     *
     * @example
     * $value = $reflection->getValue('privateProperty');
     */
    public function getValue(string $property): mixed
    {
        $refProperty = $this->getProperty($property);

        return is_string($this->objectOrClass)
            ? $refProperty->getValue()
            : $refProperty->getValue($this->objectOrClass);
    }

    /**
     * Récupère les valeurs de toutes les propriétés.
     *
     * Pour une classe (string), retourne les valeurs par défaut des propriétés statiques.
     * Pour une instance, retourne les valeurs actuelles.
     *
     * @param int|null $filter          Flags de filtrage (ex. ReflectionProperty::IS_PRIVATE)
     * @param bool     $useAndOperator  Si true, utilise un filtrage AND strict
     *
     * @return array<string, mixed> Tableau associatif [nom propriété => valeur]
     *
     * @example
     * $allValues = $reflection->getValues();
     * // Retourne: ['privateProp' => 'value1', 'protectedProp' => 'value2', ...]
     */
    public function getValues(?int $filter = null, bool $useAndOperator = true): array
    {
        $properties = $this->getProperties($filter, $useAndOperator);
        $values     = [];

        foreach ($properties as $property) {
            $values[$property->getName()] = $this->getValue($property->getName());
        }

        return $values;
    }

    /**
     * Définit la valeur d'une propriété privée ou protégée.
     *
     * @throws ReflectionException Si la propriété n'existe pas
     *
     * @example
     * $reflection->setValue('privateProperty', 'nouvelle valeur');
     */
    public function setValue(string $property, mixed $value): self
    {
        $refProperty = $this->getProperty($property);

        if (is_object($this->objectOrClass)) {
            $refProperty->setValue($this->objectOrClass, $value);
        } else {
            $refProperty->setValue(null, $value);
        }

        return $this;
    }

    /**
     * Définit plusieurs propriétés en une seule fois.
     *
     * @param array<string, mixed> $values Tableau associatif [nomPropriété => nouvelleValeur]
     *
     * @return $this
     *
     * @example
     * $reflection->setValues([
     *     'name' => 'John',
     *     'age' => 30,
     *     'email' => 'john@example.com'
     * ]);
     */
    public function setValues(array $values): self
    {
        foreach ($values as $property => $value) {
            $this->setValue($property, $value);
        }

        return $this;
    }

    // =========================================================================
    // SECTION: TYPES ET VÉRIFICATIONS DE PROPRIÉTÉS
    // =========================================================================

    /**
     * Vérifie si une propriété a un type déclaré.
     *
     * @example
     * if ($reflection->hasPropertyType('name')) {
     *     // La propriété 'name' a un type déclaré (ex: string, int, etc.)
     * }
     */
    public function hasPropertyType(string $property): bool
    {
        return $this->getProperty($property)->hasType();
    }

    /**
     * Récupère le type d'une propriété.
     *
     * @example
     * $type = $reflection->getPropertyType('name');
     * // Retourne un ReflectionType pour 'string'
     */
    public function getPropertyType(string $property): ?\ReflectionType
    {
        return $this->getProperty($property)->getType();
    }

    /**
     * Récupère le nom du type d'une propriété.
     *
     * @example
     * $typeName = $reflection->getPropertyTypeName('name');
     * // Retourne: 'string'
     */
    public function getPropertyTypeName(string $property): ?string
    {
        return $this->getPropertyType($property)?->getName() ?? null;
    }

    /**
     * Vérifie si une propriété est readonly (PHP 8.1+).
     *
     * @example
     * if ($reflection->isReadonlyProperty('id')) {
     *     // La propriété 'id' est readonly et ne peut être modifiée après initialisation
     * }
     */
    public function isReadonlyProperty(string $property): bool
    {
        if (PHP_VERSION_ID < 80100) {
            return false;
        }

        return $this->getProperty($property)->isReadOnly();
    }

    /**
     * Vérifie si une propriété peut être modifiée.
     *
     * Une propriété est modifiable si elle n'est pas readonly et
     * qu'elle est publique ou protégée.
     *
     * @example
     * if ($reflection->isWritableProperty('name')) {
     *     // La propriété 'name' peut être modifiée
     * }
     */
    public function isWritableProperty(string $property): bool
    {
        if ($this->isReadonlyProperty($property)) {
            return false;
        }

        $refProperty = $this->getProperty($property);
        return $refProperty->isPublic() || $refProperty->isProtected();
    }

    // =========================================================================
    // SECTION: ANNOTATIONS ET DOCBLOCKS
    // =========================================================================

    /**
     * Récupère les annotations (docblocks) d'une propriété ou méthode.
     *
     * @param string $member Nom de la propriété ou méthode
     * @param string $type   Type: 'property' ou 'method'
     *
     * @return array<string, string|array> Annotations parsées
     *
     * @throws InvalidArgumentException Si le type n'est pas valide
     *
     * @example
     * $annotations = $reflection->getAnnotations('name', 'property');
     * // Retourne: ['var' => 'string', 'required' => true]
     */
    public function getAnnotations(string $member, string $type = 'property'): array
    {
        $reflection = match($type) {
            'property' => $this->getProperty($member),
            'method'   => $this->getMethod($member),
            default    => throw new InvalidArgumentException('Type doit être "property" ou "method"')
        };

        return $this->parseDocComment($reflection->getDocComment());
    }

    /**
     * Récupère les annotations (docblocks) d'une propriété.
     *
     * @param string $property Nom de la propriété
     *
     * @return array<string, string|array> Annotations parsées
     *
     * @example
     * $annotations = $reflection->getPropertyAnnotations('email');
     */
    public function getPropertyAnnotations(string $property): array
    {
        return $this->getAnnotations($property, 'property');
    }

    /**
     * Récupère les annotations (docblocks) d'une méthode.
     *
     * @param string $method Nom de la méthode
     *
     * @return array<string, string|array> Annotations parsées
     *
     * @example
     * $annotations = $reflection->getMethodAnnotations('calculate');
     */
    public function getMethodAnnotations(string $method): array
    {
        return $this->getAnnotations($method, 'method');
    }

    /**
     * Parse un docblock pour en extraire les annotations.
     *
     * @param string|false $docComment Le docblock à parser
     *
     * @return array<string, string|array> Annotations parsées
     *
     * @internal Méthode interne utilisée pour parser les docblocks
     */
    private function parseDocComment(string|false $docComment): array
	{
		if (!$docComment) {
			return [];
		}

		// Supprime les balises de commentaire
		$content = trim($docComment);
		$content = preg_replace(['/^\/\*\*\s*/', '/\s*\*\/$/'], '', $content);

		// Divise en lignes et nettoie
		$lines = explode("\n", $content);
		$cleanedLines = [];

		foreach ($lines as $line) {
			$line = trim(preg_replace('/^\s*\*\s*/', '', $line));
			if ($line !== '') {
				$cleanedLines[] = $line;
			}
		}

		// Rejoindre et analyser
		$content = implode("\n", $cleanedLines);

		// Pattern principal
		$pattern = '/
			@([a-zA-Z_\\\\][a-zA-Z0-9_\\\\]*)   # Nom de lannotation
			(?:
				\s*\(([^)]*)\)                  # Contenu entre parenthèses
			)?
			\s*
			(
				(?:
					(?!\s*@)
					.
				)*?
			)
			(?=\s*@|$)
		/sx';

		preg_match_all($pattern, $content, $matches, PREG_SET_ORDER);

		$annotations = [];

		foreach ($matches as $match) {
			$name = $match[1];
			$parenValue = isset($match[2]) ? trim($match[2]) : '';
			$value = trim($match[3]);

			if (!isset($annotations[$name])) {
				$annotations[$name] = [];
			}

			// Traitement spécial pour @var et @return - ne prendre que le type
			if ($name === 'var' || $name === 'return') {
				// Extrait le type (premier mot)
				$parts = preg_split('/\s+/', $value, 2);
				$type = $parts[0] ?? '';
				$rest = $parts[1] ?? '';

				$annotations[$name][] = $type;

				// Si il y a une description après le type
				if (!empty($rest)) {
					// Stocke la description séparément
					if (!isset($annotations[$name . '_desc'])) {
						$annotations[$name . '_desc'] = [];
					}
					$annotations[$name . '_desc'][] = trim($rest);
				}

				// Extrait les annotations inline du reste
				$inlineAnnotations = $this->parseInlineAnnotations($rest);
				foreach ($inlineAnnotations as $inlineName => $inlineValue) {
					if (!isset($annotations[$inlineName])) {
						$annotations[$inlineName] = [];
					}
					$annotations[$inlineName][] = $inlineValue;
				}
			}
			// Traitement spécial pour @param - format: type $nom description
			elseif ($name === 'param') {
				// Pattern: type $variable description
				if (preg_match('/^([^\s]+)\s+(\$\w+)(?:\s+(.*))?$/s', $value, $paramMatches)) {
					$paramType = $paramMatches[1];
					$paramName = $paramMatches[2];
					$paramDesc = $paramMatches[3] ?? '';

					$annotations[$name][] = [
						'type' => $paramType,
						'name' => $paramName,
						'description' => trim($paramDesc)
					];
				} else {
					$annotations[$name][] = $value;
				}
			}
			// Annotation avec parenthèses
			elseif (!empty($parenValue)) {
				$annotations[$name][] = $parenValue;
			}
			// Annotation normale
			else {
				$annotations[$name][] = $value;
			}
		}

		// Simplifie les annotations uniques
		foreach ($annotations as $name => &$values) {
			$values = array_map(function($value) {
				return is_array($value) ? array_map('trim', $value) : trim($value);
			}, $values);
			$values = array_filter($values, fn($v) => $v !== '');

			if (count($values) === 1 && $name !== 'param') {
				$values = reset($values);
			}
		}

		return $annotations;
	}

	/**
	 * Extrait les annotations inline d'une chaîne de texte.
	 *
	 * Cette méthode analyse une chaîne de texte pour trouver et extraire
	 * les annotations PHP qui sont présentes dans le texte. Elle supporte
	 * deux formats d'annotations :
	 * 1. Annotations simples : `@required`, `@deprecated`
	 * 2. Annotations avec valeurs : `@min(18)`, `@max(100)`, `@length(min=2, max=50)`
	 *
	 * @param string $text Texte à analyser pour les annotations inline.
	 *                     Peut contenir du texte libre avec des annotations
	 *                     mélangées.
	 *
	 * @return array<string, string> Tableau associatif des annotations extraites.
	 *                               Clé : nom de l'annotation (sans le @)
	 *                               Valeur : contenu entre parenthèses ou chaîne vide
	 *                                        pour les annotations sans valeur.
	 *
	 * @example
	 * // Texte d'entrée
	 * $text = "Le nom doit être @required et avoir entre @min(2) et @max(50) caractères";
	 *
	 * // Résultat
	 * [
	 *     'required' => '',      // Annotation sans valeur
	 *     'min' => '2',          // Annotation avec valeur simple
	 *     'max' => '50',         // Annotation avec valeur simple
	 * ]
	 *
	 * @example
	 * // Annotations avec valeurs complexes
	 * $text = "Email @email @length(min=5, max=255)";
	 * // Retourne:
	 * [
	 *     'email' => '',
	 *     'length' => 'min=5, max=255'
	 * ]
	 *
	 * @example
	 * // Annotations multiples du même type
	 * $text = "@deprecated depuis la version 2.0 @see NouvelleMethode";
	 * // Retourne:
	 * [
	 *     'deprecated' => 'depuis la version 2.0',
	 *     'see' => 'NouvelleMethode'
	 * ]
	 *
	 * @note Cette méthode ne supporte PAS les annotations sur plusieurs lignes.
	 *       Pour le parsing complet de docblocks multi-lignes, utilisez
	 *       parseDocComment().
	 *
	 * @see parseDocComment() Pour le parsing complet des docblocks PHP
	 * @since 1.0.0
	 * @internal Méthode auxiliaire utilisée uniquement par parseDocComment()
	 */
	private function parseInlineAnnotations(string $text): array
	{
		$annotations = [];

		// Cherche @nom ou @nom(valeur)
		preg_match_all('/@([a-zA-Z_][a-zA-Z0-9_]*)(?:\(([^)]*)\))?/', $text, $matches, PREG_SET_ORDER);

		foreach ($matches as $match) {
			$name = $match[1];
			$value = $match[2] ?? '';

			$annotations[$name] = $value;
		}

		return $annotations;
	}

    // =========================================================================
    // SECTION: ATTRIBUTS PHP 8+
    // =========================================================================

    /**
     * Récupère les attributs d'un élément de classe.
     *
     * @param string|null $member        Nom de la propriété/méthode (null pour la classe elle-même)
     * @param string      $type          Type: 'class', 'property' ou 'method'
     * @param string|null $attributeName Nom spécifique d'attribut à filtrer
     * @param int         $flags         Flags de filtrage
     *
     * @return array<\ReflectionAttribute> Tableau d'attributs
     *
     * @throws InvalidArgumentException Si le type n'est pas valide
     *
     * @example
     * $attributes = $reflection->getAttributesFor('name', 'property');
     */
    public function getAttributesFor(
        string $member = null,
        string $type = 'class',
        ?string $attributeName = null,
        int $flags = 0
    ): array {
        $reflection = match($type) {
			'class'     => $this,
			'property'  => $this->getProperty($member),
			'method'    => $this->getMethod($member),
			'constant'  => $this->getReflectionConstant($member),
			default     => throw new InvalidArgumentException('Type doit être "class", "property", "method", ou "constant"')
        };

        if ($attributeName !== null) {
            return $reflection->getAttributes($attributeName, $flags);
        }

        return $reflection->getAttributes();
    }

    /**
     * Récupère les attributs de la classe.
     *
     * @param string|null $attribute Nom spécifique d'attribut à filtrer
     * @param int         $flags     Flags de filtrage
     *
     * @return array<\ReflectionAttribute> Tableau d'attributs de classe
     *
     * @example
     * $classAttributes = $reflection->getClassAttributes();
     */
    public function getClassAttributes(?string $attribute = null, int $flags = 0): array
    {
        return $this->getAttributesFor(null, 'class', $attribute, $flags);
    }

    /**
     * Récupère les attributs d'une propriété.
     *
     * @param string      $property   Nom de la propriété
     * @param string|null $attribute  Nom spécifique d'attribut à filtrer
     * @param int         $flags      Flags de filtrage
     *
     * @return array<\ReflectionAttribute> Tableau d'attributs de propriété
     *
     * @example
     * $propAttributes = $reflection->getPropertyAttributes('name', Validation::class);
     */
    public function getPropertyAttributes(string $property, ?string $attribute = null, int $flags = 0): array
    {
        return $this->getAttributesFor($property, 'property', $attribute, $flags);
    }

    /**
     * Récupère les attributs d'une méthode.
     *
     * @param string      $method     Nom de la méthode
     * @param string|null $attribute  Nom spécifique d'attribut à filtrer
     * @param int         $flags      Flags de filtrage
     *
     * @return array<\ReflectionAttribute> Tableau d'attributs de méthode
     *
     * @example
     * $methodAttributes = $reflection->getMethodAttributes('process', Route::class);
     */
    public function getMethodAttributes(string $method, ?string $attribute = null, int $flags = 0): array
    {
        return $this->getAttributesFor($method, 'method', $attribute, $flags);
    }

    // =========================================================================
    // SECTION: HIÉRARCHIE ET RELATIONS DE CLASSES
    // =========================================================================

    /**
     * Récupère les traits utilisés par la classe.
     *
     * @return list<string> Noms des traits
     *
     * @example
     * $traits = $reflection->getUsedTraits();
     * // Retourne: ['Loggable', 'Cacheable', 'Serializable']
     */
    public function getUsedTraits(): array
    {
        $traits = parent::getTraits();
        return array_map(fn($trait) => $trait->getName(), $traits);
    }

    /**
     * Récupère les noms des interfaces implémentées.
     *
     * @return list<string> Noms des interfaces
     *
     * @example
     * $interfaces = $reflection->getInterfaceNames();
     * // Retourne: ['JsonSerializable', 'Countable', 'IteratorAggregate']
     */
    public function getInterfaceNames(): array
    {
        $interfaces = parent::getInterfaces();
        return array_map(fn($interface) => $interface->getName(), $interfaces);
    }

    /**
     * Récupère le nom de la classe parente si elle existe.
     *
     * @return string|null Nom de la classe parente ou null
     *
     * @example
     * $parent = $reflection->getParentClassName();
     * // Retourne: 'BaseController' ou null
     */
    public function getParentClassName(): ?string
    {
        $parent = $this->getParentClass();
        return $parent ? $parent->getName() : null;
    }

    /**
     * Vérifie si la classe est un singleton.
     *
     * Une classe est considérée comme un singleton si elle possède
     * une méthode statique publique nommée 'getInstance' ou 'instance'.
     *
     * @return bool True si la classe est un singleton
     *
     * @example
     * if ($reflection->isSingleton()) {
     *     // La classe suit le pattern Singleton
     * }
     */
    public function isSingleton(): bool
    {
        foreach (['getInstance', 'instance'] as $method) {
            if ($this->hasMethod($method) &&
                $this->getMethod($method)->isStatic() &&
                $this->getMethod($method)->isPublic()) {
                return true;
            }
        }

        return false;
    }

    // =========================================================================
    // SECTION: UTILITAIRES ET OPÉRATIONS AVANCÉES
    // =========================================================================

    /**
     * Clone un objet en conservant les valeurs des propriétés privées/protégées.
     *
     * Cette méthode permet de cloner un objet tout en préservant l'état
     * de ses propriétés non-publiques, ce que le clone natif ne fait pas.
     *
     * @param object $object L'objet à cloner
     *
     * @return object Le clone avec toutes les propriétés
     *
     * @example
     * $clone = ReflectionClass::cloneWithPrivateProperties($original);
     */
    public static function cloneWithPrivateProperties(object $object): object
    {
        $reflection = new self($object);
        $clone = clone $object;
        $cloneReflection = new self($clone);

        $properties = $reflection->getProperties(ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED);

        foreach ($properties as $property) {
            $property->setAccessible(true);
            $value = $property->getValue($object);
            $cloneReflection->setValue($property->getName(), $value);
        }

        return $clone;
    }

    /**
     * Réinitialise toutes les propriétés statiques de la classe.
     *
     * Cette méthode est particulièrement utile pour les tests unitaires
     * où il faut réinitialiser l'état statique entre les tests.
     *
     * @return void
     *
     * @example
     * $reflection->resetStaticProperties();
     * // Toutes les propriétés statiques sont remises à leurs valeurs par défaut
     */
    public function resetStaticProperties(): void
    {
        $properties = $this->getProperties(ReflectionProperty::IS_STATIC);
        foreach ($properties as $property) {
			$value = $property->hasDefaultValue() ? $property->getDefaultValue() : null;
			$property->setValue(null, $value);
        }
    }
}
