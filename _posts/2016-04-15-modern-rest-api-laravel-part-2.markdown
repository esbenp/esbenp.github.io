---
layout:     post
title:      "A modern REST API in Laravel 5 Part 2: Resource controls"
subtitle:   "Enable consumers of your API to control and manipulate the data they request"
date:       2016-04-15 18:19:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-01.jpg"
---

## tl;dr

<p>
  <a href="https://github.com/esbenp/bruno">Optimus\Bruno</a> will
  enable you to filter, paginate, sort and eager load related resources through query
  string parameters. <a href="https://github.com/esbenp/architect">Optimus\Architect</a> will
  enable the consumer to decide how the eager loaded related resources should be
  structured. Lastly, <a href="https://github.com/esbenp/genie">Optimus\Genie</a>
  provides a quick integrated way to implement the two libraries without having
  to add a lot of new code.
</p>

<p>
  The full code to this article can be found here:
  <a href="https://github.com/esbenp/larapi-series-part-2">larapi-series-part-2</a>
</p>

## Introduction

<p>
  All to often API developers do not give much thought into how it is to work with
  their APIs for a consumer. This article will give some very useful tips as to how
  you can make it much more flexible to work with for client developers. This is
  going to simplify development for both you and your front-end devs.
</p>

## Agenda

<p>
  In this article I will take you through...
</p>

<ol>
  <li>How you can enable consumers to automatically eager load related resources</li>
  <li>How you can give consumers controls like filters, pagination and sorting</li>
  <li>
    How you can enable consumers to decide how eager loaded related resources
    should be returned using data composition
    </li>
</ol>

<p>
  Excited? Lets go..!
</p>

## The challenge

<p>
  If you have ever experienced developing a client that uses an API you have probably
  encountered the problem we will try to solve in this part of our journey for a
  <a href="/2016/04/11/modern-rest-api-laravel-part-0/">modern REST API in Laravel 5</a>.
  When developing a client you will often need different parts of the same data
  for different scenarios.
</p>

<p>
  Imagine having two resources: <code>/users</code> and <code>/roles</code>.
  A user can have many roles: <code>users 1 ----> n roles</code>. Now assume that you
  are tasked with designing two things:
</p>

<ol>
  <li>A table list of all users and the name of their role</li>
  <li>A dropdown of all users with the role named 'Agent'</li>
</ol>

<p>
  Let us try to think about these views one at a time and the data they would need.
</p>

### User table list

<p>
  Okay, so this would be a table that lists all users on the site. Now, we want to display
  all the roles that is attached to each user in the list.
</p>

<p>
  <img src="/img/laravel-api-part-2/user-list.png">
</p>

<p>
  The data we would need to do so would be the role name of all the roles. Something along
  these lines.
</p>

```json
[
  {
    "id": 1,
    "active": true,
    "name": "Katrine Elbek",
    "email": "demo.kat@traede.com",
    "roles": [
      {
        "name": "Administrator"
      }
    ]
  },
  {
    "id": 2,
    "active": true,
    "name": "Katrine Obling",
    "email": "demo.agent@traede.com",
    "roles": [
      {
        "name": "Agent"
      }
    ]
  },
  {
    "id": 3,
    "active": true,
    "name": "Yvonne",
    "email": "demo.lone@traede.com",
    "roles": [
      {
        "name": "Administrator"
      }
    ]
  }
]
```

### Agent dropdown

<p>
  So this is a dropdown for selecting the ID of a user that has the role "Agent".
</p>

<p style="margin-bottom:0">
  <img src="/img/laravel-api-part-2/customer-view.png">
</p>

<p>
  So we need a filtered version of the user data that only has agents in it. Like so:
</p>

```json
[
  {
    "id": 2,
    "name": "Katrine Obling",
    "email": "demo.agent@traede.com"
  }
]
```

<p>
  Note here that we do not actually <i>need</i> the roles array of the user. Because we
  are not displaying any of this data in the view.
</p>

## Planning the endpoints

