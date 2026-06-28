<?php

namespace BlitzPHP\Utilities\Resources;

use BlitzPHP\Contracts\Pagination\LengthAwarePaginator;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Contracts\Support\Jsonable;
use BlitzPHP\Traits\Support\ForwardsCalls;
use BlitzPHP\Utilities\Data\DataTransfertObject;
use BlitzPHP\Utilities\Iterable\Collection;
use BlitzPHP\Utilities\Iterable\Arr;
use JsonSerializable;
use ReflectionClass;

/**
 * Classe Resource
 *
 * Représente une ressource API inspirée de Laravel JsonResource.
 * Permet de transformer des données en une structure JSON cohérente.
 *
 * @property array $additional Données supplémentaires à ajouter au niveau supérieur
 * @property array $with Métadonnées à ajouter au niveau supérieur
 * @property array $meta Métadonnées personnalisées
 * @property array $attributes Les attributs de la ressource
 */
abstract class Resource extends DataTransfertObject implements Arrayable, Jsonable, JsonSerializable
{
    use ConditionallyLoadsAttributes;
	use ForwardsCalls;

    /**
     * Indique si les valeurs nulles doivent être conservées dans la sortie.
     */
    protected bool $preserveNullValues = false;

    /**
     * Indique si les clés doivent être préservées dans la sortie.
     */
    protected bool $preserveKeys = false;

    /**
     * Les données supplémentaires qui doivent être ajoutées au niveau supérieur.
     */
    protected array $with = [];

    /**
     * Les métadonnées supplémentaires ajoutées lors de la construction de la réponse.
     */
    protected array $additional = [];

    /**
     * Le wrapper par défaut pour les données.
     */
    protected ?string $wrap = null;

    /**
     * Métadonnées de pagination.
     */
    protected ?array $pagination = null;

    /**
     * Métadonnées personnalisées.
     */
    protected ?array $meta = null;

    /**
     * Indique si la ressource est une collection.
     */
    protected bool $isCollection = false;

	/**
	 * La ressource sous-jacente
	 */
 	protected object $resource;

    /**
     * Constructeur de la ressource.
     *
     * @param mixed $resource Les données à transformer
     */
    public function __construct(mixed $resource = [])
    {
        // Gestion de la pagination
        if ($resource instanceof LengthAwarePaginator) {
            $this->pagination   = static::extractPaginationData($resource);
            $resource           = $resource->items();
            $this->isCollection = true;
        }

        // Gestion des collections
        if (is_array($resource) && !Arr::isAssoc($resource)) {
            $this->isCollection = true;
        }

        parent::__construct(is_array($resource) ? $resource : $this->parseResource($resource));
    }

    /**
     * Crée une nouvelle instance de ressource.
     *
     * @param mixed $resource Les données à transformer
     */
    public static function make(mixed $resource): static
    {
        return new static($resource);
    }

    /**
     * Crée une nouvelle instance de ressource pour une collection.
     *
     * @param mixed $resources Les données à transformer en collection
	 *
     * @return ResourceCollection
     */
    public static function collection(mixed $resources)
    {
        return new ResourceCollection(static::class, $resources);
    }

    /**
     * Crée une collection de ressources à partir d'une collection ou d'un tableau.
     */
    public static function fromCollection(Collection|array $collection): ResourceCollection
    {
        return static::collection($collection);
    }

    /**
     * Ajoute des données supplémentaires au niveau supérieur.
     *
     * @param array $data Les données supplémentaires à ajouter
     */
    public function additional(array $data): static
    {
        $this->additional = array_merge($this->additional, $data);

        return $this;
    }

    /**
     * Ajoute des métadonnées au niveau supérieur.
     */
    public function with(array $data): static
    {
        $this->with = array_merge($this->with, $data);

		return $this;
    }

    /**
     * Définit les métadonnées personnalisées.
     */
    public function meta(array $data): static
    {
        $this->meta = $data;

		return $this;
    }

    /**
     * Ajoute des métadonnées personnalisées.
     */
    public function addMeta(array $data): static
    {
        $this->meta = array_merge($this->meta ?? [], $data);

		return $this;
    }

    /**
     * Définit le wrapper pour les données.
     */
    public function wrap(?string $wrap): static
    {
        $this->wrap = $wrap;

		return $this;
    }

    /**
     * Définit si les valeurs nulles doivent être préservées.
     */
    public function preserveNullValues(bool $preserve = true): static
    {
        $this->preserveNullValues = $preserve;

		return $this;
    }

    /**
     * Définit si les clés doivent être préservées.
     */
    public function preserveKeys(bool $preserve = true): static
    {
        $this->preserveKeys = $preserve;

		return $this;
    }

    /**
     * Convertit la ressource en tableau.
     *
     * Structure finale : { data|wrapper, meta, pagination }
     *
     * @return array Le tableau représentant la ressource
     */
    public function toArray(): array
    {
        $data = $this->prepareData();
		$data = $this->filter($data);

        $result = [];

        // Ajouter les données (avec wrapper si défini)
        if ($this->wrap) {
            $result[$this->wrap] = $data;
        } else {
            $result = $data;
        }

        // Ajouter les métadonnées
        if ($this->with !== [] || $this->meta !== []) {
            $result['meta'] = array_merge(
                $result['meta'] ?? [],
                $this->with,
                $this->meta
            );
        }

        if (!is_null($this->pagination)) {
            $result['pagination'] = $this->pagination;
        }

        if ($this->additional !== []) {
            $result = array_merge($result, $this->additional);
        }

        return $result;
    }

