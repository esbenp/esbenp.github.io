---
layout:     post
title:      "A modern REST API in Laravel 5 Part 3: Error handling"
subtitle:   "Handle exceptions the API way"
date:       2017-01-14 08:19:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-04.jpg"
---

## tl;dr

<p>
  Install <code>optimus/heimdal</code> to get an extensive exception handler for your Laravel API with
  Sentry integration.
</p>

<p>
  The full code to this article can be found here:
  <a href="https://github.com/esbenp/larapi-part-3">larapi-part-3</a>
</p>

## Introduction

<p>
  Error handling is often an overlooked element of development, unfortunately. Luckily,
  this article will take you through the basics of API error handling. We will also install
  the <a target="_blank" href="https://github.com/esbenp/heimdal">API exception handler for Laravel Heimdal</a>
  which will quickly give us an awesome API exception handler with Sentry integration out of
  the box.
</p>

## Agenda

<p>
  In this article I will take you through...
</p>

<ol>
  <li>A general introduction to how to do error handling in an API</li>
  <li>Show you how to customize how different errors are formatted using Heimdal</li>
  <li>
    Show you how to log your errors in external trackers using reporters
  </li>
</ol>

<p>
  Let's do this.
</p>

## API error handling crash course

<p>
  If you are already an avid Laravel user you will now that the classic way Laravel
  handles errors are by rendering a certain view based on the error severity. A <code>404</code>
  exeption? Show the <code>404.blade.php</code> page.
  A <code>5xx</code> error? Show the stack trace exception
  page in development mode and a production message in production environments.
</p>

<p>
  This is all great but the way we do it in APIs is a bit different. You will soon discover
  that status codes have great meaning when dealing with APIs. The clients that
  consume our API will most likely run behaviour based on the status code returned by
  our API. Imagine a user tries to submit a form but Laravel throws a validation error
  (status code <code>422</code>). The client will start parsing the response by reading
  that this is a <code>422</code> response. Therefore the client knows this is an error
  caused by the data sent to the API (because <code>4xx</code> errors are client errors,
  while <code>5xx</code> errors are caused by the server). A <code>422</code> typically
  means a validation error, so we take the response (probably validation error messages)
  and parse them through our validation error flow (show the validation error messages
  to the user).
</p>

```
User ------> Submits form ------> Data ------> API
 ^                                              |
 |                                              v
Fix error                                 Validation error
 ^                                              |
 |                                              v
Show error to user <---- Client <----- 422 response
```

<p>
  Notice the difference here is that normally the user <u>sees</u> a 404 page or similar
  and determines the corresponding action by reading the view. When consuming APIs it is
  typically the computer that has to "see" the response and determine the corresponding
  action. If we showed a 404 page to the computer how would it know how to react? This
  is why getting the status codes right is so important.
</p>

```
User --------> Request resource --------> API
  ^                                        |
  |                                        v
Login                                 Unauthorized User
  ^                                        |
  |                                        v
Redirect to login <---- Client <----- 401 response
```

<p>
  So what are some typical uses of HTTP statuses in APIs? Look no further than the table below.
</p>

<table class="table">
  <thead>
    <tr>
      <th>Code</th>
      <th>Name</th>
      <th>What does it mean?</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>401</td>
      <td>Unauthorized</td>
      <td>You are not logged in, e.g. using a valid access token</td>
    </tr>
    <tr>
      <td>403</td>
      <td>Forbidden</td>
      <td>You are authenticated but do not have access to what you are trying to do</td>
    </tr>
    <tr>
      <td>404</td>
      <td>Not found</td>
      <td>The resource you are requesting does not exist</td>
    </tr>
    <tr>
      <td>405</td>
      <td>Method not allowed</td>
      <td>
        The request type is not allowed, e.g. <code>/users</code> is a resource and
        <code>POST /users</code> is a valid action but <code>PUT /users</code> is not.
      </td>
    </tr>
    <tr>
      <td>422</td>
      <td>Unprocessable entity</td>
      <td>
        The request and the format is valid, however the request was unable to process.
        For instance when sent data does not pass validation tests.
      </td>
    </tr>
    <tr>
      <td>500</td>
      <td>Server error</td>
      <td>
        An error occured on the server which was not the consumer's fault.
      </td>
    </tr>
  </tbody>
