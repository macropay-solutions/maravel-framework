<?php

namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class MorphMany extends MorphOneOrMany
{
    /**
     * Convert the relationship to a "morph one" relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphOne
     */
    public function one()
    {
        $relationName = Str::uuid()->toString();

//        return MorphOne::noConstraints(fn () => new MorphOne(
        return MorphOne::noConstraints(function () use ($relationName): MorphOne {
            $this->getParent()->nowEagerLoadingRelationNameWithNoConstraints = $relationName;
            $builder = $this->getQuery()->clone();
            $builder->setQuery($builder->getQuery()->clone());

            return \app(MorphOne::class, [
                $builder,
                $this->getParent(),
                $this->morphType,
                $this->foreignKey,
                $this->localKey,
            ]);
        }, $relationName);
    }

    /**
     * Get the results of the relationship.
     *
     * @return mixed
     */
    public function getResults()
    {
        return !is_null($this->getParentKey())
            ? $this->query->get()
            : $this->related->newCollection();
    }

    /**
     * Initialize the relation on a set of models.
     *
     * @param array $models
     * @param string $relation
     * @return array
     */
    public function initRelation(array $models, $relation)
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->related->newCollection());
        }

        return $models;
    }

    /**
     * Match the eagerly loaded results to their parents.
     *
     * @param array $models
     * @param \Illuminate\Database\Eloquent\Collection $results
     * @param string $relation
     * @return array
     */
    public function match(array $models, Collection $results, $relation)
    {
        return $this->matchMany($models, $results, $relation);
    }

    /**
     * Create a new instance of the related model. Allow mass-assignment.
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function forceCreate(array $attributes = [])
    {
        $attributes[$this->getMorphType()] = $this->morphClass;

        return parent::forceCreate($attributes);
    }

    /**
     * Create a new instance of the related model with mass assignment without raising model events.
     *
     * @param array $attributes
     * @return \Illuminate\Database\Eloquent\Model
     */
    public function forceCreateQuietly(array $attributes = [])
    {
        return Model::withoutEvents(fn() => $this->forceCreate($attributes));
    }
}
