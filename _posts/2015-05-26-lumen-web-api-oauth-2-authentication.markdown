---
layout:     post
title:      "Building a web app with Lumen web API and OAuth2 authentication"
subtitle:   "Build a web API using Lumen micro-framework and OAuth2 authentication"
date:       2015-05-26 12:00:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-01.jpg"
---

<i>
    Note: some additions to the app.php config file and the proxy class were added on 22nd of June 2015 in response to breaking changes in the Lumen framework and with the release of Guzzle 6.
</i>

## Introduction

<p>
    <a href="http://lumen.laravel.com">Lumen</a> was recently released as a micro-framework brother for <a href="http://laravel.com">Laravel</a>.
    It immediately caught my attention as we use Laravel for writing our REST API at <a href="http://traede.com">Traede</a>. Our client is written
    in javascript and we therefore only use Laravel for the API. Lumen therefore seemed interesting as the intention is using it for writing
    speedy APIs.
</p>

<p>
    I quickly started looking at Lumen as a potential replacement for Laravel. One thing I quickly discovered though was that Laravel packages are
    not directly compatible with Lumen. Therefore a small bridge is often needed to close the gap. We use OAuth2 for API authentication and therefore
    the first bridge I wrote for Lumen was one for the excellent OAuth2 package
    <a href="https://github.com/lucadegasperi/oauth2-server-laravel">oauth2-server-laravel</a> by
    <a href="https://github.com/lucadegasperi">Luca Degasperi</a>.
</p>

<p>
    This post serves as a quick introduction on how to set it up. A README can also be found in the repository:
    <a href="https://github.com/esbenp/oauth2-server-lumen">esbenp/oauth2-server-lumen</a>. At the same time I will demonstrate how tokens can be
    managed by a javascript client using <a href="https://github.com/esbenp/jquery-oauth">esbenp/jquery-oauth</a>
</p>

## Installation

<p>
    I assume you already have an installation of Lumen. First of all require the package using composer.
</p>

```bash
composer require optimus/oauth2-server-lumen 0.1.*
```

### Registering service providers

<p>
    Next, register the service providers. In your <code>bootstrap/app.php</code> add the following registration along side
    other service providers.
</p>

```php?start_inline=1
// ... Other service providers
$app->register('LucaDegasperi\OAuth2Server\Storage\FluentStorageServiceProvider');
$app->register('Optimus\OAuth2Server\OAuth2ServerServiceProvider');
```

<p>
    <code>Optimus\OAuth2Server\OAuth2ServerServiceProvider</code> is a replacement for the original server provider provided by
    lucadegasperi's package. The original package tries to register route filters which are not supported in Lumen and should be
    replaced with route middleware.
</p>

<p>
    Additionally, the original service provider registers assets to be published using
    <code>php artisan vendor:publish</code>. Package asset publishing is not available in Lumen, we therefore have to copy it manually.
</p>

### Creating configuration file

<p>
    Inside <code>Optimus\OAuth2Server\OAuth2ServerServiceProvider</code> a config file is registered into the application using
    <code>$this->app->configure("oauth2");</code>. Inside Lumen's application class the container looks for the oauth config file
    inside a config folder that can be configured using <code>Laravel\Lumen\Application::useConfigPath</code>, however if you have
    not configured a folder the default is <code>{projectRoot}/config</code>. This folder <u>DOES NOT EXIST</u> by default, so we
    have to create it.
</p>

<p>
    When you have created the folder, copy <code>vendor/lucadegasperi/oauth2-server-laravel/config/oauth2.php</code>
    into it. Your project structure should now look like this.
</p>

```bash
app/
bootstrap/
config/            <--- the folder we just created
  - oauth2.php     <--- config file copied from vendor folder
database/
public/
... etc
```

### Registering middleware

<p>
    Lastly, we will register the route middleware which will replace the original route filters. In <code>app/bootstrap.php</code> insert the following. These will be used to protecting our API routes from unauthorized
    users.
</p>

```php?start_inline=1
$app->middleware([
    'LucaDegasperi\OAuth2Server\Middleware\OAuthExceptionHandlerMiddleware'
]);

$app->routeMiddleware([
    'check-authorization-params' => 'Optimus\OAuth2Server\Middleware\CheckAuthCodeRequestMiddleware',
    'csrf' => 'Laravel\Lumen\Http\Middleware\VerifyCsrfToken',
    'oauth' => 'Optimus\OAuth2Server\Middleware\OAuthMiddleware',
    'oauth-owner' => 'Optimus\OAuth2Server\Middleware\OAuthOwnerMiddleware'
]);
```

