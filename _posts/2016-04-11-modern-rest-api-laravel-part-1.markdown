---
layout:     post
title:      "A modern REST API in Laravel 5 Part 1: Structure"
subtitle:   "A modern take on a scalable structure for your Laravel API"
date:       2016-04-11 17:04:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-01.jpg"
---

## tl;dr

<p>
  This article will demonstrate how to separate your Laravel project into "folders-by-component"
  rather than "folders-by-type". Confused?
  <a href="https://github.com/esbenp/larapi">See the example here</a> or
  <a href="https://github.com/esbenp/distributed-laravel">the library here</a>.
</p>

## Introduction

<p>
  Over time when your API grows in size it also grows in complexity. Many moving parts
  work together in order for it to function. If you do not employ a scaleable structure
  you will have a hard time maintaining your API. New additions will cause side effects
  and breakage in other places etc.
</p>

<p>
  It is important to realize in software development no singular structure is the mother
  of all structures. It is important to build a toolbox of patterns which you can employ
  given different situations. This article will serve as an opinionated piece on how such a structure <i>could</i> look.
  It has enabled <a href="http://traede.com/company">my team and I</a> to keep building
  features without introducing (too many :-)) breakages. For me, the structure I am about to show you works well right now,
  however it might well be that we refactor into a new one tomorrow. With that said, I hope you
  will find inspiration in what I am about to show you. :-)
</p>

## Agenda

<p>
  We will take a look at structure on three different levels.
</p>

<ol>
  <li>Application flow pattern</li>
  <li>Project folder structure</li>
  <li>Resource folder structure</li>
</ol>

## Level 1: Application flow pattern

<p>
  Too make our project more scalable it is always a good idea to separate our code base into smaller chunks.
  I have seen plenty of Laravel projects where everything is written inside controller classes:
  authentication, input handling, authorization, data manipulation, database operations, response creation etc.
  Imagine something like this...
</p>

```php?start_inline=1
class UserController extends Controller
{
    public function create(Request $request)
    {
        if (!Auth::check()) {
            return response()->json([
                'error' => "You are not authenticated"
            ], 401);
        }

        if (Gate::denies('create-user')) {
            return response()->json([
                'error' => "You are no authorized to create users"
            ], 403);
        }

        $data = $request->get('user');

        $email = $data['email'];

        $exists = User::where('email', $email)->get()->first();
        if (!is_null($exists)) {
            return response()->json([
                'error' => "A user with the email $email already exists!"
            ]);
        }

        $user = User::create($data);

        $activation = Activation::create($user->id);

        $user->activation->save($activation);

        Mail::send('user.activation', function($message) use ($user) {
            $m->from('hello@app.com', 'Your Application');

            $m->to($user->email, $user->name)->subject('Welcome to my crappy app');
        });

        return response()->json($user, 201);
    }
}
```

<p class="note">
  NOTE: This is very much a contrived example, probably not even valid code, to demonstrate
  giving controllers too many responsibilities.
</p>

<p>
  Now imagine your user resource has many endpoints.
</p>

```php
GET /users
GET /users/{id}
POST /users
PUT /users/{id}
DELETE /users/{id}
// etc.
```

<p>
  The UserController quickly grows into a monstrosity of duct-tape-code barely holding the fort together.
  It is time for separation of concerns.
</p>

### Introducing the service-repository pattern

<p>
  In 2013 <a href="https://laracasts.com/lessons/repositories-simplified">the repository pattern was all the rage in the Laravel community</a>.
  <a href="https://laracasts.com/series/commands-and-domain-events">Then in 2014 it was the command bus</a>. Remember,
  there is no single pattern which is the one to always choose. New patterns emerge all the time, and they should
  <i>add to your toolbox, not replace it</i>.
</p>

<p>
  Now, for me, the service-repository pattern solves a lot of my issues with complexity. The figure below demonstrates
  how we use the pattern at <a href="http://traede.com">Traede</a>.
