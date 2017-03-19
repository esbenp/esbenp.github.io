---
layout:     post
title:      "A modern REST API in Laravel 5 Part 0: Introduction"
subtitle:   "Get started building the REST API of your dreams"
date:       2016-04-11 17:04:00 +0200
author:     "Esben Petersen"
header-img: "img/post-bg-01.jpg"
---

## Welcome to the API of your dreams

<p>
  Bonjour. Welcome to this series on how to build a cool REST API in Laravel 5.
  APIs are all around us. Most of us have tried integrating a third party into our app
  by consuming their API. Whether it is <a href="https://developer.github.com/v3/issues/#create-an-issue">creating issues in GitHub</a>,
  <a href="https://developers.facebook.com/docs/facebook-login">authenticating using Facebook</a> or
  <a href="(https://docs.intercom.io/intercom-s-key-features-explained/tracking-user-data-in-intercom)">tracking users in Intercom</a>
  we utilize the power of APIs to enhance our own app.
</p>

<p>
  What many developers do not realize is that building your own app using an API is not only super useful - it is also relatively easy to get started. If your business is powered by your own API it becomes easier building and maintaining multiple clients (e.g. a desktop app, a smartphone app, tablet app etc.) since they all utilize the same business platform (the API).
</p>

<p style="text-align:center">
  <img src="/img/api-desktop-smartphone-tablet-clients.png" alt="A REST api can serve multiple
  clients, like a smartphone-, tablet- or desktop client">
</p>

<p>
  With an API it also becomes more enjoyable to write those cool javascript clients using
  the <a href="https://facebook.github.io/react/">latest</a>, <a href="https://angular.io/">sexy</a> <a href="http://cycle.js.org/">framework</a>. Just be ware of <a href="https://medium.com/@ericclemmons/javascript-fatigue-48d4011b6fc4#.jdaxtfcdd">javascript</a> <a href="https://segment.com/blog/the-deep-roots-of-js-fatigue/">fatigue</a> ;-).
</p>

### Anywho, this is <u>NOT</u> an introduction to REST APIs

<p>
  There are already plenty of <a href="https://geemus.gitbooks.io/http-api-design/content/en/index.html">good resources on how to get introduced into the world of APIs</a>. Therefore this will not be another
  introduction into what a resource is, or how URLs should be formatted.
</p>

<p>
  For a beginners guide I definitely recommend the short e-book <a href="https://leanpub.com/build-apis-you-wont-hate">"Build APIs You Won't Hate"</a> by <a href="https://twitter.com/philsturgeon">Phil Sturgeon</a>
</p>

## So what are we going to do?

<p>
  This 5-part series will go over implementation details of specific challenges that I have
  faced in developing the <a href="http://traede.com">Traede API</a>
</p>

<h3><a href="/2016/04/11/modern-rest-api-laravel-part-1/">Part 1: A scalable structure</a></h3>

<p>
  The first part is about how we can structure our API, so that it will not blow up in our
  face once it grows in complexity. We will take a stab at structure on three different levels:
</p>

<ol>
  <li>Application flow pattern</li>
  <li>Project folder structure</li>
  <li>Resource folder structure</li>
</ol>

<p>
  <strong><a href="/2016/04/11/modern-rest-api-laravel-part-1/">Go to part 1</a></strong>
</p>

<h3><a href="/2016/04/15/modern-rest-api-laravel-part-2/">Part 2: Creating resources with controls</a></h3>

<p>
  The second part will be building resources. Building resources in itself is pretty
  easy, however the fun part becomes giving our consumers controls like filters, pagination
  and nested relationship controls.
</p>

<p>
  We will try to implement some of <a href="http://optimus.rocks">my own Laravel API libraries</a> in order to achieve this.
</p>

<p>
  <strong><a href="/2016/04/15/modern-rest-api-laravel-part-2/">Go to part 2</a></strong>
</p>

### Part 3: Error handling

<p>
  I will take you through some of the steps we have taken to increase the effictiveness
  of our error handling. This includes writing a custom exception handler that logs exceptions
  in <a href="https://getsentry.com/">Sentry - an online exception tracker service</a>.
</p>

<p>
  <strong><a href="/2017/01/14/modern-rest-api-laravel-part-3/">Go to part 3</a></strong>
</p>

### Part 4: Authentication

<p>
  Almost any API needs some sort of authentication. I have already written a bit about
  <a href="http://esbenp.github.io/2015/05/26/lumen-web-api-oauth-2-authentication/">how
  OAuth can be implemented in the Lumen framework</a>.
</p>

<p>
  In this series we will also be using OAuth for authentication, however we will be
  using Laravel's own implementation of The PHP League's OAuth Server:
  <a target="_blank" href="https://laravel.com/docs/master/passport">Laravel Passport</a>.
</p>

<p>
  <strong><a href="/2017/03/19/modern-rest-api-laravel-part-4/">Go to part 4</a></strong>
</p>

### Part 5: Inbound & outbound webhooks

<p>
  A really kick ass API can integrate with other APIs. This can often happen through webhooks.
  We will look at how you can handle input from other APIs as well as how you can enable consumers
  to integrate with your API using outbound webooks.
</p>

<p>
  <i>Coming soon...</i>
</p>