### Migrating OAuth database tables

<p>
    This step is unfortunately very hackish. If anyone knows a better way to do this I will be very happy
    to hear from you! The problem is we cannot rollback the migrations when doing this.
</p>

1. First we have to temporarily enable Facades and make a config alias.
2. Second we migrate straight out of the vendor folder.

<p>
    To setup the config alias and enabling facades go to <code>bootstrap/app.php</code> and insert the following code.
</p>

```php?start_inline=1
// It is recommended to remove ALL these lines after the migrations have run.
class_alias('Illuminate\Support\Facades\Config', 'Config');
$app->withFacades();
```

<p>
    Now run the migrations.
</p>

```bash
php artisan migrate --path=vendor/lucadegasperi/oauth2-server-laravel/migrations
```

<p>
    I highly recommended removing the class_alias and facade enabling after the migrations have run.
</p>

## Setting up an API

<p>
    Our OAuth2 server is now installed and configured. Let us look into an example on how to use it to authenticate an API.
</p>

### Configuring OAuth grant types

<p>
    We have to configure OAuth and tell what grant types we want to support in our API. Here, we are
    going to support the resource owner credentials grant and the refresh token grant. The
    resource owner credentials grant means requesting an access token using login and password.
    Open up <code>config/oauth2.php</code> and <u>replace</u> the <code>grant_types</code> key
    with the following code.
</p>

```php?start_inline=1
'grant_types' => [
    'password' => [
        'class' => '\League\OAuth2\Server\Grant\PasswordGrant',
        'callback' => function($email, $password) {
            $authManager = app()['auth'];

            if (app()["auth"]->once([
                "email" => $email,
                "password" => $password
            ])) {
                return $authManager->user()->id;
            } else {
                return false;
            }
        },
        'access_token_ttl' => 3600
    ],
    'refresh_token' => [
        'class' => '\League\OAuth2\Server\Grant\RefreshTokenGrant',
        'access_token_ttl' => 3600,
        'refresh_token_ttl' => 36000
    ]
]
```

### Create an OAuth client

<p>
    We have to create a client in the database. I usually do this using a database seeder. Before we do
    that let us create a config file that will contain our client id and secret. In production it is
    recommended to do this as environment variables rather than a config file.
</p>

<p>
    Create a config file <code>config/secrets.php</code>.
</p>

```php
<?php
return [
    'client_id' => 1,
    'client_secret' => 'gKYG75sw'
];
?>
```

<p>
    ... also create an app config file if you do not already have one <code>config/app.php</code>.
    Put in the url of your app. Also put in a 12, 32 or 64 character random string as app key.
    <u>This is important! Otherwise the encrypter will throw an exception later on.</u>
</p>

```php
<?php
return [
    'url' => 'http://oauth-tutorial.dev',
    'key' => 'U<CdJu~T&.g/kR-NX55h]HfB+bb,b7Y*',
    'cipher' => 'AES-256-CBC'
];
?>
```

<i>
    Note: the cipher entry was added 22nd of June, as this is now a required configuration since Lumen 5.1 for the encrypt/decrypt library to work.
</i>

<p>
    Add configure statement to <code>bootstrap/app.php</code>
</p>

```php?start_inline=1
$app->configure('app');
$app->configure('secrets');
```

<p>
    Now create a seed file <code>database/seeds/OAuthSeeder.php</code>
</p>

```php
<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class OAuthSeeder extends Seeder {

    public function run()
    {
        $config = app()->make('config');

        DB::table("oauth_clients")->delete();

        DB::table("oauth_clients")->insert([
            'id' => $config->get('secrets.client_id'),
            'secret' => $config->get('secrets.client_secret'),
            'name' => 'App'
        ]);
    }

}

?>
```

<p>
    ... and tell add the seed statement to <code>database/seeds/DatabaseSeeder.php</code>
</p>

```php?start_inline=1
$this->call('OAuthSeeder');
```

<p>
    Now you can finally seed the database. First redump the autoload to get the newly created
    OAuth seeder file in the autoload file.
