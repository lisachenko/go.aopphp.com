---
layout: post
title: "Implementing logging aspect with Doctrine annotations"
date: 2013-07-21 22:38
comments: true
categories: aop cookbook
keywords: logging, php, aop, monolog, annotations
description: |
    Logging is one of the most ubiquitous tasks encountered in PHP. We use logs to track error messages, record important events, and debug problems with our code. In any PHP project, the code is likely to be full of calls to a logging library which handles these actions for us. Unfortunately, having calls to a logging library scattered throughout our code makes the code dependent on the availability of that library, a clear violation of the Dependency Inversion Principle. This article describes the solution to remove this cross-cutting concern into one single place with aspect-oriented programming and Doctrine annotations.
---

Logging is probably the most mentioned sweet example of AOP. Probably because it is the simplest and most straightforward example most people can think of. So I want to show you the easiest ever way to implement logging in PHP with annotations. This article is the second part of my previous article about [application-level logging with AOP in PHP](/blog/2013/06/02/application-level-logging-with-aop-and-monolog/) and if you haven't read it yet please do this before proceeding to this article.
<!-- more -->

It's not a secret that every business application requires logging of different actions in different places of the code. Why we need this logging? Is it possible not to use it? The answer is obvious. No, we can't, we should write logs to be able to analyze them in case something goes wrong. Logs can give us the answers: what happened, when it happened and why it happened.

Let's have a look at a typical class which is doing some important job for us:

```php
class Account
{
    protected $amount = 0;

    public function depositMoney($amount)
    {
        $this->amount += $amount;
    }

    public function withdrawMoney($amount)
    {
        $this->amount -= $amount;
    }
}
```

Of course, it's the simplest implementation and real class can contain much more lines of code. Suppose that we deployed this code to the production server and after a while our project manager says that sometimes deposit withdrawal is not working. Oops!

Best solution in that case is to add logging to all the important methods to be sure that everything is working as expected. To detect an error we should write log before and after method execution. Then we can look into log and just count pairs "before-after". If there isn't "after" log record then we have an error and corresponding "before" record will give us an idea why we have this error.

To write the logs we need a logger instance in our class and it's another disadvantage of OOP version of logging. Injecting logger adds a dependency which is not really needed by our class:

```php
class Account
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    // source code here
}
```

Cross-cutting concerns such as logging can not be easily extracting on OOP level into the single class and this means that we should add logging to each method:

```php
class Account
{
    protected $amount = 0;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    public function depositMoney($amount)
    {
        $this->logger->info("Preparing to deposit money", array('amount' => $amount));
        try {
            $this->amount += $amount;
            $this->logger->info("Deposit money successful", array('amount' => $amount));
        } catch (Exception $e) {
            $this->logger->info("Deposit money error: " . $e, array('amount' => $amount));
            throw $e;
        }
    }

    public function withdrawMoney($amount)
    {
        $this->logger->info("Preparing to withdraw money", array('amount' => $amount));
        try {
            $this->amount -= $amount;
            $this->logger->info("Withdraw money successful", array('amount' => $amount));
        } catch (Exception $e) {
            $this->logger->info("Withdraw money error: " . $e, array('amount' => $amount));
            throw $e;
        }
    }
}
```
Wow! Logging is so annoying! Instead of single line of useful code we have 6! For each important method! In my previous article we refactored logging to the aspect class:

```php
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
        $this->logger->info(
            "Executing " . $invocation->getMethod()->name,
            $invocation->getArguments()
        );
    }
}
```

This aspect is cool but it is too global due to the `within(**)` pointcut that matches all public and protected methods in every class. But what should we do if we want to log only specific methods in the application? We definitely need some markers for loggable methods. In the Java world we can use native annotations to mark the methods, but for PHP there isn't native support for annotations. However, there is a cool [Doctrine Annotations](http://docs.doctrine-project.org/projects/doctrine-common/en/latest/reference/annotations.html) library that can be used in our own application to implement doc block annotations.

Let's create an annotation class for @Loggable marker:

```php
namespace Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * Loggable marker
 *
 * @Annotation
 */
class Loggable extends Annotation
{

}
```

The next step is to register this annotation in the registry of Doctrine (this is only needed if you did not register an autoloader previously):

```php
use Doctrine\Common\Annotations\AnnotationRegistry;

AnnotationRegistry::registerFile(__DIR__ . './Annotation/Loggable.php');
```

Now we can modify an aspect by writing a pointcut expression that will match only specific methods. A pointcut expression is an expression that specifies where in the code the advice will be applied. With Go! AOP, you can create a pointcut by specifying namespace, class and method attributes among other things. But the easiest way to specify a pointcut for the logging aspect is by matching methods that have a specific `Annotation\Loggable` annotation marker in the phpDoc-block

```php
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;
use Go\Lang\Annotation\Pointcut;

class LoggingAspect implements Aspect
{
    /**
     * Instance of logger (injected in constructor or by setter)
     */
    protected $logger;

    /**
     * @Pointcut("@annotation(Annotation\Loggable)")
     */
    protected function loggablePointcut() {}

    /**
     * @Around("$this->loggablePointcut")
     * @return mixed
     */
    public function aroundLoggable(MethodInvocation $invocation)
    {
        $method = $invocation->getMethod()->name;
        $this->logger->info("Entering " . $method, $invocation->getArguments());
        try {
            $result = $invocation->proceed();
            $this->logger->info("Success: " . $method);
        } catch (Exception $e) {
            $this->logger->error("Error: " . $method . ' details: ' . $e);
            throw $e;
        }
        return $result;
    }
}
```
Using the annotation means that developers never need to alter the pointcut expression to add or remove methods to the pointcut. A developer only has to add the annotation to a method to have the logging aspect applied. Let's refactor our original class to use only annotation for loggable methods:

```php

use Annotation\Loggable;

class Account
{
    protected $amount = 0;

    /**
     * @var LoggerInterface
     */
    protected $logger;

    public function __construct(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * @Loggable
     */
    public function depositMoney($amount)
    {
        $this->amount += $amount;
    }

    /**
     * @Loggable
     */
    public function withdrawMoney($amount)
    {
        $this->amount -= $amount;
    }
}
```

We can also remove logger dependency, as there is no need in it any more:

```php

use Annotation\Loggable;

class Account
{
    protected $amount = 0;

    /**
     * @Loggable
     */
    public function depositMoney($amount)
    {
        $this->amount += $amount;
    }

    /**
     * @Loggable
     */
    public function withdrawMoney($amount)
    {
        $this->amount -= $amount;
    }
}
```

We just finished our refactoring to use transparent logging with Go! AOP and Doctrine annotations. By having `@Loggable` marker we still have a good understanding that this method should be logged and have a nice aspect that do logging in one place.