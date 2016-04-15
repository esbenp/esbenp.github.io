<?php

namespace Api\Users\Repositories;

use Infrastructure\Database\Eloquent\Repository;

class UserRepository extends Repository
{
    public function filterIsAgent(Builder $query, $method, $clauseOperator, $value, $in)
    {
        // check if value is true
        if ($value) {
            $query->whereIn('roles.name', ['Agent']);
        }
    }
}