</p>

<p style="text-align: center">
  <img src="/img/service-repository-pattern.png" alt="Service repository pattern in Laravel">
</p>

#### 1. The controller

<p>
  The controller is the entry point and exit for the application. It should define the endpoints of our resources.
  We use it for simple validation using
  <a href="https://laravel.com/docs/master/validation#validation-quickstart">Laravel's request validation</a> and for
  parsing any resource control options passed with the request (more on this in part 2). The controller will call
  the appropriate service class method and format the response in JSON with the correct status code.
</p>

#### 2. The middleware

<p>
  <a href="http://esbenp.github.io/2015/07/31/implementing-before-after-middleware/">I love the concept of middleware</a>.
  We use it for many things. However, in our simplified example here it will serve as an <i>authentication checkpoint</i>.
  More on that in part 4.
</p>

#### 3. Service class

<p>
  They way we are seeing the service class is as the glue of the operation. The goal of our example operation
  <code>POST /users</code> is to create a user. The service class will function as the operator that pulls different
  classes together in order to complete that operation.
</p>

#### 4. Repositories

<p>
  The repository pattern is an easy way to abstract away database operations. This way we separate business logic
  and SQL logic from our application. More on this in part 2.
</p>

#### 5. Events

<p>
  Using the event system is an effective way abstracting away complexity. It will be used for internal events
  (like sending the user created notification) and for webhooks.
</p>

### An example

<p>
  Alright, let us have a look on how we can use this pattern to implement our endpoint <code>POST /users</code>.
  So the goal is to create a user, but what steps does that operation entail exactly?
</p>

<ol>
  <li>Simple data validation (is it a valid email?)</li>
  <li>Authenticate the user</li>
  <li>Authorize that the user has permission to create users</li>
  <li>Complex data validation (is the email unique?)</li>
  <li>Create the database record</li>
  <li>Send email created notification to the new user</li>
  <li>Return the created user in JSON format with status code 201</li>
</ol>

<p></p>

#### 1. The controller

```php?start_inline=1
class UserController extends Controller
{
    private $userService;

    public function __construct(UserService $userService) {
        $this->userService = $userService;
    }

    public function create(CreateUserRequest $request)
    {
        $user = $this->userService->create($request->get('user'));

        return response()->json($user, 201);
    }
}
```
```php?start_inline=1
class CreateUserRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user' => 'required|array',
            'user.email' => 'required|email'
        ];
    }
}
```

<p>
  As it is evident, not a lot of stuff going on. This is to ensure our controllers can grow.
  It might look empty now, but it will have a decent size (without being to big) once we add
  4-5 endpoints more. We have also kept its responsibilities to a minimum thus making the
  class easy to reason about for other developers than ourself.
</p>

#### 2. The middleware layer

<p>
  The authentication of our user will happen behind the scenes using Laravel's middleware system
  and other libraries. We will get to the implementation of this in part 4. For now, just assume it
  is there.
</p>

#### 3. The service class

```php?start_inline=1
<?php

namespace App\Services;

use App\Exceptions\EmailIsNotUniqueException;
use App\Events\UserWasCreated;
use App\Helpers\UserValidator;
use App\Repositories\UserRepository;
use Infrastructure\Auth\Authentication;
use Illuminate\Events\Dispatcher;

class UserService
{
    private $auth;

    private $dispatcher;

    private $userRepository;

    private $userValidator;

    public function __construct(
        Authentication $auth,
        Dispatcher $dispatcher,
        UserRepository $userRepository,
        UserValidator $userValidator
    ) {
        $this->auth = $auth;
        $this->dispatcher = $dispatcher;
        $this->userRepository = $userRepository;
        $this->userValidator = $userValidator;
    }

    public function create(array $data)
    {
        $account = $this->auth->getCurrentUser();

        // Check if the user has permission to create other users.
        // Will throw an exception if not.
        $account->checkPermission('users.create');

        // Use our validation helper to check if the given email
        // is unique within the account.
        if (!$this->userValidator->isEmailUniqueWithinAccount($data['email'], $account->id)) {
            throw new EmailIsNotUniqueException($data['email']);
        }

        // Set the account ID on the user and create the record in the database
        $data['account_id'] = $account->id;
        $user = $this->userRepository->create($data);

        // If we set the relation right away on the user model, then we can
        // call $user->account without quering the database. This is useful if
        // we need to call $user->account in any of the event listeners
        $user->setRelation('account', $account);

        // Fire an event so that listeners can react
        $this->dispatcher->fire(new UserWasCreated($user));

        return $user;
    }
}
```

