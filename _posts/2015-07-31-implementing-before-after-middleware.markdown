---
layout:     post
title:      "Implementing before/after middleware in PHP"
subtitle:   "Middleware is sexy and all around us - how can we implement a general solution?"
date:       2015-07-31 12:00:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-03.jpg"
---

<p>
    Middleware is a crazy popular mechanism in coding these days.
    <a href="http://laravel.com/docs/5.1/middleware">Laravel</a> has implemented it for its router, giving
    you the possibility to run actions on a request before and after it is executed. Likewise the web application
    framework for Node <a href="http://expressjs.com/guide/using-middleware.html">Express.js</a> has
    also a middleware implementation with middleware libraries for different things
    for example <a href="https://www.npmjs.com/package/serve-static">serving static files</a>,
    <a href="https://www.npmjs.com/package/morgan">log requests</a> etc.
</p>

## How middleware is implemented in Laravel

<p>
    Whenever a request is made to a Laravel application it is run through a pipeline
    of middleware. A demonstration is in order. Queue my amazing Photoshop skills.
</p>

<img src="/img/middleware.jpg" alt="How middleware is implemented in Laravel">

<p>
    Imagine you are making a request <code>GET /users</code> to an
    API. The actual action of GETting users from the database is displayed as the
    white circle in the middle. But before this can happen we want to run some middleware.
    As pictured there are two types of middleware: (1) before and (2) after.
    Before middleware is run against the request before the execution of the actual
    action it requests (duh). Examples of this could be checking if the user
    is authenticated and authorized, if the CSRF token sent with the request is valid
    etc.
</p>

<p>
    Once the before middleware has run, it is time for the actual action. We
    get an array of users from the database to send back to the client. But before
    the response reaches the client we want to do some post-action work on it.
    This is after middleware. Examples could be adding CORS headers, adding cookies,
    or caching the result. If we implemented caching after middleware,
    we could then implement some before middleware to
    check for a cached version of the resource before actually getting it
    from database.
</p>

## So it is a router thing?

<p>
    Not at all! The concept is really versatile if you think about it, and it can be implemented in
    many different scenarios.
</p>

### Use case: an uploader

<p>
    At <a href="http://traede.com">Traede</a> we are currently implementing middleware in our
    uploader functionality. Our users upload many product pictures, user profile pictures
    etc. All these we store in our CDN hosted by <a href="http://cloudinary.com">Cloudinary</a>.
    But before we actually upload the picture to Cloudinary we do some quick work,
    and after the image has been uploaded we do some clean up.
</p>

<img src="/img/middleware2.png" alt="Uploader middleware in Traede">

<p>
    This is how our uploader looks in Traede. The user drops a file in the drop area and it
    displays a small box with a thumbnail and the status of the image. The thumbnail is
    created using thumbnail middleware. Our middleware pipeline runs like this
</p>

<ol>
    <li><strong>Before middleware:</strong> generate thumbnail</li>
    <li><strong>Actual action:</strong> upload image to Cloudinary</li>
    <li><strong>After middleware:</strong> clean up temporary files</li>
</ol>

<img src="/img/middleware3.jpg" alt="Diagram of uploader middleware mechanism in Traede">

<p>
    There are probably many more middleware classes to be implemented in our uploader.
    But, for now, these are the ones we use. This was just a simple
    demonstration of how epic middleware can obviously be.
</p>

## Gotcha! Now gimme middleware!

<p>
    So, how do you actually implement a general middleware solution? Introducing:
    <a href="http://github.com/esbenp/onion">Onion</a>. A small standalone library,
    with a clever name might I add, that will give you the power to implement
    middleware in any situation.
</p>

### Some terminology of the Middleware Onion

<ul>
    <li>The actual action (e.g. upload to Cloudinary) is called the core of the onion</li>
    <li>Middleware classes are called layers (Onion layers - clever, no?)</li>
</ul>

<img src="/img/middleware4.jpg" alt="The middleware onion">

### A quick example

<p>
    The below example has two different middleware layers: a before and an after.
    The object we pass through our pipeline is a simple object with an array.
    Each actor that interacts with the object will log itself in the array.
</p>

```php?start_inline=1
class BeforeLayer implements LayerInterface {

    public function peel($object, Closure $next)
    {
        $object->runs[] = 'before';

        return $next($object);
    }

}

class AfterLayer implements LayerInterface {

    public function peel($object, Closure $next)
    {
        $response = $next($object);

        $object->runs[] = 'after';

        return $response;
    }

}

$object = new StdClass;
$object->runs = [];

$onion = new Onion;
$end = $onion->layer([
                new AfterLayer(),
                new BeforeLayer(),
                new AfterLayer(),
                new BeforeLayer()
            ])
            ->peel($object, function($object){
                $object->runs[] = 'core';
                return $object;
            });

var_dump($end);
```

<p>
    The result of this will be
</p>

```php?start_inline=1
..object(stdClass)#161 (1) {
  ["runs"]=>
  array(5) {
    [0]=>
    string(6) "before"
    [1]=>
    string(6) "before"
    [2]=>
    string(4) "core"
    [3]=>
    string(5) "after"
    [4]=>
    string(5) "after"
  }
}
```

<p>
    As you can see all the before middleware will run before the core,
    and likewise all the after middleware will run after. As you have probably
    noticed it does not matter in which order we add the layers to the onion.
    So how do we actually define what to run before and what to run after?
</p>

```php?start_inline=1
class Layer implements LayerInterface {

    public function peel($object, Closure $next)
    {
        // Everything run before the execution of
        // $next, can be considered before middleware

        $response = $next($object);

        // Everything run after the execution of
        // $next, can be considered after middleware

        return $response;
    }

}
```

<p>
    The function <code>$next</code> we pass to all layers is the function that
    will pass the object down through our pipeline. All actions that are run
    before <code>$next</code> in a layer class will be run before the core function,
    and is therefore before middelware. Likewise, whatever is done after <code>$next</code>
    is next middleware. Take a look at the layers we previously used in our example.
</p>

```php?start_inline=1
class BeforeLayer implements LayerInterface {

    public function peel($object, Closure $next)
    {
        $object->runs[] = 'before';

        return $next($object);
    }

}

class AfterLayer implements LayerInterface {

    public function peel($object, Closure $next)
    {
        $response = $next($object);

        $object->runs[] = 'after';

        return $response;
    }

}
```

## And that's a wrap

<p>
    That is it for now folks. Go to <a href="http://github.com/esbenp/onion">Onion's github repo</a>
    to get started middlewaring your world. Have fun!
</p>
