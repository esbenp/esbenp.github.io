<?php

namespace App\Http\Controllers;

use Optimus\Api\Controller\EloquentBuilderTrait;
use Optimus\Api\Controller\LaravelController;
use App\Models\User;

class UserController extends LaravelController
{
    use EloquentBuilderTrait;

    public function getUsers()
    {
        // Parse the resource options given by GET parameters
        $resourceOptions = $this->parseResourceOptions();

        // Start a new query for books using Eloquent query builder
        // (This would normally live somewhere else, e.g. in a Repository)
        $query = User::query();
        $this->applyResourceOptions($query, $resourceOptions);
        $books = $query->get();

        // Parse the data using Optimus\Architect
        $parsedData = $this->parseData($books, $resourceOptions, 'users');

        // Create JSON response of parsed data
        return $this->response($parsedData);
    }

    public function filterIsAgent(Builder $query, $method, $clauseOperator, $value, $in)
    {
        // check if value is true
        if ($value) {
            $query->whereIn('roles.name', ['Agent']);
        }
    }
}
