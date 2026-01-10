<?php


namespace App\Support;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class UniqueIdentifier
{
    /**
     * Generate a random, unique and unused identifier for a model.
     */
    public static function generate(string $model, string $column, int $length, int $attempts = 10): string
    {
        if (!class_exists($model) || !in_array(Model::class, class_parents($model))) {
            throw new InvalidArgumentException("The model class must be a valid Eloquent class");
        }

        $usingSoftDelete = in_array(SoftDeletes::class, class_uses_recursive($model));

        /** @var Model $instance */
        $instance = new $model;

        $isUnique = function (string $value) use ($usingSoftDelete, $instance, $column) {
            $query = $instance->newQuery();

            if ($usingSoftDelete) {
                $query->withTrashed();
            }

            return $query->where($column, $value)->doesntExist();
        };

        $tries = 0;

        do {
            $tries++;

            $identifier = Str::lower(Str::random($length));

            if ($isUnique($identifier)) {
                return $identifier;
            }
        } while ($tries < $attempts);

        throw new RuntimeException("Maximum attempts reached");
    }
}