</table>

<p>
  This was by no means an exhaustive list. There are more status codes but these were all
  general ones to give you an idea of what you typically work with.
  Curious for more? <a target="_blank" href="https://httpstatuses.com">Here is a great overview of HTTP status codes</a>.
</p>

<p>
  So now that we know what status codes to use, how should we go about formatting our response?
  Well, there are a lot of opinions on that. Many times it could just be an empty response.
</p>

```
HTTP/1.0 401 Unauthorized
Content-Type: application/json
{}

HTTP/1.0 403 Forbidden
Content-Type: application/json
{}

HTTP/1.0 405 Method not allowed
Content-Type: application/json
{}
```

<p>
  Albeit not very helpful these are all valid responses. The client should be able to perform a corresponding
  action based on these responses, e.g. redirection.
</p>

<p>
  At <a target="_blank" href="https://traede.com">Traede</a> we have access control using users, roles and
  permissions. Sometimes it is beneficial to show an user an action they are not allowed to perform.
  In such cases when they try to perform the action we will display a modal saying
  "You do not have access to viewing this customer's orders" or similar. To actually know what the user
  is not allowed to do we have to get the missing permissions from the request. Therefore, our
  403 responses are formatted somewhat like this.
</p>

```
HTTP/1.0 403 Forbidden
Content-Type: application/json
{"error":true","missing_permissions":[{"permission":"orders:read","description":"customer's orders"}]}
```

<p>
  So now our client has some useful information that it can display the user. So maybe, if this was
  an employee with limited access he can request access from someone who can give it to him.
</p>

### Standardizing error responses with JSON API

<p>
  The <a target="_blank" href="http://jsonapi.org/">JSON API specification</a> is one of several specifications
  discussing a standardized API design. Other examples include
  <a target="_blank" href="https://github.com/Microsoft/api-guidelines">Microsoft's API guidelines</a> and
  <a target="_blank" href="https://github.com/interagent/http-api-design">Heroku's HTTP API design</a>. For
  the remainder of this article we will focus solely on JSON API. Not saying this is the "best".
</p>

<p>
  The only requirement for JSON API errors is that each object is in an array keyed by <code>errors</code>.
  Then there is a list of members you can put in each error object. None are required.
  <a target="_blank" href="http://jsonapi.org/format/#error-objects">You can see the exhaustive list here</a>.
</p>

<p>
  As an example imagine we try to create an user using <code>POST /users</code>. Let us say two validation
  errors occur: (1) the email is not an valid email and the password is not long enough. Using JSON API
  we could return this using this JSON object.
</p>

```
{
  "errors": [
    {
      "status": "422",
      "code": "110001",
      "title": "Validation error",
      "detail": "The email esben@petersendk is not an valid email."
    },
    {
      "status": "422",
      "code": "110002",
      "title": "Validation error",
      "detail": "The password has to be at least 8 characters long, you entered 7."
    }
  ]
}
```

```
HTTP/1.0 422 Unprocessable entity
Content-Type: application/json
{"errors":[{"status":"422","code":"110001","title":"Validation error","detail":"The email esben@petersendk is not an valid email."},{"status":"422","code":"110002","title":"Validation error","detail":"The password has to be at least 8 characters long, you entered 7."}]}
```

<p>
  The client can easily display these to the user so that inputs can be changed.
</p>

<p>
  Alright, this was a crash course to error handling. Let us look at some implementation!
</p>

## Implementing Heimdal, the API exception handler for Laravel

<p>
  At Traede we use <a target="_blank" href="https://github.com/esbenp/heimdal">Heimdal an API exception handler for APIs</a>.
  It is easily installable using the guide in the README. The rest of this guide will assume you have installed it.
  PRO tip: My <a target="_blank" href="https://github.com/esbenp/larapi">Laravel API fork</a> already comes with Heimdal installed.
</p>

<p>
  Alright, so Heimdal is installed and the config file <code>optimus.heimdal.php</code> has been published to our
  configuration directory. It already comes with sensible defaults as how to format ones errors. Let us take a look.
</p>

