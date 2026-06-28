<?php

namespace BlitzPHP\Utilities\Resources;

use BlitzPHP\Contracts\Pagination\LengthAwarePaginator;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Utilities\Iterable\Collection;
use JsonSerializable;

/**
 * Classe ResourceCollection
 *
 * Représente une collection de ressources API.
 * Permet de transformer une collection de données en une structure JSON cohérente.
 */
class ResourceCollection implements Arrayable, JsonSerializable
{
    /**
     * La classe Resource à utiliser.
     */
    protected string $resourceClass;

    /**
     * Les données à transformer.
     */
    protected mixed $data;

    /**
     * Les données supplémentaires au niveau supérieur.
     */
    protected array $additional = [];

    /**
     * Les métadonnées au niveau supérieur.
     */
    protected array $with = [];

    /**
     * Les métadonnées personnalisées.
     */
    protected ?array $meta = null;

    /**
     * Le wrapper pour les données.
     */
    protected ?string $wrap = null;

    /**
     * Indique si les clés doivent être préservées.
     */
    protected bool $preserveKeys = false;

    /**
     * Indique si les valeurs nulles doivent être conservées.
     */
    protected bool $preserveNullValues = false;

    /**
     * Métadonnées de pagination.
     */
    protected ?array $pagination = null;

    /**
     * Constructeur de la collection de ressources.
     *
     * @param string $resourceClass La classe Resource à utiliser
     * @param mixed $data Les données à transformer
     */
    public function __construct(string $resourceClass, mixed $data)
    {
        $this->resourceClass = $resourceClass;
        $this->data = $data;
    }

    /**
     * Ajoute des données supplémentaires au niveau supérieur.
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
     * Définit si les clés doivent être préservées.
     */
    public function preserveKeys(bool $preserve = true): static
    {
        $this->preserveKeys = $preserve;

        return $this;
    }

    /**
     * Définit si les valeurs nulles doivent être conservées.
     */
    public function preserveNullValues(bool $preserve = true): static
    {
        $this->preserveNullValues = $preserve;

        return $this;
    }

    /**
     * Convertit la collection de ressources en tableau.
     *
     * Structure finale : { data, meta, pagination }
     *
     * @return array Le tableau représentant la collection
     */
    public function toArray(): array
    {
        // Transformer les données en ressources
        $data = $this->transformData();

        // Construire la structure finale
        $result = [];

        // Ajouter les données (avec wrapper si défini)
        if ($this->wrap) {
            $result[$this->wrap] = $data;
        } else {
            $result['data'] = $data;
        }

        // Ajouter les métadonnées (with)
        if (!empty($this->with)) {
            $result['meta'] = array_merge($result['meta'] ?? [], $this->with);
        }

        // Ajouter les métadonnées personnalisées (meta)
        if (!empty($this->meta)) {
            $result['meta'] = array_merge($result['meta'] ?? [], $this->meta);
        }

        // Ajouter la pagination
        if (!is_null($this->pagination)) {
            $result['pagination'] = $this->pagination;
        }

        // Ajouter les données supplémentaires
        if (!empty($this->additional)) {
            $result = array_merge($result, $this->additional);
        }

        return $result;
    }

    /**
     * Spécifie les données à sérialiser en JSON.
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Transforme les données en collection de ressources.
     */
    protected function transformData(): array
    {
        // Gestion de la pagination
        if ($this->data instanceof LengthAwarePaginator) {
            $items = $this->data->items();
            $this->pagination = $this->extractPaginationData($this->data);
        } else {
            $items = $this->data instanceof Collection ? $this->data->all() : $this->data;
            $this->pagination = null;
        }

        // Transformation des items en ressources
        $resources = [];
        $index = 0;

        foreach ($items as $key => $item) {
            /** @var Resource $resource */
            $resource = new $this->resourceClass($item);

            // Appliquer les configurations à chaque ressource
            if ($this->preserveKeys) {
                $resource->preserveKeys(true);
            }

            if ($this->preserveNullValues) {
                $resource->preserveNullValues(true);
            }

            // Utiliser la clé originale ou un index numérique
            $resources[$this->preserveKeys ? $key : $index] = $resource->toArray();
            $index++;
        }

        return $resources;
    }

    /**
     * Extrait les données de pagination.
     */
    protected function extractPaginationData(LengthAwarePaginator $paginator): array
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
     * Récupère les items en tant que collection.
     */
    public function collect(): Collection
    {
        return Collection::make($this->data);
    }

    /**
     * Compte les items.
     */
    public function count(): int
    {
        return count($this->data);
    }

    /**
     * Retourne les données brutes.
     */
    public function raw(): mixed
    {
        return $this->data;
    }
}