</p>

```bash
composer dump-autoload && php artisan db:seed
```

### Creating a user database

<p>
    We need a database of users to auth. This is not put in Lumen out-of-the-box, but we can
    quickly borrow some classes from the Laravel repository to set it up. I have used this
    <a href="https://github.com/laravel/laravel/blob/master/app/User.php" target="_blank">user model</a>
    and put it in <code>app/Auth/User.php</code>
</p>

```php
<?php

namespace App\Auth;

use Illuminate\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;

class User extends Model implements AuthenticatableContract, CanResetPasswordContract {
    use Authenticatable, CanResetPassword;
    /**
     * The database table used by the model.
     *
     * @var string
     */
    protected $table = 'users';
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name', 'email', 'password'];
    /**
     * The attributes excluded from the model's JSON form.
     *
     * @var array
     */
    protected $hidden = ['password', 'remember_token'];
}
?>
```

<p>
    also, create a migration file using <code>php artisan make:migration create_users_table</code> and put
    in this code.
</p>

```php
<?php
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;
class CreateUsersTable extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('users', function(Blueprint $table)
        {
            $table->increments('id');
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password', 60);
            $table->rememberToken();
            $table->timestamps();
        });
    }
    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::drop('users');
    }
}
?>
```

<p>
    Create a UserSeeder in <code>database/seeds/UserSeeder.php</code>.
</p>

```php
<?php

use Illuminate\Database\Seeder;
use Illuminate\Database\Eloquent\Model;

class UserSeeder extends Seeder {

    public function run()
    {
        DB::table('users')->delete();

        $user = app()->make('App\Auth\User');
        $hasher = app()->make('hash');

        $user->fill([
            'name' => 'User',
            'email' => 'user@user.com',
            'password' => $hasher->make('1234')
        ]);
        $user->save();
    }

}

?>
```

<p>
    ... and add it to the database seeder <code>database/seeds/DatabaseSeeder.php</code>
</p>

```php?start_inline=1
$this->call('UserSeeder');
```

<p>
    Finally dump autoload, migrate and seed.
</p>

```bash
composer dump-autoload && php artisan migrate && php artisan db:seed
```

### Defining some API routes

<p>
    Now that we have our database seeded with a OAuth client and a test user, we need to create some
    routes for our API. Go to <code>app/Http/routes.php</code> and
    add the following routes.
</p>

```php?start_inline=1
$app->get('/', function() use ($app) {
    return view()->make('client');
});

$app->post('login', function() use($app) {
    $credentials = app()->make('request')->input("credentials");
    return $app->make('App\Auth\Proxy')->attemptLogin($credentials);
});

$app->post('refresh-token', function() use($app) {
    return $app->make('App\Auth\Proxy')->attemptRefresh();
});

$app->post('oauth/access-token', function() use($app) {
    return response()->json($app->make('oauth2-server.authorizer')->issueAccessToken());
});

$app->group(['prefix' => 'api', 'middleware' => 'oauth'], function($app)
{
    $app->get('resource', function() {
        return response()->json([
            "id" => 1,
            "name" => "A resource"
        ]);
    });
});
```

<p>
    <code>POST oauth/access-token</code> is the url that will issue the access token. For security reasons we will not call this directly, but through a proxy. This is to hide the client id and secret
    from the client. To read up on this specific issue I highly recommend the article <a href="http://jeremymarc.github.io/2014/08/14/oauth2-with-angular-the-right-way/" target="_blank">Oauth2 with Angular: The right way</a>. Instead we will attempt to login using <code>POST login</code> which
    will call <code>POST oauth/access-token</code> using a proxy written with <code>GuzzleHttp/guzzle</code>.
</p>

<p>
    <code>POST refresh-token</code> will be used to request new access tokens using our refresh token.
</p>

<p>
    <code>GET api/resource</code> is an API endpoint for a resource named resource. It uses
    the OAuth route middleware to check for a valid access token which we will pass to the authorization header
    later on.
</p>

### Writing our proxy

<p>
    It is never good practice to store ones client id and secret in the client for everyone to read.
    We therefore hide it by making a proxy that will issue our access token for us. First require
    Guzzle
</p>

```bash
composer require guzzlehttp/guzzle
```

<p>
    Next create the file <code>app/Auth/Proxy.php</code> and paste the following code.
</p>

