<?php

namespace BlitzPHP\Utilities\Resources;

use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\String\Stringable;

/**
 * Trait ConditionallyLoadsAttributes
 *
 * Permet de charger conditionnellement des attributs dans les ressources.
 */
trait ConditionallyLoadsAttributes
{
    /**
     * Filtre les données données, en supprimant les valeurs optionnelles.
     */
    protected function filter(array $data): array
    {
        $index = -1;

        foreach ($data as $key => $value) {
            $index++;

            // Si la valeur est un tableau, on le filtre récursivement
            if (is_array($value)) {
                $data[$key] = $this->filter($value);
                continue;
            }

            // Si la valeur est une ressource vide, on la définit à null
			if ($value instanceof self) {
				$data[$key] = empty($value->getResource()) ? null : $value;
			}
        }

        return $this->removeMissingValues($data);
    }

    /**
     * Fusionne les données données à l'index spécifié.
     *
     * @param array $data Les données originales
     * @param int $index L'index où fusionner
     * @param array $merge Les données à fusionner
     * @param bool $numericKeys Indique si les clés sont numériques
     * @return array Les données fusionnées
     */
    protected function mergeData(array $data, int $index, array $merge, bool $numericKeys): array
    {
        if ($numericKeys) {
            return $this->removeMissingValues(array_merge(
                array_merge(array_slice($data, 0, $index, true), $merge),
                $this->filter(array_values(array_slice($data, $index + 1, null, true)))
            ));
        }

        return $this->removeMissingValues(array_slice($data, 0, $index, true) +
                $merge +
                $this->filter(array_slice($data, $index + 1, null, true)));
    }

    /**
     * Supprime les valeurs manquantes des données filtrées.
     */
    protected function removeMissingValues(array $data): array
    {
        $numericKeys = true;

        // Vérifie si toutes les clés sont numériques
        foreach ($data as $key => $value) {
            $numericKeys = $numericKeys && is_numeric($key);
        }

        // Si la préservation des clés est activée, on retourne les données telles quelles
        if (property_exists($this, 'preserveKeys') && $this->preserveKeys === true) {
            return $data;
        }

        // Réindexe les tableaux numériques
        return $numericKeys ? array_values($data) : $data;
    }

    /**
     * Récupère une valeur si la "condition" donnée est vraie.
     */
    protected function when(bool $condition, mixed $value, mixed $default = null): mixed
    {
        if ($condition) {
            return Helpers::value($value);
        }

        return Helpers::value($default);
    }

    /**
     * Récupère une valeur si la "condition" donnée est fausse.
     */
    protected function unless(bool $condition, mixed $value, mixed $default = null): mixed
    {
        $arguments = func_num_args() === 2 ? [$value] : [$value, $default];

        return $this->when(! $condition, ...$arguments);
    }

    /**
     * Fusionne une valeur dans le tableau.
     */
    protected function merge(mixed $value): mixed
    {
        return $this->mergeWhen(true, $value);
    }

    /**
     * Fusionne une valeur si la condition donnée est vraie.
     */
    protected function mergeWhen(bool $condition, mixed $value, mixed $default = null): mixed
    {
        if ($condition) {
            return Helpers::value($value);
        }

        return Helpers::value($default);
    }

    /**
     * Fusionne une valeur sauf si la condition donnée est vraie.
     */
    protected function mergeUnless(bool $condition, mixed $value, mixed $default = null): mixed
    {
        $arguments = func_num_args() === 2 ? [$value] : [$value, $default];

        return $this->mergeWhen(! $condition, ...$arguments);
    }

    /**
     * Fusionne les attributs donnés.
     */
    protected function attributes(array $attributes): array
    {
        $data = [];

        foreach ($attributes as $attribute) {
            // Récupère l'attribut depuis la ressource si disponible
            if (isset($this->resource) && method_exists($this->resource, 'getAttribute')) {
                $data[$attribute] = $this->resource->getAttribute($attribute);
            } elseif (isset($this->attributes[$attribute])) {
                $data[$attribute] = $this->attributes[$attribute];
            }
        }

        return $data;
    }

    /**
     * Récupère un attribut s'il existe sur la ressource.
     */
    protected function whenHas(string $attribute, mixed $value = null, mixed $default = null): mixed
    {
        // Vérifie si l'attribut existe
        $hasAttribute = isset($this->attributes[$attribute]) ||
                       (isset($this->resource) && method_exists($this->resource, 'getAttribute') &&
                        !is_null($this->resource->getAttribute($attribute)));

        if (!$hasAttribute) {
            return Helpers::value($default);
        }

        // Récupère la valeur de l'attribut
        $attributeValue = $this->attributes[$attribute] ??
                         ($this->resource->getAttribute($attribute) ?? null);

        // Si un seul argument est passé, retourne la valeur directement
        if (func_num_args() === 1) {
            return $attributeValue;
        }

        // Sinon, évalue la valeur avec l'attribut en paramètre
        return Helpers::value($value, $attributeValue);
    }

    /**
     * Récupère un attribut s'il est nul.
     */
    protected function whenNull(mixed $value, mixed $default = null): mixed
    {
        $arguments = func_num_args() == 1 ? [$value] : [$value, $default];

        return $this->when(is_null($value), ...$arguments);
    }

