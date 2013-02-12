---
layout: page
title: "Pointcuts and advices"
date: 2013-02-12 22:51
keywords: pointcut, introduction, advice, ordering, definition, annotation, after throwing, interceptor
comments: true
sharing: true
footer: true
---

Pointcut and advice declarations can be made using the @Pointcut, @Before, @After, @AfterThrowing, and @Around annotations.

## Advice

In this section we first discuss the use of annotations for simple advice declarations. Then we show how JoinPoint and its siblings are handled in the body of advice and discuss the treatment of proceed in around advice.

Using the annotation style, an advice declaration is written as a regular PHP method with one of the @Before, @After, @AfterThrowing, or @Around annotations. Except in the case of around advice, the method should return void. The method should be declared public.

The following example shows a simple before advice

```php
<?php

    /**
     * Method that should be called before real method
     *
     * @param MethodInvocation $invocation Invocation
     * @Before(pointcut="examplePublicMethods()")
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
```

@After advice declarations take exactly the same form as @Before, as do the forms of @AfterReturning and @AfterThrowing that do not expose the return type or thrown exception respectively.

For around advice, we can do anything with original invocation, for example:

```php
<?php

    /**
     * Advice that prevents the execution of original method
     *
     * @param MethodInvocation $invocation Invocation
     * @Around(pointcut="Aspect\DebugAspect->examplePublicMethods()") // Full-qualified pointcut name
     */
    public function preventMethodExecution(MethodInvocation $invocation)
    {
        echo "Execution of method was prevented";
        //return $invocation->proceed();
    }
```

## Pointcuts

Pointcuts are specified using the Go\Lang\Annotation\Pointcut annotation on a method declaration.

As a general rule, the @Pointcut annotated method must have an empty method body. Here is a simple example of a pointcut declaration:

```php
<?php

    /**
     * Pointcut for example class
     *
     * @Pointcut("execution(public Example->*(*))")
     */
    protected function examplePublicMethods() {}
```

Declared pointcut can be referenced in advice by short name `pointcut="examplePublicMethods()"` or by full-qualified name, for example `pointcut="Aspect\DebugAspect->examplePublicMethods()"`.

### Annotation pointcut

Annotation pointcut can be used to match against the set of annotations on the annotated element. An annotation pattern element has following form: `@annotation(Annotation\Class\Name)`. This pointcut will match all methods with `Anotation\Class\Name` annotation in phpDoc block. Following example shows how to implement transparent caching with aspects and annotation pointcut:

First of all, create an annotation class:

```php
<?php

namespace Annotation;

use Doctrine\Common\Annotations\Annotation;

/**
 * @Annotation
 * @Target("METHOD")
 */
class Cacheable extends Annotation
{
}
```

Next step is to add this annotation to method:

```php
<?php

use Annotation\Cacheable;

/**
 * Example class to test aspects
 */
class Example
{

    /**
     * Test cacheable by annotation
     *
     * @Cacheable
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

And then just create an around advice with annotation pointcut:

```php
<?php

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;

/**
 * Cache aspect
 */
class CacheAspect implements Aspect
{
    /**
     * Cacheable methods
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("@annotation(Annotation\Cacheable)")
     */
    public function aroundCacheable(MethodInvocation $invocation)
    {
        static $memoryCache = array();

        $time  = microtime(true);

        $obj   = $invocation->getThis();
        $class = is_object($obj) ? get_class($obj) : $obj;
        $key   = $class . ':' . $invocation->getMethod()->name;
        if (!isset($memoryCache[$key])) {
            $memoryCache[$key] = $invocation->proceed();
        }

        echo "Take ", sprintf("%0.3f", (microtime(true) - $time) * 1e3), "ms to call method<br>", PHP_EOL;
        return $memoryCache[$key];
    }
}
```

## Advice ordering

Each advice can have custom order that will be used during joinpoint invocation. To define the order just fill it in annotation:

```php
<?php

    /**
     * Privileged invocation
     *
     * @param MethodInvocation $invocation Invocation
     * @Before(pointcut="examplePublicMethods()", order=-128)
     */
    public function orderSecond(MethodInvocation $invocation)
    {
        echo "Privileged advice ", "<br>", PHP_EOL;
    }

    /**
     * Non-privileged advice that will be last
     *
     * @param MethodInvocation $invocation Invocation
     * @Before(pointcut="examplePublicMethods()", order=128)
     */
    public function orderFirst(MethodInvocation $invocation)
    {
        echo "I'm last...", "<br>", PHP_EOL;
    }
```

Advice with smallest order will be executed first. If several advices uses the same value for order then they will be executed in the order of registration in the container.

## Introductions

Introductions (known also as inter-type declarations) enable an aspect to declare additional interfaces for advised objects, and to provide an implementation of that interface with the help of [traits](http://php.net/manual/en/language.oop5.traits.php).

An introduction is made using the @DeclareParents annotation for the property inside aspect class. This annotation is used to declare that matching types have a new parent (hence the name). For example, given an interface Serializable, and an implementation of that interface SerializableImpl, the following aspect declares that Example class is also implements the Serializable interface:

```php
<?php

use Go\Lang\Annotation\DeclareParents;

/**
 * Serialization aspect
 */
class SerializableAspect implements Aspect
{

    /**
     * @DeclareParents(value="Example", interface="Serializable", defaultImpl="Aspect\Introduce\SerializableImpl")
     *
     * @var null
     */
    protected $introduction = null;
}
```

Here, `value` is a name of the classes to apply advice (can contain '*' as wildcard), `interface` declares additional interface for classes and `defaultImpl` should specify a trait with realization. For Serializable interface it can looks like this:

```php
<?php

/**
 * Example class to test aspects
 */
trait SerializableImpl
{
    /**
     * String representation of object
     * @return string the string representation of the object or null
     */
    public function serialize()
    {
        return serialize(get_object_vars($this));
    }

    /**
     * Constructs the object
     * @param string $serialized The string representation of the object.
     *
     * @return mixed the original value unserialized.
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);
        foreach($data as $key=>$value) {
            $this->$key = $value;
        }
    }
}
```