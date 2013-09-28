---
layout: post
title: "Aspect-Oriented programming with Yii"
date: 2013-09-28 16:28
comments: true
categories: aop framework cookbook
keywords: Yii, AOP, Go!, PHP, framework, configuration, installation
description: |
    Configuration of framework is typically tricky process, so this article gives a how-to manual to start playing with Go! AOP and famous Yii frameworks.
---
Aspect-Oriented programming becomes more popular for PHP, but it requires good knowledge of OOP and can be very cumbersome for beginners. So it's important to have a working examples and manuals to have a possibility to run it locally with favorite framework. Go! AOP provides all the necessary functionality for configuration of AOP into any frameworks, but integration process can be very tricky, so configuration is not so easy. In this article we will configure a working example for Yii framework.
<!-- more -->

Let's start! First of all, we need an empty `yii-aspect` directory for our project:

```bash
mkdir yii-aspect && cd yii-aspect
```

Next step is to install dependencies: Yii and Go! AOP frameworks. Go! AOP is registered on packagist and can be easily installed with composer:
```bash
composer require lisachenko/go-aop-php:0.4.*
```
Latest version of Yii framework (1.1.14) is on [packagist](https://packagist.org/packages/yiisoft/yii) too! This means that we can install it without any hacks with local repositories:
```bash
composer require yiisoft/yii:1.1.*
```

This is a minimum list of dependencies for our first example. Following step is very easy, thanks to the `yiic` console command. By default, all binaries are installed into `./vendor/bin` folder, so `yiic` console should be there too.

Let's create a web application:
```bash
yiic webapp app
```
Yii will generate a directory `app` with default project structure and files. This project can be opened in the browser, but AOP isn't enabled right now. To enable AOP we should prepare our application to have an ability to use it. To enable AOP we need to update the front controller of our application (`./app/index.php`) in the following way:

Add this lines to the top of file before original content:
```php
<?php
use Go\Instrument\Transformer\FilterInjectorTransformer;

// Load the composer autoloader
include __DIR__ . '/../vendor/autoload.php';

// Load AOP kernel
include __DIR__ . '/aspect.php';
```

And replace initialization of Yii at the bottom of file from `require_once($yii)` to `require_once(FilterInjectorTransformer::rewrite($yii))`. This is needed to give a hook for the Go! AOP framework to weave aspects into classes. Aspects are defined as separated classes and included in the `./aspect.php` file. Let's move to it and to the aspect kernel

```php

include __DIR__ . '/protected/extensions/go-aop-php/ApplicationAspectKernel.php';

// Prevent an error about nesting level
ini_set('xdebug.max_nesting_level', 500);

// Initialize an application aspect container
$applicationAspectKernel = ApplicationAspectKernel::getInstance();
$applicationAspectKernel->init(array(
    'debug' => true,
    // Application root directory
    'appDir'   => __DIR__ . '/../',
    // Cache directory
    'cacheDir' => __DIR__ . './protected/aspect',
    'excludePaths' => array(
        __DIR__ . './protected/aspect'
    )
));
```

This is typical configuration of Go! AOP framework where we can adjust some directories and paths. I think that this config is pretty clear to understand. Only `debug` parameter is really important. For production mode it should be `false`, but for development mode it should be enabled to enable better debugging and cache refreshing.

In this file we also include a strange file `ApplicationAspectKernel.php`. This file contains definition of aspect kernel for our application and it's very simple:

```php

use Go\Core\AspectKernel;
use Go\Core\AspectContainer;

/**
 * Application Aspect Kernel
 */
class ApplicationAspectKernel extends AspectKernel
{

    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    protected function configureAop(AspectContainer $container)
    {
        // todo: register aspects, advisors, pointcuts, etc
    }
}
```

There is only one method to define: `configureAop` that is used for AOP configuration. We can create an aspect now and register it in the kernel:

```php
<?php
// app/protected/extensions/go-aop-php/TestMonitorAspect.php

namespace Aspect;

use Go\Aop\Aspect;
use Go\Aop\Intercept\FieldAccess;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Around;
use Go\Lang\Annotation\Pointcut;

/**
 * Monitor aspect
 */
class TestMonitorAspect implements Aspect
{

    /**
     * Method that will be called before real method
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("within(**)")
     */
    public function beforeMethodExecution(MethodInvocation $invocation)
    {
        $obj = $invocation->getThis();
        echo 'Calling Before Interceptor for method: ',
        is_object($obj) ? get_class($obj) : $obj,
        $invocation->getMethod()->isStatic() ? '::' : '->',
        $invocation->getMethod()->getName(),
        '()',
        ' with arguments: ',
        json_encode($invocation->getArguments()),
        "<br>\n";
    }
}
```
... and registration in the kernel:

```php
<?php
// app/protected/extensions/go-aop-php/ApplicationAspectKernel.php

require_once 'TestMonitorAspect.php';

use Aspect\TestMonitorAspect;
use Go\Core\AspectKernel;
use Go\Core\AspectContainer;

/**
 * Application Aspect Kernel
 */
class ApplicationAspectKernel extends AspectKernel
{

    /**
     * Configure an AspectContainer with advisors, aspects and pointcuts
     *
     * @param AspectContainer $container
     *
     * @return void
     */
    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new TestMonitorAspect());
    }
}
```

That's all )

<blockquote class="twitter-tweet" data-conversation="none"><p>Just have recreated <a href="https://t.co/FqlECETHO7">https://t.co/FqlECETHO7</a> to use latest <a href="https://twitter.com/search?q=%23yii&amp;src=hash">#yii</a> 1.1.x and Go! AOP with <a href="https://twitter.com/search?q=%23composer&amp;src=hash">#composer</a> /cc <a href="https://twitter.com/afdiaz">@afdiaz</a> <a href="https://twitter.com/sam_dark">@sam_dark</a></p>&mdash; Alexander Lisachenko (@lisachenko) <a href="https://twitter.com/lisachenko/statuses/383317369872855040">September 26, 2013</a></blockquote>

Just refresh the page in the browser to see a result. All methods will be intercepted by our advice `beforeMethodExecution`:

{% img  /images/yii-aop.png 'Yii with Go! AOP framework' 'Yii methods interception' %}

PS. If you want to create an empty project with single line you can run:

```bash
composer create-project lisachenko/yii-aspect --prefer-source --stability=dev
```