<p>
  Okay, so we now know the data that we will eventually need to construct the two views.
  Let us just summarize them here.
</p>

**Table list**

```json
[
  {
    "id": 1,
    "active": true,
    "name": "Katrine Elbek",
    "email": "demo.kat@traede.com",
    "roles": [
      {
        "name": "Administrator"
      }
    ]
  },
  {
    "id": 2,
    "active": true,
    "name": "Katrine Obling",
    "email": "demo.agent@traede.com",
    "roles": [
      {
        "name": "Agent"
      }
    ]
  },
  {
    "id": 3,
    "active": true,
    "name": "Yvonne",
    "email": "demo.lone@traede.com",
    "roles": [
      {
        "name": "Administrator"
      }
    ]
  }
]
```

**Agent user dropdown**

```json
[
  {
    "id": 2,
    "name": "Katrine Obling",
    "email": "demo.agent@traede.com"
  }
]
```

<p>
  Now, one of the first questions we could ask ourselves could be: for the second data set,
  do we want to filter the users in the API or in the client? Let us just imagine for a
  second that the endpoint <code>/users</code> always returns the top data set. Then to get
  all the agents in javascript view we could simply use a filter function.
</p>

```javascript
let agents = users
              .map((user) => {
                  user.roleNames = user.roles.map((role) => role.name)
                  return user;
              })
              .filter((user) => user.roleNames.indexOf('Agent') !== -1)
```

<p>
  So, it is pretty simple to actually get all the agents out of the data set. However,
  this code does have a few risks.
</p>

<p>
  What happens if the underlying data model of the API changes? Imagine if the Agent
  role name changed to Super-Agent. Then we would not get the agents any more since
  we are looking specifically for roles named 'Agent'. What if the name property
  changed to title? I know these are all contrived examples, however they do demonstrate
  the risks of relying to heavily on the client to do these sort of things.
</p>

<p>
  Would it not be much better if the client could simply <i>request</i> all the agents
  from the API?
</p>

<p>
  And what about our users list: do we always want to load the roles whenever we are
  requesting the users? What about in a situation where we just want the users, but
  not their roles? Assuming the underlying database model is a relational
  is it not highly inefficient to load the roles when they are not needed?
</p>

<p>
  Given these goals we can quickly sum up a list of requirements:
</p>

<ol>
  <li>We want to be able to load users <i>without</i> their roles</li>
  <li>We want to be able to load users <i>with</i> their roles</li>
  <li>We want to be able to load users that have the agent role</li>
</ol>

<p>
  Let us look at the different strategies we can implement to achieve this.
</p>

### The bad one: make an endpoint for each data set

<p>
  One solution could be to simply make an endpoint per type of dataset.
</p>

<ul>
  <li><code>/users</code> returns users <i>without</i> their roles</li>
  <li><code>/users/with-roles</code> returns users <i>with</i> their roles</li>
  <li><code>/users/agents</code> returns users that are agents</li>
</ul>

<p>
  Hopefully I do not have to spend to much time explaining why this is a bad idea.
  When having so many different endpoints for the same underlying data it
  becomes a real <i>pain in the a$$&#8482;</i> to maintain. Whenever something
  has to change it will often result in an eksponential amount of changes elsewhere.
</p>

### The better solution: resource controls

<p>
  Would it not be so much nicer if we could just do:
</p>

<ul>
  <li><code>/users</code> returns users <i>without</i> their roles</li>
  <li><code>/users?includes[]=roles</code> returns users <i>with</i> their roles</li>
  <li><code>/users?filters[]=isAgent:1</code> returns users that are agents</li>
</ul>

<p>
  Now we not only use the same endpoint but use query strings to filter and manipulate
  the data we need.
</p>

## Implementation

<p>
  To implement this I have written a small library:
  <a href="https://github.com/esbenp/bruno">Bruno</a>.
  To find more details about this library and its features you can read its
  README.
</p>

<p>
  So assume we have the application in place and we have a <code>User</code> model
  and a <code>Role</code> model. With a relation like so <code>User 1 ----> n Role</code>.
  Then we can write a controller that looks like this:
