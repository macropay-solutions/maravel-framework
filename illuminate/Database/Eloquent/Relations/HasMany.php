<?php

namespace Illuminate\Database\Eloquent\Relations;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class HasMany extends HasOneOrMany
{
    /**
     * Convert the relationship to a "has one" relationship.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function one()
    {
        $relationName = Str::uuid()->toString();

//        return HasOne::noConstraints(fn () => new HasOne(
        return HasOne::noConstraints(function () use ($relationName): HasOne {
            $this->parent->nowEagerLoadingRelationNameWithNoConstraints = $relationName;

            return \app(HasOne::class, [
                $this->getQuery(),
                $this->parent,
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
}