```
'formatters' => [
    SymfonyException\UnprocessableEntityHttpException::class => Formatters\UnprocessableEntityHttpExceptionFormatter::class,
    SymfonyException\HttpException::class => Formatters\HttpExceptionFormatter::class,
    Exception::class => Formatters\ExceptionFormatter::class,
],
```

<p>
  So the way this works is that the higher the exception is, the higher the priority. So if an
  <code>UnprocessableEntityHttpException</code> (validation error) is thrown then it will be formatted using the
  <code>UnprocessableEntityHttpExceptionFormatter</code>. However, if an <code>UnauthorizedHttpException</code> is thrown there
  is no special formatter so it will be passed down through the <code>formatters</code> array until it hits a relevant
  formatter.
</p>

<p>
  The <code>UnauthorizedHttpException</code> is a Symfony Http Exception is a subclass of <code>HttpException</code> and will
  therefore be caught by this line.
</p>

```
SymfonyException\HttpException::class => Formatters\HttpExceptionFormatter::class,
```

<p>
  Let us assume the error that occurs is an server error (500). Imagine PHP throws an <code>InvalidArgumentException</code>.
  This is not a subclass of <code>HttpException</code> but is a subclass of <code>Exception</code> and will therefore be
  caught by the last line.
</p>

```
Exception::class => Formatters\ExceptionFormatter::class
```

<p>
  So it will be formatted using <code>ExceptionFormatter</code>. Let us take a quick look at what it does.
</p>

```php
<?php

namespace Optimus\Heimdal\Formatters;

use Exception;
use Illuminate\Http\JsonResponse;
use Optimus\Heimdal\Formatters\BaseFormatter;

class ExceptionFormatter extends BaseFormatter
{
    public function format(JsonResponse $response, Exception $e, array $reporterResponses)
    {
        $response->setStatusCode(500);
        $data = $response->getData(true);

        if ($this->debug) {
            $data = array_merge($data, [
                'code'   => $e->getCode(),
                'message'   => $e->getMessage(),
                'exception' => (string) $e,
                'line'   => $e->getLine(),
                'file'   => $e->getFile()
            ]);
        } else {
            $data['message'] = $this->config['server_error_production'];
        }

        $response->setData($data);
    }
}
```

<p>
  Alright so when we are working in a development environment the returned error
  will just be the information available in the Exception: line number, file and so forth.
  When we are in a production environment we do not wish to display this kind of
  information to the user so we just return a special Heimdal configuration key
  <code>server_error_production</code>. This defaults to "An error occurred".
</p>

<strong>Debug environment</strong>

```
HTTP/1.0 500 Internal server error
Content-Type: application/json
{"status":"error","code":0,"message":"","exception":"InvalidArgumentException in [stack trace]","line":4,"file":"[file]"}
```

<strong>Production environment</strong>

```
HTTP/1.0 500 Internal server error
Content-Type: application/json
{"message":"An error occurred"}
```

<p>
  Alright now, what about the <code>HttpExceptionFormatter</code>?
</p>

```php
<?php

namespace Optimus\Heimdal\Formatters;

use Exception;
use Illuminate\Http\JsonResponse;
use Optimus\Heimdal\Formatters\ExceptionFormatter;

class HttpExceptionFormatter extends ExceptionFormatter
{
    public function format(JsonResponse $response, Exception $e, array $reporterResponses)
    {
        parent::format($response, $e, $reporterResponses);

        $response->setStatusCode($e->getStatusCode());
    }
}
```

<p>
  Aha, so the base <code>HttpExceptionFormatter</code> is just adding the HTTP status code to the response
  but is otherwise exactly the same as <code>ExceptionFormatter</code>. Awesomesauce.
</p>

<p>
  Let us try to add our own formatter. According to the HTTP specification a <code>401</code> response should
  include a challenge in the <code>WWW-Authenticate</code> header. This is currently not added by the
  Heimdal library (it will after this article), so let us create the formatter.
</p>

```
<?php

namespace Infrastructure\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Optimus\Heimdal\Formatters\HttpExceptionFormatter;

class UnauthorizedHttpExceptionFormatter extends HttpExceptionFormatter
{
    public function format(JsonResponse $response, Exception $e, array $reporterResponses)
    {
        parent::format($response, $e, $reporterResponses);

        $response->headers->set('WWW-Authenticate', $e->getHeaders()['WWW-Authenticate']);

        return $response;
    }
}
```