</p>

```php
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
}
```

<p>
  A few things are going on here which are worth to notice.
</p>

```php?start_inline=1
// Parse the resource options given by GET parameters
$resourceOptions = $this->parseResourceOptions();
```

<p>
  <code>parseResourceOptions</code> is a method of the base controller that
  will read our query strings
  (<code>?includes[]=roles</code> and <code>?filters[]=isAgent:1</code>) into a
  format that we can use.
</p>

```php?start_inline=1
$this->applyResourceOptions($query, $resourceOptions);
```

<p>
  This is the method that will apply our different resource controls to the
  Eloquent query builder. In the case of <code>?includes[]=roles</code> it
  will run something like <code>$query->with('roles')</code> behind the scenes.
</p>

<p>
  It is important to note that <code>applyResourceOptions</code> is part of the
  <code>EloquentBuilderTrait</code> that we include in our controller. Why is this not
  a standard part of the base controller class? Because database logic should <u>not</u>
  be written in our controllers :-)
</p>

### Making filters work

<p>
  So, I may have oversold the syntax of filters a little bit.
  <code>?filters[]=isAgent:1</code> sounded a little to good to be true, right?
  Actually, the reason the filter syntax is a bit more complex is because it
  contains a lot of cool functionality.
</p>

```json
{
  "filter_groups": [
    {
      "filters": [
        {
          "key": "isAgent",
          "value": true,
          "operator": "eq"
        }
      ]
    }
  ]
}
```

<p class="note">
  The actual syntax of filters.
</p>

<p>
  You can do a lot more cool stuff with filters, and
  <a href="https://github.com/esbenp/bruno">
  I suggest you check out the syntax in the Bruno repository</a>.
</p>

<p>
  The short version is that you can define several filter groups. Each filter
  group can contain many filters using operators such as <code>eq</code> (equals),
  <code>sw</code> (starts with), <code>lt</code> (less than),
  <code>in</code> and more. Think of each filter group as a parenthesis grouping
  in an if-statement. So for instance this example
</p>

```json
{
  "filter_groups": [
    {
      "or": true,
      "filters": [
        {
          "key": "email",
          "value": "@gmail.com",
          "operator": "ew"
        },
        {
          "key": "email",
          "value": "@hotmail.com",
          "operator": "ew"
        }
      ]
    },
    {
      "filters": [
        {
          "key": "name",
          "value": ["Stan", "Eric", "Kyle", "Kenny"],
          "operator": "in"
        }
      ]
    }
  ]
}
```

<p>
  If thought of as an if-statement would look like
</p>

```php?start_inline=1
if (
  (endsWith('@gmail.com', $email) || endsWith('@hotmail.com', $email)) &&
  in_array($name, ['Stan', 'Eric', 'Kyle', 'Kenny'])
) {
 // return user
}
```

<p class="note">
  endsWith is a fictitious function
</p>

<p>
  So the query will return all users whoose email ends with <u>either</u>
  @gmail.com or @hotmail.com <u>and</u> whoose name is either Stan, Eric, Kyle or
  Kenny. Cool, yeah?
</p>

<p>
  Anywho, enough syntax. Let us make the 'isAgent' filter work. First of all
  remember to send the correct data with the request. Instead of
  <code>?filters[]=isAgent:1</code> the data is now
</p>

```json
{
  "filter_groups": [
    {
      "filters": [
        {
          "key": "isAgent",
          "value": true,
          "operator": "eq"
        }
      ]
    }
  ]
}
```

<p>
  Or as query string
</p>

```
?filter_groups%5B0%5D%5Bfilters%5D%5B0%5D%5Bkey%5D=isAgent&filter_groups%5B0%5D%5Bfilters%5D%5B0%5D%5Bvalue%5D=true&filter_groups%5B0%5D%5Bfilters%5D%5B0%5D%5Boperator%5D=eq
```