<p>
  Okay, so a lot of stuff going on here. Let us break it down step by step.
</p>

```php?start_inline=1
$account = $this->auth->getCurrentUser();

// Check if the user has permission to create other users.
// Will throw an exception if not.
$account->checkPermission('users.create');
```

<p>
  We get the current user (i.e. the user that makes the request). We have to check
  if this user has the permission to actually create users. How we do this is covered
  in part 4. For now, just know that if the user does not have permission to create
  users an exception will be thrown.
</p>

```php?start_inline=1
// Use our validation helper to check if the given email
// is unique within the account.
if (!$this->userValidator->isEmailUniqueWithinAccount($data['email'], $account->id)) {
    throw new EmailIsNotUniqueException($data['email']);
}
```

<p>
  Okay, so I realize this could have been done in the request validation using an
  <a href="https://laravel.com/docs/master/validation#rule-unique">unique rule</a>.
  However, this is just to illustrate two things: (I) sometimes you will have to do more
  complex data manipulation or validation that cannot be done automatically by Laravel.
  Using helper classes and having the service class "string it all together" is a
  great way of achieving this. (II) By throwing an exception we abort the flow so
  we make sure nothing else is executed before the user has fixed the error. More
  on this in part 3.
</p>

```php?start_inline=1
// Set the account ID on the user and create the record in the database
$data['account_id'] = $account->id;
$user = $this->userRepository->create($data);

// If we set the relation right away on the user model, then we can
// call $user->account without quering the database. This is useful if
// we need to call $user->account in any of the event listeners
$user->setRelation('account', $account);
```

<p>
  So in our fictitious example we have 1 account. And each account can have many users.
</p>

<pre>
Accounts 1 -------> n Users
</pre>

<p>
  We therefore add the <code>account_id</code> to the data, which the repository will
  persist in the database. I realize it is simpler to achieve this with
  <code>$user->account->save($account)</code>, however in this example we spare 1 more
  query to the database. And it is also just to demonstrate there are other ways
  of achieving the goal.
</p>

```php?start_inline=1
// Fire an event so that listeners can react
$this->dispatcher->fire(new UserWasCreated($user));

return $user;
```

<p>
  We fire an event through the event system for listeners to react to. In this example
  a listener will send the email notification. But more on this in part 5.
</p>

<p>
  If we want to decrease the responsibilities of our service class further, we can easily
  refactor the validation of data and the creation of the database entry to a UserBuilder class.
  However, for now we will keep the code as is.
</p>

#### 4. The repository

```php?start_inline=1
class UserRepository
{
    public function create(array $data)
    {
        DB::beginTransaction();

        try {
            $user = new User;

            $user->account_id = $data['account_id'];
            $user->fill($data);
            $user->save();

            $activation = new Activation;
            $activation->user_id = $user->id;
            $activation->save();
        } catch(Exception $e) {
            DB::rollBack();

            throw $e;
        }

        DB::commit();

        return $user;
    }
}
```
```php?start_inline=1
class User extends EloquentModel
{
    protected $fillable = [
        'email'
    ];

    // Activation relation
}
```

<p>
  For now our repository is super simple. Later on, we will let it extend a base
  class for advanced functionality but for now it will due. Notice, we never let
  relation properties be fillable.
</p>