<p>
  Symfony's <code>HttpException</code> contains an header array that contains an <code>WWW-Authenticate</code> entry
  for all <code>UnauthorizedHttpException</code>. Next, we add the formatter to <code>config/optimus.heimdal.php</code>.
</p>

```
'formatters' => [
    SymfonyException\UnprocessableEntityHttpException::class => Formatters\UnprocessableEntityHttpExceptionFormatter::class,
    SymfonyException\UnauthorizedHttpException::class => Infrastructure\Exceptions\UnauthorizedHttpExceptionFormatter::class,
    SymfonyException\HttpException::class => Formatters\HttpExceptionFormatter::class,
    Exception::class => Formatters\ExceptionFormatter::class,
],
```

<p>
  The important thing here is that the added entry is higher than <code>HttpException</code> so that it has precedence.
  Now when we throw an <code>UnauthorizedHttpException</code> in our code like this
  <code>throw new UnauthorizedHttpException("challenge");</code> we get a response like below.
</p>

```
HTTP/1.0 401 Unauthorized
Content-Type: application/json
WWW-Authenticate: challenge
{"status":"error","code":0,"message":"","exception":"Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException in [stack trace]","line":4,"file":"[file]"}
```

<p>
  We can also add formatters for custom exceptions. Even though <code>418 I'm a teapot</code> is a valid exception to throw
  when <a target="_blank" href="https://httpstatuses.com/418">attempting to brew coffee with a teapot</a> it currently has
  no implementation in the Symfony HttpKernel. So let us add it.
</p>

```php
<?php

namespace Infrastructure\Exceptions;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ImATeapotHttpException extends HttpException
{
    public function __construct(\Exception $previous = null, $code = 0)
    {
        parent::__construct(418, 'I\'m a teapot', $previous, [], $code);
    }
}
```

```php
<?php

namespace Infrastructure\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Optimus\Heimdal\Formatters\HttpExceptionFormatter;

class ImATeapotHttpExceptionFormatter extends HttpExceptionFormatter
{
    public function format(JsonResponse $response, Exception $e, array $reporterResponses)
    {
        parent::format($response, $e, $reporterResponses);

        $response->setData([
            'coffe_brewer' => 'http://ghk.h-cdn.co/assets/cm/15/11/320x320/55009368877e1-ghk-hamilton-beach-5-cup-coffeemaker-48136-s2.jpg',
            'teapot' => 'http://www.ikea.com/PIAimages/0282097_PE420125_S5.JPG'
        ]);

        return $response;
    }
}
```

```
'formatters' => [
    SymfonyException\UnprocessableEntityHttpException::class => Formatters\UnprocessableEntityHttpExceptionFormatter::class,
    SymfonyException\UnauthorizedHttpException::class => Infrastructure\Exceptions\UnauthorizedHttpExceptionFormatter::class,
    Infrastructure\Exceptions\ImATeapotHttpException::class => Infrastructure\Exceptions\ImATeapotHttpExceptionFormatter::class,
    SymfonyException\HttpException::class => Formatters\HttpExceptionFormatter::class,
    Exception::class => Formatters\ExceptionFormatter::class,
],
```

<p>
  Now we throw the exception <code>throw new ImATeapotHttpException();</code> we send images of coffee brewers and teapots to the
  consumer so they can learn the difference :-)
</p>

```
HTTP/1.0 401 I'm a teapot
Content-Type: application/json
{"coffe_brewer":"http:\/\/ghk.h-cdn.co\/assets\/cm\/15\/11\/320x320\/55009368877e1-ghk-hamilton-beach-5-cup-coffeemaker-48136-s2.jpg","teapot":"http:\/\/www.ikea.com\/PIAimages\/0282097_PE420125_S5.JPG"}
```

## Send exceptions to external tracker using reporters

<p>
  More often than not you want to send your exceptions to an external tracker service for better overview, handling etc.
  There are a lot of these but Heimdal has out of the box support for both
  <a target="_blank" href="https://getsentry.com">Sentry</a> and <a target="_blank" href="https://bugsnag.com">Bugsnag</a>.
  The remainder of this article will show you how to integrate your exception handler with Sentry. For more information
  on reporters you can always refer to the <a target="_blank" href="https://github.com/esbenp/heimdal">documentation</a>.
