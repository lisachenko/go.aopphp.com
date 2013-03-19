---
layout: post
title: "Implementing reusable fluent interface pattern in PHP with AOP"
date: 2013-03-19 22:44
comments: true
categories: aop pattern
keywords: fluent interface, pattern, php, aop, method chaining, human interface, readability
description: |
    Fluent interface is an implementation of an object oriented API that aims to provide for more readable code.
    A fluent interface is normally implemented by using method chaining to relay the instruction context of a subsequent call.
    This article describes an implementation of this pattern with the help of AOP techniques.

---

During software development one of the most important goals is the readability of source code. There are special
techniques and tips that help us to improve the readability of source code. One of the techniques of improving the
source code readability is using of fluent interfaces. Let's discuss it in this article.
<!-- more -->

### Evolution. From simple to complex.

Every programmer starts with the language by writing a trivial "Hello, world!". After that, it takes several years to
learn the language and to make clumsy attempts to write his own ORM / CMS / Framework (underline one or several). I
think that everyone has the code which is better to hide. But without understanding of simple things you will not be
able to understand the complex ones, so let's start with a simple example and get to the implementation of the "fluent"
interface as a separate class using AOP. Those who know this pattern can safely move on to the last part of the article,
where you can get excellent food for thoughts.

Let's start!

Let's take a simple user entity, which has the following properties: name, last name and the password:

```php
<?php
class User
{
    public $name;
    public $surname;
    public $password;
}
```
An excellent class that is easy to use:
```php
<?php
$user = new User;

$user->name = 'John';
$user->surname = 'Doe';
$user->password = 'root';
```

It is easy to notice that we have no validation and somebody can set a blank password, which is not very good. In
addition, it would be nice to know that the field values are immutable. These several considerations lead us to the
idea that properties should be protected or private and access to them should be carried out with a pair of getter / setter.

Suit the action to the word:
```php
<?php
class User
{
    protected $name;
    protected $surname;
    protected $password;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setSurname($surname)
    {
        $this->surname = $surname;
    }

    public function setPassword($password)
    {
        if (!$password) {
            throw new InvalidArgumentException("Password shouldn't be empty");
        }
        $this->password = $password;
    }
}
```
For the new class the configuration has changed a little and now we are using setters:
```php
<?php
$user = new User;

$user->setName('John');
$user->setSurname('Doe');
$user->setPassword('root');
```

Not a big deal, right? But what if we need to set up 20 properties? 30 properties? This code will be flooded with setter
calls and $user variable references. If the variable name will be $superImportantUser then readability of the source
code deteriorates further. What can be done to get rid of the copy of the code?

### Fluent interface to the rescue!

So, we came to the Fluent Interface pattern, which was coined by Eric Evans and Martin Fowler to increase readability of
source code by simplifying multiple calls to the same object. It is implemented by a chain of methods, transmitting the
calling context to the following method in the chain. The context is the return value of the method and this value can
be any object, including the current one.

To implement a fluent interface, we need all the methods-setters to return the current object:
```php
<?php
class User
{
    protected $name;
    protected $surname;
    protected $password;

    public function setName($name)
    {
        $this->name = $name;
        return $this;
    }

    public function setSurname($surname)
    {
        $this->surname = $surname;
        return $this;
    }

    public function setPassword($password)
    {
        if (!$password) {
            throw new InvalidArgumentException("Password shouldn't be empty");
        }
        $this->password = $password;
        return $this;
    }
}
```
This approach will allow us to make the call chain:

```php
<?php
$user = new User;
$user->setName('John')->setSurname('Doe')->setPassword('root');
```

As you can see, the configuration of the object now takes up less space and is much easier to read. We achieved our
goal! At this point, many developers will ask: "So what?" Ok, then try to answer the question: "What's wrong with fluent
interface in this way?" before reading the next section.

### So what's bad in it?

Perhaps you've failed to find an answer and you've decided to read further? ) Well, I can reassure you that it's all
fine with fluent interface in OOP. However, if you think about it, you can find out that it can't be implemented as a
separate class, interface or trait. So it can't be reused. This results in the fact that we have to put down "return
$this" at the end of each method to implement fluent interface. If we have a couple dozen classes with a couple dozen
methods that we want to do "fluent" then we have to manually deal with this unpleasant operation.
This is the classic crosscutting.

### Let's make it in a separate class

Since we have a crosscutting, we must rise to the level above OOP to describe this pattern. Specification is pretty
simple: when calling public methods in a class, original object should be returned as the result of the method
invocation. Of course, we do not want unexpected effects, so let's be clear: methods should be public setters starting
with "set" and they should be inside classes that implement FluentInterface marker only . Let's describe this with AOP
and Go! AOP library.

First of all, our marker interface:

```php
<?php
/**
 * Fluent interface marker
 */
interface FluentInterface
{

}
```
Fluent interface implementation in the form of advice within aspect:

```php
<?php
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;

class FluentInterfaceAspect implements Aspect
{
    /**
     * Fluent interface advice
     *
     * @Around("within(FluentInterface+) && execution(public **->set*(*))")
     *
     * @param MethodInvocation $invocation
     * @return mixed|null|object
     */
    protected function aroundMethodExecution(MethodInvocation $invocation)
    {
        $result = $invocation->proceed();
        return $result!==null ? $result : $invocation->getThis();
    }
}
```

Just a quick explanation - "Around" advice sets the hook "around" the original method and is fully responsible for
whether the original method will be called or not, and is responsible for the result that will be returned. In the
advice, we call original method (setter invocation) and if it doesn't return anything we just return the original
object $invocation->getThis(). That is all ) That is a useful pattern implementation just in a few lines of code. Using
the fluent interface is now easy and sexy :)

```php
<?php
class User implements FluentInterface
{
    protected $name;
    protected $surname;
    protected $password;

    public function setName($name)
    {
        $this->name = $name;
    }

    public function setSurname($surname)
    {
        $this->surname = $surname;
    }

    public function setPassword($password)
    {
        if (!$password) {
            throw new InvalidArgumentException("Password shouldn't be empty");
        }
        $this->password = $password;
    }
}
```
And usage:

```php
<?php
$user = new User;
$user->setName('John')->setSurname('Doe')->setPassword('root');
```
No more copying "return $this" for hundreds of methods, only pure source code, intuitive FluentInterface and
implementation of FluentInterface in the form of a simple aspect.