<p>
  So in our example each user has an activation record that indicates whether or not
  the user has activated (by clicking an activation link we send our by email).
  Thus when creating a user we have to create both records and relate them to one another.
  This is the reason why we wrap our code in a database transaction. Should the
  activation record creation fail, for whatever reason, we want to fail the user record
  as well and let the exception bubble up so we can handle it (we will do so in part 3).
</p>

<p>
  <img src="/img/service-repository-pattern-2.png">
</p>

<p>
  Before we get into how we can structure our project folder, let us recap what we did.
  We divided an operation into different parts that each has a more narrower field of
  responsibility, thereby effectively allowing our code base to grow.
</p>

### Level 2: Project folder structure

<p>
  One of the first things that happened as our API grew was that we introduced a lot of custom infrastructure. Things like analytics integration, base repositories, exception handlers, queue infrastructure, testing helpers, custom validation rules etc. became part of our code base.
</p>

<p>
  What we realized was that we were mixing <i>business code</i> with
  <i>infrastructure code</i>. Confused? Think about it. How would it look if
  all the Laravel framework code was in the same folder as your API code?
  That would make no sense, right? This is because the Laravel framework is merely infrastructure
  that enables you to rapidly iterate on your <i>business code</i> without having to write a lot of infrastructure code. If you are interested in
  such architectural topics I suggest you look into stuff like
  <a href="http://fideloper.com/hexagonal-architecture">Hexagonal Architecture</a>  and <a href="https://domainlanguage.com/">Domain Driven Design</a>.
</p>

<p>
  To make matters even worse for us, in Traede, we have an app store. Basically it is a bunch of apps you can install in your account, i.e. they are <u>not part of the core product</u>. We realized we could divide our API into three parts: (I) the core business, (II) infrastructure and (III) extensions installable via app store.
</p>

<p>
  <img src="/img/traede-structure.png">
</p>

<p>
  So we decided our project structure should reflect the same.
</p>

<p>
  <img src="/img/old-new-api-structure.png">
</p>

<p>
  Above you see a comparison of how our project structure changed. On the left is the standard
  Laravel structure most of you are probably used to. In the past it contained all of our code:
  business code, infrastructure, extensions - everything! Now we have separated those concerns
  into separate namespaces. And the best part is that it is ridiculously easy to implement.
  In the <code>autoload</code> section of your <code>composer.json</code> set it up like so.
</p>

```json
// ...
"psr-4": {
  "Apps\\": "app-store/",
  "Traede\\": "traede/",
  "Infrastructure\\": "infrastructure/"
}
// ...
```

<p>
  If you want tests to be run you will also have to add a testsuite to <code>phpunit.xml</code>.
  <a href="https://github.com/esbenp/larapi/blob/master/phpunit.xml#L12">See how here</a>.
</p>

<p>
  Now, if you are an attentive reader (as I am sure you are), you may have noticed that the
  <code>resources/</code> and <code>tests/</code> folders have disappeared from the project.
  There is a good reason for that which I will explain in the next section.
</p>

### Level 3: Resource folder structure

<!-- Do not change the indention of the HTML here! it will break the rendering -->
<div class="row">
<div class="col-md-4">
  <img src="/img/bad-project-structure.png">
</div>
<div class="col-md-8">
<p style="margin-top:0">
  What you see on the left is an example of how our code base grew at Traede.
  Laravel by default is organized such that the code is "grouped-by-type".
  So all the controllers are in a folder together, all the
  models are in a folder together, all the events are in a folder together etc.
</p>

<p>
  What often happened was that I would be working on let us say the "Products component"
  of our API. The files of interest can be described as below.
</p>

{% highlight bash %}
/ app
  / Http
    / Controllers
      ..
      / ProductController.php
      ..
  / Models
    ..
    Product.php
    ProductVariant.php
    ProductVariantPrice.php
    ..
  / Repositories
    ..
    ProductRepository.php
    ProductVariantRepository.php
    ProductVariantPriceRepository.php
    ..
  / Services
    ..
    ProductService.php
    VariantService.php
    ..
{% endhighlight %}