```php
<?php

namespace App\Auth;

use GuzzleHttp\Client;

class Proxy {

    public function attemptLogin($credentials)
    {
        return $this->proxy('password', $credentials);
    }

    public function attemptRefresh()
    {
        $crypt = app()->make('encrypter');
        $request = app()->make('request');

        return $this->proxy('refresh_token', [
            'refresh_token' => $crypt->decrypt($request->cookie('refreshToken'))
        ]);
    }

    private function proxy($grantType, array $data = [])
    {
        try {
            $config = app()->make('config');

            $data = array_merge([
                'client_id'     => $config->get('secrets.client_id'),
                'client_secret' => $config->get('secrets.client_secret'),
                'grant_type'    => $grantType
            ], $data);

            $client = new Client();
            $guzzleResponse = $client->post(sprintf('%s/oauth/access-token', $config->get('app.url')), [
                'form_params' => $data
            ]);
        } catch(\GuzzleHttp\Exception\BadResponseException $e) {
            $guzzleResponse = $e->getResponse();

        }

        $response = json_decode($guzzleResponse->getBody());

        if (property_exists($response, "access_token")) {
            $cookie = app()->make('cookie');
            $crypt  = app()->make('encrypter');

            $encryptedToken = $crypt->encrypt($response->refresh_token);

            // Set the refresh token as an encrypted HttpOnly cookie
            $cookie->queue('refreshToken',
                $crypt->encrypt($encryptedToken),
                604800, // expiration, should be moved to a config file
                null,
                null,
                false,
                true // HttpOnly
            );

            $response = [
                'accessToken'            => $response->access_token,
                'accessTokenExpiration'  => $response->expires_in
            ];
        }

        $response = response()->json($response);
        $response->setStatusCode($guzzleResponse->getStatusCode());

        $headers = $guzzleResponse->getHeaders();
        foreach($headers as $headerType => $headerValue) {
            $response->header($headerType, $headerValue);
        }

        return $response;
    }

}
?>
```

<i>
    Note: some changes were added to the proxy class on 22nd of June 2015, as Guzzle 6 was released and it depreciated the use of the 'body' key for POST params. It also included the use of PSR-7 responses which to not have a json() function.
</i>

<p>
    I will not go to much into the details of the class. In short we have to options.
</p>

1. Request an access token (login)
2. Refresh the access token (access token has expired)

<p>
    Because we do not want to expose our client we do it through a proxy created with Guzzle. If
    we login we save the refresh token in a HttpOnly cookie (a cookie that cannot be accessed by
    client side scripts thus mitigating XSS attacks).
</p>

<p>
    If we request a refresh of our access token the server will decrypt the cookie and send it
    with the proxy request. If the refresh token is valid a new access token will be issued.
</p>

#### Registering cookie middleware

<p>
    The proxy utilizes queued cookies. To utilize this in Lumen we have to register
    <code>AddQueuedCookiesToResponse</code> middleware in <code>bootstrap/app.php</code>
</p>

```php?start_inline=1
$app->middleware([
    'LucaDegasperi\OAuth2Server\Middleware\OAuthExceptionHandlerMiddleware',
    'Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse'  // <--- added
]);
```

## Building a small client

<p>
    We will build a small client in javascript that will interact with our API. Basically the client will have
    three actions.
</p>

1. Request an access token and storing it
2. Request the resource from the API
3. Logout by removing the access token from local storage

<p>
    Let us build a quick skeleton for our client. Create the file <code>resources/views/client.blade.php</code>
    and add the markup below to it.
</p>

```html
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="utf-8">
    <title>Client</title>
    <script type="text/javascript" src="/vendor/jquery/dist/jquery.min.js"></script>
    <script type="text/javascript" src="/vendor/store-js/store.min.js"></script>
    <script type="text/javascript" src="/vendor/jquery-oauth/dist/jquery.oauth.min.js"></script>
    <script type="text/javascript" src="/js/client.js"></script>
  </head>
  <body>

    <button id="login">Login to API</button>
    <button id="request">Request resource</button>
    <button id="logout">Logout</button>

  </body>
</html>
```

<p>
    Now we need to install client dependencies. I highly recommend using Bower. We will use
    my library <a href="https://github.com/esbenp/jquery-oauth" target="_blank">esbenp/jquery-oauth</a>
    to manage and store the access token in the client.