    /**
     * Retourne la ressource sous-jacente.
     */
    public function getResource(): mixed
    {
        return $this->resource ?? $this->attributes;
    }

    /**
     * Vérifie si la ressource a une relation chargée.
     */
    public function relationLoaded(string $relation): bool
    {
        return isset($this->attributes[$relation]) ||
               (isset($this->resource) && method_exists($this->resource, 'relationLoaded') &&
                $this->resource->relationLoaded($relation));
    }

    /**
     * Vérifie si une clé existe dans la ressource.
     */
    protected function offsetExists(string $key): bool
    {
        return isset($this->attributes[$key]) ||
               property_exists($this, $key) ||
               method_exists($this, $this->getComputedAttributeName($key));
    }

    /**
     * Prépare les données avant la sérialisation.
     */
    protected function prepareData(): array
    {
        // Récupérer toutes les données
        $data = parent::toArray();

        // Filtrer les valeurs nulles si nécessaire
        if (!$this->preserveNullValues) {
            $data = array_filter($data, fn($value) => $value !== null);
        }

        // Formater les valeurs
        foreach ($data as $key => $value) {
            $data[$key] = $this->formatValue($value);
        }

        // Ajouter les propriétés calculées
        foreach ($this->appends as $computed) {
            $data[$computed] = $this->__get($computed);
        }

        return $data;
    }

    /**
     * Formate une valeur pour la sérialisation.
     */
    protected function formatValue($value, array $visited = []): mixed
    {
        // Si c'est une ressource, on la convertit en tableau
        if ($value instanceof self) {
			// Protection contre la récursion infinie
            $hash = spl_object_hash($value);
            if (in_array($hash, $visited, true)) {
                return null;
            }
            $visited[] = $hash;
            return $value->toArray();
        }

        // Si c'est une collection de ressources, on la convertit en tableau
        if ($value instanceof ResourceCollection) {
            return $value->toArray();
        }

        // Si c'est une collection, on formate récursivement
        if ($value instanceof Collection) {
            return $value->map(fn ($item) => $this->formatValue($item, $visited))->all();
        }

        // Si c'est un tableau, on formate récursivement
        if (is_array($value)) {
            return array_map(fn ($item) => $this->formatValue($item, $visited), $value);
        }

        // Si l'objet a une méthode toArray, on l'utilise
        if (is_object($value) && method_exists($value, 'toArray')) {
            return $value->toArray();
        }

        return $value;
    }

    /**
     * Parse une ressource non-tableau.
     */
    protected function parseResource(mixed $resource): array
    {
		if (is_object($resource)) {
			$this->resource = $resource;
		}

        if ($resource instanceof Arrayable) {
            return $resource->toArray();
        }

        if ($resource instanceof Jsonable) {
            return json_decode($resource->toJson(), true) ?? [];
        }

        if (is_object($resource) && method_exists($resource, 'toArray')) {
            return $resource->toArray();
        }

        if (is_object($resource)) {
            return get_object_vars($resource);
        }

        return (array) $resource;
    }

    /**
     * Extrait les données de pagination.
     *
     * Cette méthode peut être surchargée dans les classes enfants pour personnaliser
     * les données de pagination retournées.
     */
    public static function extractPaginationData(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page'   => $paginator->currentPage(),
            'per_page'       => $paginator->perPage(),
            'total'          => $paginator->total(),
            'last_page'      => $paginator->lastPage(),
            'from'           => $paginator->firstItem(),
            'to'             => $paginator->lastItem(),
            'path'           => $paginator->path(),
            'first_page_url' => $paginator->url(1),
            'last_page_url'  => $paginator->url($paginator->lastPage()),
            'next_page_url'  => $paginator->nextPageUrl(),
            'prev_page_url'  => $paginator->previousPageUrl(),
        ];
    }

    /**
     * Récupère toutes les propriétés publiques et attributs.
     */
    public function all(): array
    {
        $data = parent::all();

        // Ajouter les propriétés qui ont des getters
        foreach ($this->getAccessibleProperties() as $property) {
            if (!isset($data[$property]) && method_exists($this, 'get' . ucfirst($property) . 'Attribute')) {
                $data[$property] = $this->{'get' . ucfirst($property) . 'Attribute'}();
            }
        }

        return $data;
    }

    /**
     * Récupère les propriétés accessibles.
     */
    protected function getAccessibleProperties(): array
    {
        $reflection = new ReflectionClass($this);
        $properties = [];

        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic()) {
                $properties[] = $property->getName();
            }
        }

        return $properties;
    }

    /**
     * Vérifie si la ressource est une collection.
     */
    public function isCollection(): bool
    {
        return $this->isCollection;
    }

    /**
     * Retourne les données brutes.
     */
    public function raw(): array
    {
        return $this->attributes;
    }

    /**
     * Méthode magique pour les appels de méthode.
     */
    public function __call(string $method, array $parameters): mixed
    {
        // Vérifier si c'est un accesseur
        if (str_starts_with($method, 'get') && str_ends_with($method, 'Attribute')) {
            $property = strtolower(substr($method, 3, -9));
            if (isset($this->attributes[$property])) {
                return $this->attributes[$property];
            }
            // Si la propriété n'existe pas, retourner null
            return null;
        }

        // Vérifier si la méthode existe sur la ressource
        if (isset($this->resource)) {
			return $this->forwardCallTo($this->resource, $method, $parameters);
        }

        throw new \BadMethodCallException(sprintf(
            'La méthode %s::%s n\'existe pas.',
            static::class,
            $method
        ));
    }
}
