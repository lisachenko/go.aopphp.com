---
layout: post
title: "What is new in version 0.5.0"
date: 2014-05-24 21:26
comments: true
categories: aop framework
keywords: AOP, release, framework, version, changelog, news
description: |
    New version of Go! AOP framework is almost ready and this article highlights important changes for this release. 
---
New versions of frameworks are always exciting because they provide more features and can contain important fixes and changes. Go! AOP evolves too, so I prepared this article for users to underline most important changes.
<!-- more --> 

### Support for custom streams and read-only file systems

[lisachenko/go-aop-php#118](https://github.com/lisachenko/go-aop-php/pull/118)

This feature is one of the most interesting, because it allows to use framework with any stream filters and wrappers, for example with phar archives. It is possible to generate an AOP cache and put it into the phar archive as a standalone read-only application. Possible usages of aspects in the phar archives are logging, dry-run control and much more. Do you bored with implementation of dry-run option for each command? Just give a try for AOP and define an aspect for that!

### Direct advisors

[lisachenko/go-aop-php#142](https://github.com/lisachenko/go-aop-php/pull/142)

I received a lot of complains about usage of annotations for defining advices instead of pure PHP code. So I decided to give an alternative way for defining advisors with closures:

```php
    protected function configureAop(AspectContainer $container)
    {
        $container->registerAdvisor(
            new DefaultPointcutAdvisor(
                new TrueMethodPointcut(),
                new MethodBeforeInterceptor(function (MethodInvocation $invocation) {
                    echo "Hello", $invocation->getMethod()->name;
                })
            ),
            'test'
        );
    }
```
This patch also optimizes injection of advices (interceptors) into the concrete class, so no more slow `serialize()/unserialize()` functions, they were replaced by `var_export()` and direct injection. I'm also thinking about DSL builders for defining pointcuts in a more natural way:

```php
    protected function configureAop(AspectContainer $container)
    {
        $builder = new PointcutBuilder($container);
        $builder->before('execution(public **->get(*)')->do(function (MethodInvocation $invocation) {
            echo "Hello", $invocation->getMethod()->name;
        });
    }
```
But this is not included into the current version, please ping me on github if your want it for the next version.

### Annotation class filter

[lisachenko/go-aop-php#128](https://github.com/lisachenko/go-aop-php/pull/129)

PhpDeal Desing by Contract frameworks requires matching of classes based on presence of annotation in the class docblock. This functionality was missed in the framework. Now it's possible to use special `@within(AnnotationClassName)` syntax to match classes that have `AnnotationClassName` annotation.

Here is an example of pointcut that intercepts execution of all public methods in the class marked with `Invariant` annotation:

```php

    /**
     * Verifies invariants for contract class
     *
     * @Around("@within(PhpDeal\Annotation\Invariant) && execution(public **->*(*))")
     * @param MethodInvocation $invocation
     *
     * @throws ContractViolation
     * @return mixed
     */
    public function invariantContract(MethodInvocation $invocation) {...}
```

### Access to a doctrine annotations from the MethodInvocation class

[lisachenko/go-aop-php#66](https://github.com/lisachenko/go-aop-php/issues/66)

Some aspects can analyze annotation to perform additional steps. Consider the following class where we define a ttl in the annotation for a method:

```php
use Demo\Annotation\Cacheable;

class General
{
    /**
     * Test cacheable by annotation
     *
     * @Cacheable(time=10)
     * @param float $timeToSleep Amount of time to sleep
     *
     * @return string
     */
    public function cacheMe($timeToSleep)
    {
        usleep($timeToSleep * 1e6);
        return 'Yeah';
    }
}
```

We can easily define a pointcut and advice that will intercept the execution of methods marked with `Cacheable` annotation and cache their results in the cache for a specified time. Should be cool, isn't it? Let's do this:

```php
use Demo\Annotation\Cacheable;
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;

class CacheAspect implements Aspect
{
    /**
     * Cacheable methods
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("@annotation(Demo\Annotation\Cacheable)")
     */
    public function aroundCacheable(MethodInvocation $invocation)
    {
        /** @var Cacheable $cacheable */
        $cacheable = $invocation->getMethod()->getAnnotation(Cacheable::class);
        echo $cacheable->time; // TTL for the cache
        return $invocation->proceed();
    }
}
```

### Simplified pointcut syntax for methods in the same class

[lisachenko/go-aop-php#113](https://github.com/lisachenko/go-aop-php/issues/113)

Instead of complex pointcut like this:
```
execution(public **\*Controller->create(*) )
|| execution(public **\*Controller->get(*) )
|| execution(public **\*Controller->update(*) )
|| execution(public **\*Controller->delete(*) )
```

it is possible to use or'ed constructions with `|` sign:
```
execution(public **\*Controller->create|get|update|delete(*))
```
Much cleaner and more readable!

### Inheritance analysis during load-time

[lisachenko/go-aop-php#131](https://github.com/lisachenko/go-aop-php/issues/131)

It is not a secret, that framework performs load-time weaving of aspects during loading the source file into PHP. Before PHP will be able to parse it and load, Go! AOP scans tokens and builds reflection to analyze a pointcuts. At that moment of class loading there is no information about full inheritance of class (interfaces, abstract classes, traits). This was known limitation of framework that it wasn't possible to match parent methods in the children class:

```php
class Greeting
{
    public hello($name)
    {
        echo "hello $name";
    }
}

class Example extends Greeting
{
}

// pointcut is @Before("execution(public Example->*(*))")

$e = new Example();
$e->hello("User"); // advice is not applied, because method is defined in the Greeting class
```

After some research I found a way to recursively load parent classes and scan tokens. This is the major change for a dessert, but it can have an impact on the current aspects that uses `within` or similar pointcuts.