</p>

<p>
    First we need to configure bower to use right installation path. Add the file <code>.bowerrc</code>
    to your project root.
</p>

```json
{
  "directory": "public/vendor/"
}
```

<p>
    Now install <code>jquery-oauth</code>
</p>

```bash
bower install --save jquery-oauth
```

<p>
    Now we will build the small client that will interact with the API. Create the file <code>public/js/client.js</code>
    and add the code.
</p>

```javascript
var Client = function Client() {
    this.authClient = null;

    this._setupAuth();
    this._setupEventHandlers();
}

Client.prototype._login = function _login() {
    var self = this;

    $.ajax({
        url: "/login",
        method: "POST",
        data: {
            credentials: {
                username: 'user@user.com',
                password: '1234'
            }
        },
        statusCode: {
            200: function(response) {
                if (response.accessToken === undefined) {
                    alert('Something went wrong');
                } else {
                    self.authClient.login(response.accessToken, response.accessTokenExpiration);
                }
            },
            401: function() {
                alert('Login failed');
            }
        }
    });
}

Client.prototype._logout = function _logout() {
    this.authClient.logout();
}

Client.prototype._request = function _request() {
    var resource = $.ajax({
        url: "/api/resource",
        statusCode: {
            400: function() {
                alert('Since we did not send an access token we get client error');
            },
            401: function() {
                alert('You are not authenticated, if a refresh token is present will attempt to refresh access token');
            }
        }
    })
    .done(function(data) {
        alert(JSON.stringify(data));
    });
}

Client.prototype._setupAuth = function _setupAuth() {
    var self = this;

    this.authClient = new jqOAuth({
        events: {
            login: function() {
                alert("You are now authenticated.");
            },
            logout: function() {
                alert("You are now logged out.");
            },
            tokenExpiration: function() {
                return $.post("/refresh-token").success(function(response){
                    self.authClient.setAccessToken(response.accessToken, response.accessTokenExpiration);
                });
            }
        }
    });
}

Client.prototype._setupEventHandlers = function _setupEventHandlers() {
    $("#login").click(this._login.bind(this));
    $("#request").click(this._request.bind(this));
    $("#logout").click(this._logout.bind(this));
}

$(document).ready(function() {
    var client = new Client;
});
```

<p>
    Three things are going on here that are worth noticing.
</p>

#### 1. Login callback

<p>
    Once the user hits our login button his credentials are sent to our login endpoint <code>POST /login</code>.
    If we get an access token back we store it using <code>jquery-oauth</code>.
</p>

#### 2. jquery-oauth for access token management and storage

<p>
    <a href="https://github.com/esbenp/jquery-oauth" target="_blank">esbenp/jquery-oauth</a> is setup as the
    first thing for managing access tokens. It will store the access token in localstorage. If you refresh
    the page it will look for the access token and reauthenticate the user if a token was found. When a new
    access token is passed to the manager it will automatically add a authorization header
    <code>Authorization: Bearer {accessToken}</code> to all subsequent requests. This is used by our API
    to authenticate the user.
</p>

<p>
    Because access tokens are short lifed by design (10 minutes) the user will eventually request the API with
    an expired token. When this happens a 401 response will be sent back to the client. jquery-oauth picks up
    on this response and buffers all the requests that are sent to the server with 401 responses. It will request
    a new access token using the refresh token. If the refresh token is still valid and an new access token is
    acquired all the buffered requests will be refired.
</p>

#### 3. API requests have proper headers automatically set

<p>
    As previosuly mentioned when we call <code>GET /resource</code> using <code>$.ajax</code> jquery-oauth
    will already have our access token attached as a header. The OAuth2 middleware on the server will check
    if the access token is valid before returning our resource.
</p>

## Conclusion

<p>
    We have now (1) installed an OAuth2 Lumen server, (2) configured it with proper management and user infrastructure,
    and lastly (3) created a simple example of a client that effectively manages tokens and API requests.
</p>

<p>
    The code for this post can be found at <a href="https://github.com/esbenp/lumen-api-oauth" target="_blank">https://github.com/esbenp/lumen-api-oauth</a>.
    Questions are welcome at <a href="mailto:ep@traede.com">ep@traede.com</a> or twitter <a href="https://twitter.com/esbenp">@esbenp</a>
</p>
