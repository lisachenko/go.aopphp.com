---
layout: post
title: "Aspect-Oriented Framework for PHP"
date: 2013-02-11 22:55
comments: true
categories: aop php framework
---

Go! is a PHP 5.4 framework based on OOP and AOP paradigms and designed by Lisachenko Alexander.
It allows developers to add support of AOP to every PHP application.

Go! doesn't require any PECL-extentions, it neither uses any dark magic of Runkit nor evals, the library doesn't use DI-containers.
The code with weaved aspects is fully readable and native, it can be easily debugged with XDebug.
You can debug either classes or aspects.
The main advantage of Go! is that potentially it can be installed in every PHP-application,
because you don't have to change the application source code at all.
As an example, with the help of 10-20 lines of code we can intercept all the public, protected and static methods in all the classes
of application and display the name and the arguments of each method during its execution.

What is AOP?
------------

[AOP (Aspect-Oriented Programming)](http://en.wikipedia.org/wiki/Aspect-oriented_programming) is an approach to cross-cutting concerns, where the concerns are designed and implemented
in a "modular" way (that is, with appropriate encapsulation, lack of duplication, etc.), then integrated into all the relevant
execution points in a succinct and robust way, e.g. through declarative or programmatic means.

In AOP terms, the execution points are called join points, a particular set of them is called a pointcut and the new
behavior that is executed before, after, or "around" a join point is called advice. You can read more about AOP in
[Introduction](/docs/introduction/) section.

PHP traits can be used to implement some aspect-like functionality.