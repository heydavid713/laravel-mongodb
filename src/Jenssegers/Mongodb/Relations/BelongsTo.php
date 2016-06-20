<?php

namespace Jenssegers\Mongodb\Relations;

class BelongsTo extends \Illuminate\Database\Eloquent\Relations\BelongsTo
{
    /**
     * Set the base constraints on the relation query.
     */
    public function addConstraints()
    {
        if (static::$constraints) {
            // For belongs to relationships, which are essentially the inverse of has one
            // or has many relationships, we need to actually query on the primary key
            // of the related models matching on the foreign key that's on a parent.
            $this->query->where($this->otherKey, '=', $this->parent->getAttributeValue($this->foreignKey));
        }
    }

    /**
     * Set the constraints for an eager load of the relation.
     *
     * @param array $models
     */
    public function addEagerConstraints(array $models)
    {
        // We'll grab the primary key name of the related models since it could be set to
        // a non-standard name and not "id". We will then construct the constraint for
        // our eagerly loading query so it returns the proper models from execution.
        $key = $this->otherKey;

        $this->query->whereIn($key, $this->getEagerModelKeys($models));
    }

        /**
         * Gather the keys from an array of related models.
         *
         * @param array $models
         *
         * @return array
         */
        protected function getEagerModelKeys(array $models)
        {
            $keys = [];

            // First we need to gather all of the keys from the parent models so we know what
            // to query for via the eager loading query. We will add them to an array then
            // execute a "where in" statement to gather up all of those related records.
            foreach ($models as $model) {
                if (!is_null($value = $model->{$this->foreignKey})) {
                    if (is_array($value)) {
                        $keys = array_merge($keys, $value);
                    } else {
                        $keys[] = $value;
                    }
                }
            }

            // If there are no keys that were not null we will just return an array with either
            // null or 0 in (depending on if incrementing keys are in use) so the query wont
            // fail plus returns zero results, which should be what the developer expects.
            if (count($keys) === 0) {
                return [$this->related->incrementing ? 0 : null];
            }

            return array_values(array_unique($keys));
        }

        /**
         * Match the eagerly loaded results to their parents.
         *
         * @param array                                    $models
         * @param \Illuminate\Database\Eloquent\Collection $results
         * @param string                                   $relation
         *
         * @return array
         */
        public function match(array $models, \Illuminate\Database\Eloquent\Collection $results, $relation)
        {
            $foreign = $this->foreignKey;

            $other = $this->otherKey;

            // First we will get to build a dictionary of the child models by their primary
            // key of the relationship, then we can easily match the children back onto
            // the parents using that dictionary and the primary key of the children.
            $dictionary = [];

            foreach ($results as $result) {
                $dictionary[$result->getAttribute($other)] = $result;
            }

            // Once we have the dictionary constructed, we can loop through all the parents
            // and match back onto their children using these keys of the dictionary and
            // the primary key of the children to map them onto the correct instances.
            foreach ($models as $model) {
                if (is_array($model->$foreign)) {
                    foreach ($model->$foreign as $f) {
                        if (isset($dictionary[$model->$f])) {
                            $model->setRelation($relation, $dictionary[$model->$f]);
                        }
                    }
                } else {
                    if (isset($dictionary[$model->$foreign])) {
                        $model->setRelation($relation, $dictionary[$model->$foreign]);
                    }
                }
            }

            return $models;
        }
}
