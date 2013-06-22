---
layout: post
title: "Application-level logging with AOP and Monolog"
date: 2013-06-02 13:50
comments: true
categories: aop cookbook
keywords: logging, php, aop, monolog
description: |
    Logging is the process of recording events, with a computer program usually an application software in a certain scope in order to provide an audit trail that can be used to understand the activity of the system and to diagnose problems.
    Logs are essential to understand the activities of complex systems, particularly in the case of applications with little user interaction (such as server applications).
    This article describes the aspect-oriented solution for writing logs with the help of Monolog and Go! AOP framework.
---

So you got finished with your brand new website. It is completely PHP driven and looks very nice. But are you sure that
everything works perfectly? Under every circumstances?

No. You can never be absolutely sure. That is why you need a log file to see if there where some errors. Well, if you
are the kind of person that doesn’t care if some jerks who behaved wrong on you website get error messages, then you
probably don’t need an error log file.
<!-- more -->

If you decide to to write the logs, then you definitely choose the best tool for this - [Monolog](https://github.com/Seldaek/monolog).
Monolog is used by such frameworks as Symfony2, Silex, Laravel4, PPI and can be easily integrated into custom application.

But even such a great tool like Monolog can not help us with encapsulating the logging into separate class, because
logging is a [cross-cutting concern](http://en.wikipedia.org/wiki/Cross-cutting_concern). This means that logging do not
fit cleanly into object-oriented programming. As a result, the code addressing the logging must be scattered, or duplicated,
across the various related locations, resulting in a loss of modularity.

Aspect-oriented programming aims to encapsulate cross-cutting concerns into aspects to retain modularity.
This allows for the clean isolation and reuse of code addressing the cross-cutting concern. By basing designs on
cross-cutting concerns, software engineering benefits are effected, including modularity and simplified maintenance.
This article will show you how to combine the power of AOP with the ease of use of Monolog to implement application-level
 logging just in few lines.

### Installation

First of all, we need to install Monolog (if it isn't available). Monolog is available on Packagist (monolog/monolog)
and as such installable via Composer:

```bash
$ composer require monolog/monolog
```

If you do not use Composer, you can grab the code of Monolog from GitHub, and use any PSR-0 compatible autoloader
(e.g. the Symfony2 ClassLoader component) to load Monolog classes.

Next tool that we need is [Go! Aspect-Oriented Framework](http://go.aopphp.com). You can find more information about installation
and configuration in the [documentation](/docs/) section.

If you use a Composer, then installation is quite easy:

```bash
$ composer require lisachenko/go-aop-php
```

Small configuration is required to prepare the AOP kernel for work. Detailed instructions are
available [here](/docs/initial-configuration/). After installation and configuration we are ready for the dark power of
AOP

### Implementing logging aspect

So, what is aspect?

{% blockquote %}
In computer science, an aspect of a program is a feature linked to many other parts of the program, but which is not related to the program's primary function.
{% endblockquote %}

Go! framework defines an aspect as a typical class with methods-advices. Each advice contains a specific logic that can be
invoked before, after or around specific part of code in your application. Let's try to describe an advice for logging.

Suppose, that we want to log an execution of methods. We want to write a method name and an arguments before execution of a method.
Typically this is looking like this:

```php
<?php
class Example
{
    /**
     * Instance of logger (injected in constructor or by setter)
     */
    protected $logger;

    public function test($arg1, $arg2)
    {
        $this->logger->info("Executing " . __METHOD__, func_get_args());
        // ... logic of method here
    }

    public function anotherTest($arg1)
    {
        $this->logger->info("Executing " . __METHOD__, func_get_args());
        // ... logic of method here
    }
}
```

We can notice that the code addressing the logging is scattered and duplicated. To solve this issue we can extract this code into the separate "before" advice:

```php
<?php
use Go\Aop\Aspect;

class LoggingAspect implements Aspect
{
    /**
     * Instance of logger (injected in constructor or by setter)
     */
    protected $logger;

    public function beforeMethodExecution()
    {
        $this->logger->info("Executing " . __METHOD__, func_get_args());
        // return the control to original code
    }
}
```

Ok, we have extracted the advice itself, but how we get a method name and arguments? Go! framework contains a specific class, that implements `MethodInvocation` interface. This interface gives an information about joinpoint by providing an access to the reflection object. Each advice is receiving an instance of this class as an argument:

```php
<?php
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;

class LoggingAspect implements Aspect
{
    /**
     * Instance of logger (injected in constructor or by setter)
     */
    protected $logger;

    public function beforeMethodExecution(MethodInvocation $invocation)
    {
        $this->logger->info("Executing " . $invocation->getMethod()->name, $invocation->getArguments());
    }
}
```
There is one more question to solve: "how to specify concrete methods?". This is known as a pointcut - the term given to the point of execution in the application at which cross-cutting concern needs to be applied.

Go! framework uses annotations for defining pointcuts. Pointcut syntax is like an SQL for the source code. To intercept each public and protected method in the class we can use "within" pointcut:

```php
<?php
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Around;

class LoggingAspect implements Aspect
{
    /**
     * Instance of logger (injected in constructor or by setter)
     */
    protected $logger;

    /**
     * @Before("within(**)")
     */
    public function beforeMethodExecution(MethodInvocation $invocation)
    {
        $this->logger->info("Executing " . $invocation->getMethod()->name, $invocation->getArguments());
    }
}
```

Pointcut syntax allows many constructions, for example: "within(My\Super\Class)", "execution(public ClassName->*(*))", "@annotation(Annotation\Class\Name)" and more. You can play with pointcuts to look at result )

To register the aspect just add an instance of it in the `configureAop()` method of the kernel:

```php
<?php
// app/ApplicationAspectKernel.php

use LoggingAspect;

//...

    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new LoggingAspect());
    }

//...
```

If you don't know how to inject a logger, you can try to initialize it in the aspect constructor:

```php
<?php
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\After;
use Go\Lang\Annotation\Before;
use Go\Lang\Annotation\Around;

use Monolog;

/**
 * Logging aspect
 */
class LoggingAspect implements Aspect
{
    protected $logger;

    public function __construct()
    {
        $this->logger = new Monolog\Logger('test');
        $this->logger->pushHandler(new Monolog\Handler\StreamHandler('php://output'));
    }

    /**
     * @Before("within(**)")
     */
    public function beforeMethodExecution(MethodInvocation $invocation)
    {
        $obj   = $invocation->getThis();
        $class = $obj === (object)$obj ? get_class($obj) : $obj;
        $this->logger->info("Executing " . $class.'->'.$invocation->getMethod()->name, $invocation->getArguments());
    }
}
```

If you run an application you can get an output like this (example for a Go! [demo](https://github.com/lisachenko/go-aop-php/blob/master/demos/life.php)):
```
Want to eat something, let's have a breakfast!<br>
[2013-06-02 11:50:14] test.INFO: Executing Example\Human->eat [] []
[2013-06-02 11:50:14] test.INFO: Executing Example\Human->washUp [] []
Washing up...<br>
Eating...<br>
[2013-06-02 11:50:14] test.INFO: Executing Example\Human->cleanTeeth [] []
Cleaning teeth...<br>
I should work to earn some money<br>
[2013-06-02 11:50:14] test.INFO: Executing Example\Human->work [] []
Working...<br>
It was a nice day, go to bed<br>
[2013-06-02 11:50:14] test.INFO: Executing Example\Human->cleanTeeth [] []
Cleaning teeth...<br>
[2013-06-02 11:50:14] test.INFO: Executing Example\Human->sleep [] []
Go to sleep...<br>
[2013-06-02 11:50:14] test.INFO: Executing Example\User->setName ["test"] []
[2013-06-02 11:50:14] test.INFO: Executing Example\User->setSurname ["a"] []
[2013-06-02 11:50:14] test.INFO: Executing Example\User->setPassword ["root"] []
```

So, we have implemented logging without changes in the original source code! Have a nice experiments!