<p class="note" style="margin-bottom:0;">
  <code>..</code> represents other files and folders.
</p>
</div>
</div>

<p>
   As you can imagine your files are spread very far from each other vertically in your
   project tree. I was not event able to take a screenshot with my
   product-component repositories and services in the same image. As I see it,
   this structure only makes sense if you "work on all the repositories right now" or
   "work on all the models right now". But you rarely do that, do you?
</p>

<p>
  Would it not be better if all the files were organized by component? E.g.
  all the product-oriented services, repositories, models, routes etc. in the same
  folder?
</p>

<!-- Do not change the indention of the HTML here! it will break the rendering -->
<div class="row">
<div class="col-md-4">
  <img src="/img/better-project-structure.png">
</div>
<div class="col-md-8">
<p style="margin-top:0">
  As you can see we have divided the core product of Traede into 8 separate components.
  A component typically has these folders
</p>

{% highlight bash %}
/ Products
  / Controllers
    # We typically have a controller per
    # resource, e.g. ProductController,
    # TagController, VariantController etc.
  / Events
    # All the events that can be raised by
    # the component, e.g. ProductWasCreated,
    # VariantPriceDeleted etc.
  / Exceptions
    # All exceptions. We currently have about 20
    # custom exceptions for products like
    # DuplicateSkuException and
    # BasePriceNotDefinedException
  / Listeners
    # Listeners for events
  / Models
    # Eloquent models: Product, Variant, Tag etc.
  / Repositories
    # We typically have a repository per eloquent model
    # ProductRepository, VariantRepository etc.
  / Requests
    # HTTP requests for validation
  / Services
    # A few larger classes that string together operations
    # typically we make one per controller, like
    # ProductController -> ProductService
  / Tests
    # All tests for the component
  # Typically used to connect events and listeners.
  ProductServiceProvider.php
  # All the routes for the component. Having distributed
  # route files, makes them ALOT more clear when you
  # have 100+ routes :-)
  routes.php
{% endhighlight %}

</div>
</div>

<p>
  So this clears up what happened to the <code>tests/</code> folder of the
  root folder. We simply gave each component a folder to have its tests
  within. But what about the <code>resources/</code>?
</p>

<p>
  One of the powers of the
  <a href="https://github.com/esbenp/distributed-laravel">distributed laravel</a>
  package is that it can search for resource files in the new component structure.
</p>

<p>
  In our entities component we have a lot of email views for transactional emails.
  The system will simply look for a <code>resources/</code> folder within
  <code>traede/Entities/</code> an load it into Laravel as the normal resources folder
  is.
</p>

<p>
  Another example is that Laravel by default comes with a langugage file for
  validation rules saved in <code>resources/lang/en/validation.php</code>.
  We have a validation component in our infrastructure that contains custom
  validation rules etc. It looks like this.
</p>

```bash
/ infrastructure
  .. # Other infrastructure components
  / Validation
    / resources
      / en
        # The default validation translations
        / validation.php
    # Loads custom validation rules
    ValidationServiceProvider.php
```

## Getting started

<p>
  Go to GitHub to see the <a href="https://github.com/esbenp/larapi">Larapi</a> example
  to see how the code is structured. If you want to implement this in an existing
  project you can use the service providers of
  <a href="https://github.com/esbenp/distributed-laravel">distributed-laravel</a> to
  load the components into Laravel. Browsing the Larapi code is best way to see
  how you should restructure.
</p>

## Conclusion

<p>
  This structure is still very new for me, so I am still experimenting and I am
  sure somethings could be done better. You think so as well? Reach out on
  <a href="mailto:esbenspetersen@gmail.com">e-mail</a>,
  <a href="https://twitter.com/esbenp">twitter</a> or
  <a href="https://github.com/esbenp/larapi/issues">the Larapi repository</a>
</p>