</p>

<p>
  To add Sentry integration add the reporter to <code>config/optimus.heimdal.php</code>.
</p>

```
'reporters' => [
    'sentry' => [
        'class'  => \Optimus\Heimdal\Reporters\SentryReporter::class,
        'config' => [
            'dsn' => '[insert your DSN here]',
            // For extra options see https://docs.sentry.io/clients/php/config/
            // php version and environment are automatically added.
            'sentry_options' => []
        ]
    ]
],
```

<p>
  That is it! Remember to fill out <code>dsn</code>. Now when an exception is thrown we can see it in our Sentry UI.
</p>

<img src="/img/laravel-api-part-3/sentry.png" />

<p>
  Pretty dope. But there is more. In Heimdal all reporters responses are added to an array which is the passed to
  all formatters. Sentry will return a unique ID for all exceptions logged. For instance, it may be that you
  want to display the specific exception ID to the user so they can hand it over to the technical support.
  Finding the error that an user claims have happened has never been easier. Let us see how it works.
</p>

```php
<?php

namespace Infrastructure\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;
use Optimus\Heimdal\Formatters\ExceptionFormatter as BaseExceptionFormatter;

class ExceptionFormatter extends BaseExceptionFormatter
{
    public function format(JsonResponse $response, Exception $e, array $reporterResponses)
    {
        parent::format($response, $e, $reporterResponses);

        $response->setData(array_merge(
            (array) $response->getData(),
            ['sentry_id' => $reporterResponses['sentry']]
        ));

        return $response;
    }
}
```

<p>
  Alright, so basically what we are trying to achieve is to create a new internal server error
  formatter, since we probably only want to log internal server errors to Sentry. The ID of the
  exception in Sentry can be found in the reporter responses array, so we just extend the
  base exception formatter to include this ID. Now our exceptions look like so.
</p>

```
HTTP/1.0 500 Internal server error
Content-Type: application/json
{"status":"error","code":0,"message":"Annoying error logged in Sentry.","exception":"Exception: Annoying error logged in Sentry. in [stack trace]","line":37,"file":"[file]","sentry_id":"e8987d63dba549a69c58b49feb2692f9"}
```

<p>
  And we can find the exception by searching for the ID in Sentry.
</p>

<img src="/img/laravel-api-part-3/sentry_search.png" />

<p>
  If you want, it is really easy to add new reporters to Heimdal. Look at the code below to see just how
  simple the Sentry reporter implementation is.
</p>

```php
<?php

namespace Optimus\Heimdal\Reporters;

use Exception;
use InvalidArgumentException;
use Raven_Client;
use Optimus\Heimdal\Reporters\ReporterInterface;

class SentryReporter implements ReporterInterface
{
    public function __construct(array $config)
    {
        if (!class_exists(Raven_Client::class)) {
            throw new InvalidArgumentException("Sentry client is not installed. Use composer require sentry/sentry.");
        }

        $this->raven = new Raven_Client($config['dsn'], $config['sentry_options']);
    }

    public function report(Exception $e)
    {
        return $this->raven->captureException($e);
    }
}
```

<p class="note">
  The current Sentry implementation is larger because it adds some options straight out of the box. However, the above
  would be a perfectly valid integration.
</p>

## Conclusion

<p>
  By installing <a href="https://github.com/esbenp/heimdal">Heimdal</a> we very quickly
  get a good error handling system for our API. The important thing is that we provide enough
  information for our client so it can determine a corresponding action.
</p>

<p>
  The full code to this article can be found here:
  <a href="https://github.com/esbenp/larapi-part-3">larapi-part-3</a>
</p>

<p>
  All of these ideas and libraries are new and underdeveloped.
  Are you interested in helping out? Reach out on
  <a href="mailto:esbenspetersen@gmail.com">e-mail</a>,
  <a href="https://twitter.com/esbenp">twitter</a> or
  <a href="https://github.com/esbenp/larapi/issues">the Larapi repository</a>
</p>