<p>
  Most AJAX libraries will automatically convert the JSON data to a query string
  on GET requests, so you really do not need to be a query string syntax wizard.
  jQuery even has the function <code>$.param</code> which can do it for you.
</p>

<p>
  The next thing we must do to make it work is to implement a custom filter.
  This is because there is no <code>isAgent</code> property on our <code>User</code>
  model. So when the controller applies our filter to the Eloquent query builder
  it will try to execute <code>$query->where('isAgent', true)</code> which will
  throw an error (since the column does not exist). Luckily the controller will
  look for custom filter methods: so let us implement that!
</p>

```php?start_inline=1
public function filterIsAgent(Builder $query, $method, $clauseOperator, $value, $in)
{
    // check if value is true
    if ($value) {
        $query->whereIn('roles.name', ['Agent']);
    }
}
```

<p>
  Add this method to our <code>UserController</code> and we are almost done.
  There is just one more thing. Whenever we are making a custom filter method
  the system will try to look for a relationship on the Eloquent model to join.
  That means in this case it will try to find a <code>isAgent</code> relationship
  on the model. This probably does not exist, but there does exist a
  relationship named <code>roles</code>. So we overcome this by adding the
  <code>isAgent</code> relationship to the model.
</p>

```php?start_inline=1
public function isAgent()
{
    return $this->roles();
}
```

### Making it work with Larapi

<p>
  So the above example is just a quick and dirty demonstration of how you can use
  Laravel controller to get resource controls. However, since we are doing a
  <a href="/2016/04/11/modern-rest-api-laravel-part-0/">series on creating a Laravel API</a> with my
  <a href="https://github.com/esbenp/larapi">API-friendly Laravel fork</a> let us see
  how this example would look using the
  <a href="/2016/04/11/modern-rest-api-laravel-part-1/">API structure</a> we laid out in part 1.
</p>

#### The controller

<p>
  In <code>UserController.php</code> the method for <code>GET /users</code> should be defined like
</p>

```php?start_inline=1
public function getAll()
{
    $resourceOptions = $this->parseResourceOptions();

    $data = $this->userService->getAll($resourceOptions);
    $parsedData = $this->parseData($data, $resourceOptions, 'users');

    return $this->response($parsedData);
}
```

<p>
  So we pass along the resource control options to the service so it can pass it along to
  the repository. If your repositories extend my
  <a href="https://github.com/esbenp/genie">Eloquent repository base class Genie</a> it will already
  have the helper functions build-in needed to use the resource options.
</p>

#### The service

<p>
  In the user service class, <code>UserService.php</code>, add the method to get users.
</p>

```php?start_inline=1
public function getAll($options = [])
{
    return $this->userRepository->get($options);
}
```

<p>
  The <code>get</code> method of the repository is
  <a href="https://github.com/esbenp/genie/blob/master/src/Repository.php#L33">
  build into the previously mentioned repository base class</a>,
  so we do not even need to implement it.
</p>

#### The repository

<p>
  Even though the <code>get</code> method is already implemented in the
  repository base class, we still need to implement the custom <code>isAgent</code> filter.
</p>

```php
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
```

#### The model

<p>
  Lastly, we need to make sure the <code>User</code> model can join the
  <code>isAgent</code> relationship. Add the following relationship to the
  model.
</p>

```php?start_inline=1
public function isAgent()
{
    return $this->roles();
}
```

#### That is it

<p>
  Yeah there is really nothing more to it. Now the <code>GET /users</code> endpoint has
  support for filtering, eager loading, pagination and sorting. Plus data
  structure composition.
</p>

## Data structure composition?!

<p>
  If you remember in our <code>UserController</code> we have the following line.
</p>

```php?start_inline=1
// Parse the data using Optimus\Architect
$parsedData = $this->parseData($books, $resourceOptions, 'users');
```

<p>
  So what the heck is
  <a href="https://github.com/esbenp/architect">Optimus\Architect</a>?
  It is a library we can use to define the composition of eager loaded relationships.
  Easier to explain by example. Imagine this user data:
</p>

<code>GET /users?includes[]=roles</code>

```json
{
  "users": [
    {
      "id": 1,
      "active": true,
      "name": "Katrine Elbek",
      "email": "demo.kat@traede.com",
      "roles": [
        {
          "id": 1,
          "name": "Administrator"
        }
      ]
    },
    {
      "id": 2,
      "active": true,
      "name": "Katrine Obling",
      "email": "demo.agent@traede.com",
      "roles": [
        {
          "id": 2,
          "name": "Agent"
        }
      ]
    },
    {
      "id": 3,
      "active": true,
      "name": "Yvonne",
      "email": "demo.lone@traede.com",
      "roles": [
        {
          "id": 1,
          "name": "Administrator"
        }
      ]
    }
  ]
}
```

<p>
  This data is loaded with the <code>embedded</code> mode, meaning that
  relationships of resources are nested within. In this case it would be that
  <i>inside</i> each <code>User</code> is a <i>embedded</i> collection of its
  <code>Role</code>s. This is the default way to load relationships with Eloquent. Let us check out some other modes.
</p>

<code>GET /users?includes[]=roles:ids</code>

```json
{
  "users": [
    {
      "id": 1,
      "active": true,
      "name": "Katrine Elbek",
      "email": "demo.kat@traede.com",
      "roles": [1]
    },
    {
      "id": 2,
      "active": true,
      "name": "Katrine Obling",
      "email": "demo.agent@traede.com",
      "roles": [2]
    },
    {
      "id": 3,
      "active": true,
      "name": "Yvonne",
      "email": "demo.lone@traede.com",
      "roles": [1]
    }
  ]
}
```

<p>
  In the <code>ids</code> mode Architect will simply return the <i>primary key</i> of the related model.
</p>

<code>GET /users?includes[]=roles:sideload</code>

```json
{
  "users": [
    {
      "id": 1,
      "active": true,
      "name": "Katrine Elbek",
      "email": "demo.kat@traede.com",
      "roles": [1]
    },
    {
      "id": 2,
      "active": true,
      "name": "Katrine Obling",
      "email": "demo.agent@traede.com",
      "roles": [2]
    },
    {
      "id": 3,
      "active": true,
      "name": "Yvonne",
      "email": "demo.lone@traede.com",
      "roles": [1]
    }
  ],
  "roles": [
    {
      "id": 1,
      "name": "Administrator"
    },
    {
      "id": 2,
      "name": "Agent"
    }
  ]
}
```

<p>
  In the last mode <code>sideload</code> all the embedded relationships are
  hoisted into its own collection at the root level.
  This removes duplicated entries of the embedded mode and can result
  in smaller responses.
</p>

## Conclusion

<p>
  To really build a good and useful API one most think about the consumers of
  it. Think of it like a business: your customers should love your product.
  One of the things that makes it a hell to build a client to an API is when
  the API does not offer flexibility in the returned data set. What related
  resources should be included (and how)? Sorting, filtering, pagination is all
  essential when building stuff like lists and data tables
  (the bread and butter of any SaaS application).
</p>

<p>
  By implementing a few simple libraries like
  <a href="https://github.com/esbenp/architect">Optimus\Architect</a>,
  <a href="https://github.com/esbenp/bruno">
  Optimus\Bruno</a> and
  <a href="https://github.com/esbenp/genie">Optimus\Genie</a> you
  can rapidly create resources that scale well and easily grant your
  consumers the flexibility they need whilst keeping your own development
  flow sane.
</p>

<p>
  The full code to this article can be found here:
  <a href="https://github.com/esbenp/larapi-series-part-2">larapi-series-part-2</a>
</p>

<p>
  All of these ideas and libraries are new and underdeveloped.
  Are you interested in helping out? Reach out on
  <a href="mailto:esbenspetersen@gmail.com">e-mail</a>,
  <a href="https://twitter.com/esbenp">twitter</a> or
  <a href="https://github.com/esbenp/larapi/issues">the Larapi repository</a>
</p>
