<?php

/**
 * This file is part of Blitz PHP framework.
 *
 * (c) 2022 Dimitri Sitchet Tomkeu <devcode.dst@gmail.com>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace BlitzPHP\Utilities\Data;

use BlitzPHP\Annotations\AnnotationReader;
use BlitzPHP\Contracts\Support\Arrayable;
use BlitzPHP\Contracts\Support\Jsonable;
use BlitzPHP\Utilities\DateTime\Date;
use BlitzPHP\Utilities\Helpers;
use BlitzPHP\Utilities\Iterable\Arr;
use BlitzPHP\Utilities\Iterable\Collection;
use BlitzPHP\Utilities\String\Text;
use InvalidArgumentException;
use JsonSerializable;
use ReflectionClass;
use ReflectionProperty;
use Stringable;

class DataTransfertObject implements Arrayable, Jsonable
{
    /**
     * Donnees originales sans alteration quelconque
     *
     * @var array<string, mixed>
     */
    private array $original = [];

    /**
     * Proprietes a ne pas afficher lors de la serialisation
     *
     * @var list<string>
     */
    protected array $except = [];

    /**
     * Proprietes a afficher lors de la serialisation
     *
     * @var list<string>
     */
    protected array $only = [];

    /**
     * Proprietes calculées qui doivent etre ajoutés aux données lors de la serialisation
     *
     * @var list<string>
     */
    protected array $appends = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(protected array $attributes = [])
    {
        foreach ($attributes as $key => $value) {
            $this->parseProperty($key, $value);
        }
    }

    /**
     * Restreindre les donnees serialisable du DTO en un sous-ensemble specifique
     */
    public function only(string ...$keys): static
    {
        $dto = clone $this;

        $dto->only = [...$this->only, ...$keys];

        return $dto;
    }

    /**
     * Exclure un sous-ensemble specifique des donnees serialisable du DTO
     */
    public function except(string ...$keys): static
    {
        $dto = clone $this;

        $dto->except = [...$this->except, ...$keys];

        return $dto;
    }

    /**
     * Cloner les donnees du DTO dans un nouveau DTO tout en ajoutant des elements
     */
    public function clone(...$args): static
    {
        return static::make(array_merge($this->toArray(), $args));
    }

    /**
     * Cree un tableau de DTO a partir d'un tableau d'element
     *
     * @param list<list<mixed>> $arrayOfattributes Tableau a 2 dimensions des elements a transformer en DTO
     *
     * @return list<static>
     */
    public static function arrayOf(array $arrayOfattributes): array
    {
        return static::collection($arrayOfattributes)->all();
    }

    /**
     * Cree une collection de DTO a partir d'un tableau d'element
     *
     * @param list<list<mixed>> $arrayOfattributes Tableau a 2 dimensions des elements a transformer en DTO
     *
     * @return Collection<static>
     */
    public static function collection(array $arrayOfattributes): Collection
    {
        if (empty($arrayOfattributes)) {
            return Helpers::collect($arrayOfattributes);
        }

        if (Arr::dimensions($arrayOfattributes) < 2) {
            throw new InvalidArgumentException();
        }

        return Helpers::collect($arrayOfattributes)->map(static fn (array $attributes) => static::make($attributes));
    }

    /**
     * Cree une instance du DTO
     */
    public static function make(array $attributes): static
    {
        return new static($attributes);
    }

    /**
     * Recupere toutes les donnees du DTO
     */
    public function all(): array
    {
        $data = $this->attributes;

        foreach ($this->getProperties() as $property) {
            if ($property->isStatic()) {
                continue;
            }

            $property->setAccessible(true);
            if (! $property->isInitialized($this)) {
                continue;
            }

            $data[$property->getName()] = $property->getValue($this);
        }

        return $data;
    }

    /**
     * Converti l'instance en tableau pour la serialisation
     */
    public function toArray(): array
    {
        if (count($this->only)) {
            $data = Arr::only($this->all(), $this->only);
        } else {
            $data = Arr::except($this->all(), $this->except);
        }

        $data = Arr::except($data, array_keys($this->attributes));

        foreach ($this->appends as $computed) {
            $data[$computed] = $this->__get($computed);
        }

        foreach ($data as $key => $value) {
            $data[$key] = $this->format($value);
        }

        return $data;
    }

    /**
     * Converti l'instance en sa representation json
     */
    public function toJson(int $options = 0): string
    {
        return json_encode($this->toArray(), $options);
    }

    public function __get($name)
    {
        if (isset($this->attributes[$name])) {
            return $this->attributes[$name];
        }

        if (method_exists($this, $method = $this->getComputedAttributeName($name))) {
            return $this->{$method}();
        }

        return null;
    }

    public function __set($name, $value)
    {
        $this->attributes[$name] = $value;
    }

    /**
     * Transforme les valeurs a affecter aux proprietes lors de leur initialisation
     */
    protected function transform(string $attribute, mixed $value): mixed
    {
        return $value;
    }

    /**
     * Cast une valeur en fonction du type desiré par le developpeur
     */
    protected function cast(mixed $value, string $type): mixed
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            'int', 'integer' => (int) $value,
            'string' => (string) $value,
            'float', 'number', 'double' => (float) $value,
            'object' => (object) $value ,
            'bool' , 'boolean' => (bool) $value ,
            default => class_exists($type) ? new $type($value) : $value,
        };
    }

    /**
     * Format les valeurs pour la serialisation
     */
    protected function format(mixed $value): mixed
    {
        if (is_array($value)) {
            $value = Helpers::collect($value);
        }

        if ($value instanceof Collection) {
            return $value->map(fn ($v) => $this->format($v))->all();
        }

        if ($value instanceof Date) {
            return $value->format('Y-m-d H:i:s');
        }
        if ($value instanceof Arrayable) {
            return $value->toArray();
        }
        if ($value instanceof Jsonable) {
            return $value->toJson();
        }
        if ($value instanceof Stringable) {
            return $value->__toString();
        }
        if ($value instanceof JsonSerializable) {
            return $value->jsonSerialize();
        }

        return $value;
    }

    /**
     * Renvoi toutes les proprietes publique de l'instance en cours de traitement
     *
     * @return list<ReflectionProperty>
     */
    protected function getProperties(): array
    {
        return (new ReflectionClass($this))->getProperties(ReflectionProperty::IS_PUBLIC);
    }

    /**
     * Cree le nom de la methode a utiliser pour obtenir la valeur d'une proprieté calculée
     */
    private function getComputedAttributeName(string $name): string
    {
        return Text::camel('get_' . $name . '_attribute');
    }

    /**
     * Verifie si une cle existe comme etant propriete de la classe et le caste au bon type le cas echeant
     */
    private function parseProperty(string $key, mixed $value): void
    {
        $this->original[$key] = $value;

        if (! property_exists($this, $key)) {
            return;
        }

        unset($this->attributes[$key]);

        $property    = new ReflectionProperty($this, $key);
        $type        = $property->getType()?->getName();
        $annotations = AnnotationReader::formProperty($property, $key, '@var');

        if (empty($value) && $property->hasDefaultValue()) {
            $value = $property->getDefaultValue();
        }

        $value = $this->transform($key, $value);

        if ((in_array($type, [null, 'null', 'mixed'], true) && [] === $annotations) || ($value === null && $property->getType()?->allowsNull())) {
            $this->{$key} = $value;

            return;
        }

        $subtype        = $type;
        $annotationType = isset($annotations[0]) ? $annotations[0]->type : 'mixed';

        if (null !== $type && is_a($type, Collection::class, true)) {
            $subtype = 'collection';
        }

        if (str_ends_with($annotationType, '[]')) {
            $annotationType = str_replace('[]', '', $annotationType);

            if (in_array($type, ['null', null, 'mixed'], true)) {
                $subtype = 'collection';
            } else {
                $subtype = 'array';
                $type    = $annotationType;
            }
        }

        if (in_array($type, [null, 'null', 'mixed'], true)) {
            $type = $annotationType;
        }

        if (class_exists($type) && ! in_array($subtype, ['array', 'collection'], true)) {
            $this->{$key} = $this->cast($value, $type);

            return;
        }

        $result = Helpers::collect($value)->map(fn ($v) => $this->cast($v, $type));

        $this->{$key} = match ($subtype) {
            'collection' => $result,
            'array'      => $result->all(),
            default      => $result->first()
        };
    }
}
