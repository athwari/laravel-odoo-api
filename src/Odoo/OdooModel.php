<?php

namespace Athwari\LaravelOdooApi\Odoo;

use Athwari\LaravelOdooApi\Attributes\BelongsTo;
use Athwari\LaravelOdooApi\Attributes\Field;
use Athwari\LaravelOdooApi\Attributes\HasMany;
use Athwari\LaravelOdooApi\Attributes\Model;
use Athwari\LaravelOdooApi\Exceptions\ConfigurationException;
use Athwari\LaravelOdooApi\Exceptions\OdooModelException;
use Athwari\LaravelOdooApi\Exceptions\UndefinedPropertyException;
use Athwari\LaravelOdooApi\Odoo;
use Athwari\LaravelOdooApi\Odoo\Mapping\HasFields;
use Athwari\LaravelOdooApi\Odoo\Models\LazyHasMany;
use Athwari\LaravelOdooApi\Odoo\Models\ModelQuery;
use ReflectionClass;

class OdooModel
{
    use HasFields;

    /**
     * Bound Odoo client(s), keyed by model class name.
     *
     * Keyed per-class rather than a single shared static so that, in the
     * common case, OdooModel::boot() still works as a zero-config
     * convenience for the single-connection case, while leaving room for
     * a future per-class or per-connection binding without another
     * breaking change to this layer.
     *
     * @var array<class-string, Odoo>
     */
    private static array $bindings = [];

    /**
     * Bind an Odoo client for this model class (and, by default, every
     * OdooModel subclass that doesn't have its own explicit binding).
     *
     * Typically called once from a service provider's boot() method.
     */
    public static function boot(Odoo $odoo): void
    {
        self::$bindings[static::class] = $odoo;
    }

    private static function odoo(): Odoo
    {
        return self::$bindings[static::class]
            ?? self::$bindings[self::class]
            ?? throw new ConfigurationException(
                static::class.' has no bound Odoo client. Call OdooModel::boot($odoo) first '
                .'(typically from a service provider), or '
                .static::class.'::boot($odoo) to bind this model class specifically.'
            );
    }

    public static function listFields(?array $fields = null): object
    {
        return self::odoo()->fieldsGet(static::model(), $fields);
    }

    public static function find(int $id): ?static
    {
        $odooInstance = self::odoo()->find(static::model(), $id, static::fieldNames());

        if ($odooInstance === null) {
            return null;
        }

        return static::hydrate($odooInstance);
    }

    /**
     * @param  int[]  $ids
     * @return static[]
     */
    public static function read(array $ids): array
    {
        return array_map(
            static::hydrate(...),
            self::odoo()->read(static::model(), $ids, static::fieldNames()),
        );
    }

    protected static function model(): string
    {
        $reflectionClass = new ReflectionClass(static::class);
        $model = $reflectionClass->getAttributes(Model::class)[0]
            ?? throw new ConfigurationException(static::class.' is missing the #[Model] attribute.');

        return $model->newInstance()->name;
    }

    public static function query(): ModelQuery
    {
        return new ModelQuery(
            static::newInstance(),
            self::odoo()->model(static::model())->fields(static::fieldNames()),
        );
    }

    /**
     * @return OdooModel[]
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    public function __construct()
    {
        $this->initializeRelations();
    }

    public function __get(string $name): mixed
    {
        $reflectionClass = new ReflectionClass($this);
        if (! $reflectionClass->hasProperty($name)) {
            throw new UndefinedPropertyException(
                "Property '{$name}' is not defined on ".static::class.'.'
            );
        }

        $property = $reflectionClass->getProperty($name);

        $belongsToAttributes = $property->getAttributes(BelongsTo::class);
        if (! empty($belongsToAttributes)) {
            $belongsTo = $belongsToAttributes[0]->newInstance();

            $fkValue = null;
            foreach ($reflectionClass->getProperties() as $prop) {
                $fieldAttributes = $prop->getAttributes(Field::class);
                if (! empty($fieldAttributes)) {
                    $fieldAttr = $fieldAttributes[0]->newInstance();
                    if (($fieldAttr->name ?? $prop->name) === $belongsTo->name) {
                        if ($prop->isInitialized($this)) {
                            $fkValue = $this->{$prop->name};
                        }
                        break;
                    }
                }
            }

            if ($fkValue !== null) {
                $relatedClass = $belongsTo->class;
                $this->{$name} = $relatedClass::find((int) $fkValue);

                return $this->{$name};
            }

            $this->{$name} = null;

            return null;
        }

        $hasManyAttributes = $property->getAttributes(HasMany::class);
        if (! empty($hasManyAttributes)) {
            $hasMany = $hasManyAttributes[0]->newInstance();
            $this->{$name} = new LazyHasMany($hasMany->class, []);

            return $this->{$name};
        }

        throw new UndefinedPropertyException(
            "Property '{$name}' is not defined or initialized on ".static::class.'.'
        );
    }

    protected function initializeRelations(): void
    {
        $reflectionClass = new ReflectionClass($this);
        foreach ($reflectionClass->getProperties() as $property) {
            if (! empty($property->getAttributes(BelongsTo::class))) {
                unset($this->{$property->name});
            } elseif (! empty($property->getAttributes(HasMany::class))) {
                $hasMany = $property->getAttributes(HasMany::class)[0]->newInstance();
                try {
                    $this->{$property->name} = new LazyHasMany($hasMany->class, []);
                } catch (\TypeError) {
                    try {
                        $this->{$property->name} = [];
                    } catch (\TypeError) {
                        $this->{$property->name} = null;
                    }
                }
            }
        }
    }

    public ?int $id = null;

    public function exists(): bool
    {
        return isset($this->id);
    }

    /**
     * @throws OdooModelException
     */
    public function save(): static
    {
        if ($this->exists()) {
            $updateResponse = self::odoo()->write(static::model(), [$this->id], (array) static::dehydrate($this));

            if ($updateResponse === false) {
                throw new OdooModelException('Failed to update '.static::class." (id={$this->id}).");
            }
        } else {
            $createResponse = self::odoo()->create(static::model(), (array) static::dehydrate($this));

            if ($createResponse === false) {
                throw new OdooModelException('Failed to create '.static::class.'.');
            }

            $this->id = $createResponse;
        }

        return $this;
    }

    /**
     * @throws UndefinedPropertyException
     */
    public function fill(iterable $properties): static
    {
        $reflectionClass = new ReflectionClass(static::class);

        foreach ($properties as $name => $value) {
            if (! $reflectionClass->hasProperty($name)) {
                throw new UndefinedPropertyException(
                    "Property '{$name}' is not defined on ".static::class.'.',
                );
            }

            $this->{$name} = $value;
        }

        return $this;
    }

    public function equals(OdooModel $model): bool
    {
        $reflectionClass = new ReflectionClass(static::class);

        foreach ($reflectionClass->getProperties() as $property) {
            $thisInitialized = $property->isInitialized($this);
            $otherInitialized = $property->isInitialized($model);

            if ($thisInitialized !== $otherInitialized) {
                return false;
            }

            if ($thisInitialized) {
                $val1 = $this->{$property->name};
                $val2 = $model->{$property->name};
                if (is_object($val1) && is_object($val2)) {
                    if ($val1 != $val2) {
                        return false;
                    }
                } elseif ($val1 !== $val2) {
                    return false;
                }
            }
        }

        return true;
    }
}