    /**
     * Récupère un attribut s'il n'est pas nul.
     */
    protected function whenNotNull(mixed $value, mixed $default = null): mixed
    {
        $arguments = func_num_args() == 1 ? [$value] : [$value, $default];

        return $this->when(! is_null($value), ...$arguments);
    }

    /**
     * Récupère un accesseur lorsqu'il a été ajouté.
     */
    protected function whenAppended(string $attribute, mixed $value = null, mixed $default = null): mixed
    {
        // Vérifie si l'attribut a été ajouté
        $hasAppended = in_array($attribute, $this->appends ?? []) ||
                       (isset($this->resource) && method_exists($this->resource, 'hasAppended') &&
                        $this->resource->hasAppended($attribute));

        if (!$hasAppended) {
            return Helpers::value($default);
        }

        // Récupère la valeur de l'attribut
        $attributeValue = $this->attributes[$attribute] ?? $this->resource->$attribute ?? null;

        if (func_num_args() === 1) {
            return $attributeValue;
        }

        return Helpers::value($value, $attributeValue);
    }

    /**
     * Récupère une relation si elle a été chargée.
     */
    protected function whenLoaded(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        // Vérifie si la relation est chargée
        $isLoaded = isset($this->attributes[$relationship]) ||
                    (isset($this->resource) && method_exists($this->resource, 'relationLoaded') &&
                     $this->resource->relationLoaded($relationship));

        if (!$isLoaded) {
            return Helpers::value($default);
        }

        // Récupère la valeur de la relation
        $loadedValue = $this->attributes[$relationship] ?? $this->resource->{$relationship} ?? null;

        if (func_num_args() === 1) {
            return $loadedValue;
        }

        if ($loadedValue === null) {
            return null;
        }

        return Helpers::value($value, $loadedValue);
    }

    /**
     * Récupère un compteur de relation s'il existe.
     */
    protected function whenCounted(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        // Construit le nom de l'attribut du compteur
        $attribute = (new Stringable($relationship))->snake()->finish('_count')->value();

        // Vérifie si le compteur existe
        if (!isset($this->attributes[$attribute])) {
            return Helpers::value($default);
        }

        if (func_num_args() === 1) {
            return $this->attributes[$attribute];
        }

        if ($this->attributes[$attribute] === null) {
            return null;
        }

        return Helpers::value($value, $this->attributes[$attribute]);
    }

    /**
     * Récupère une valeur agrégée d'une relation si elle existe.
     */
    protected function whenAggregated(string $relationship, string $column, string $aggregate, mixed $value = null, mixed $default = null): mixed
    {
        // Construit le nom de l'attribut de l'agrégation
        $attribute = (new Stringable($relationship))
            ->snake()
            ->append('_')
            ->append($aggregate)
            ->append('_')
            ->finish($column)
            ->value();

        // Vérifie si l'agrégation existe
        if (!isset($this->attributes[$attribute])) {
            return Helpers::value($default);
        }

        if (func_num_args() === 3) {
            return $this->attributes[$attribute];
        }

        if ($this->attributes[$attribute] === null) {
            return null;
        }

        return Helpers::value($value, $this->attributes[$attribute]);
    }

    /**
     * Récupère une vérification d'existence de relation si elle existe.
     */
    protected function whenExistsLoaded(string $relationship, mixed $value = null, mixed $default = null): mixed
    {
        // Construit le nom de l'attribut d'existence
        $attribute = (new Stringable($relationship))->snake()->finish('_exists')->value();

        // Vérifie si l'existence est vérifiée
        if (!isset($this->attributes[$attribute])) {
            return Helpers::value($default);
        }

        if (func_num_args() === 1) {
            return $this->attributes[$attribute];
        }

        if ($this->attributes[$attribute] === null) {
            return null;
        }

        return Helpers::value($value, $this->attributes[$attribute]);
    }

    /**
     * Exécute un callback si la table pivot donnée a été chargée.
     */
    protected function whenPivotLoaded(string $table, mixed $value, mixed $default = null): mixed
    {
        return $this->whenPivotLoadedAs('pivot', $table, $value, $default);
    }

    /**
     * Exécute un callback si la table pivot donnée avec un accesseur personnalisé a été chargée.
     */
    protected function whenPivotLoadedAs(string $accessor, string $table, mixed $value, mixed $default = null): mixed
    {
        return $this->when(
            $this->hasPivotLoadedAs($accessor, $table),
            $value,
            $default
        );
    }

    /**
     * Détermine si la ressource a la table pivot spécifiée chargée.
     */
    protected function hasPivotLoaded(string $table): bool
    {
        return $this->hasPivotLoadedAs('pivot', $table);
    }

    /**
     * Détermine si la ressource a la table pivot spécifiée avec un accesseur personnalisé chargée.
     */
    protected function hasPivotLoadedAs(string $accessor, string $table): bool
    {
        // Récupère la valeur du pivot
        $pivot = $this->attributes[$accessor] ?? null;

        if (!$pivot) {
            return false;
        }

        // Vérifie si le pivot est une instance de la table ou a le même nom de table
        return $pivot instanceof $table ||
               (method_exists($pivot, 'getTable') && $pivot->getTable() === $table);
    }
}
