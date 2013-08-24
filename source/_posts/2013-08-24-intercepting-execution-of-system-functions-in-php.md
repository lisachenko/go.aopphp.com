---
layout: post
title: "Intercepting execution of system functions in PHP"
date: 2013-08-24 17:00
comments: true
categories: aop
keywords: function interception, PHP, system functions, hook, AOP, intercepting function calls, function advice
description: |
    This article describes a possible ways to intercept an execution of system function in the PHP by using aspect-oriented approach with Go! AOP framework and namespace trick. Function interception can be used for different tasks such as testing, implementing authorization control, caching, logging and much more.

---
Intercepting an execution of methods is one of the most common tasks for AOP. In the Java world there are a lot of articles that has a detailed examples for transactional control, logging, authorization, etc. But all AOP stuff in Java is related only to the classes and objects, because functions are not first-class citizens in Java whereas PHP has a good support for functions. By using some tricks we can create a proxies for system functions and add our own interceptors with custom logic. This article will show you how to use AOP techniques with functions in PHP.

<!-- more -->
Ok, let's have some experiments with PHP. Suppose, that we have a nice code that uses the `file_get_contents()` function to load the content of a file and then prints it to the screen:

```php
namespace Test;

class FilePrinter
{
    public function show($filename)
    {
        echo '<pre>', htmlspecialchars(file_get_contents($filename)), '</pre>';
    }
}
```

Is it possible to test this class and method? Of course, yes! We can create a unit test that will generate a temporary file and then just check that content is correct:

```php
namespace Test;

use PHPUnit_Framework_TestCase as TestCase;

class FilePrinterTest extends TestCase
{
    protected $printer;

    public function setUp()
    {
        $this->printer = new FilePrinter();
    }

    public function testShow()
    {
        $file = tempnam("/tmp", "PHP");
        file_put_contents($file, 'test');
        ob_start();
        $this->printer->show($file);
        $content = ob_end_clean();
        $this->assertEqual('test', $content);
        unlink($file);
    }
}
```
Not so cool to use a real filesystem. Real programmers should use [virtual file system](https://github.com/mikey179/vfsStream/wiki)! But is it possible to intercept system functions like `file_get_contents()` in PHP?

I can suppose that most of programmers will argue that it's impossible to intercept system function without extensions such as [runkit](https://github.com/mikey179/vfsStream/wiki). Yes, it's absolutely true that we can not change the function that already loaded into the memory of PHP. However there is a small loophole in the PHP that can be exploited for free.

### Namespace magic
All modern code is using namespaces to organize the better structure, to encapsulate a classes into a group and to avoid name collisions for functions, classes and constants. There are special [namespace resolution rules](http://php.net/manual/en/language.namespaces.rules.php) that are used for resolving relative names to fully-qualified names. Let's have a careful look to the rule number 5:

{% blockquote %}
Inside namespace (say A\B), calls to unqualified functions are resolved at run-time. Here is how a call to function foo() is resolved:
1. It looks for a function from the current namespace: A\B\foo().
2. It tries to find and call the global function foo().
{% endblockquote %}

Wow! Inside namespace calls to unqualified functions are resolved at run-time! This means that we can create a function with the same name as system function in a namespace and it will be used instead of system one. Let's check this fact:

```php
namespace Test;

use PHPUnit_Framework_TestCase as TestCase;

function file_get_contents($filename) {
    return 'Wow!';
}

class FilePrinterTest extends TestCase
{
    protected $printer;

    public function setUp()
    {
        $this->printer = new FilePrinter();
    }

    public function testShow()
    {
        $file = tempnam("/tmp", "PHP");
        file_put_contents($file, 'test');
        ob_start();
        $this->printer->show($file);
        $content = ob_end_clean();
        $this->assertEqual('test', $content);
        unlink($file);
    }
}
```

Pay an attention that we define the function `file_get_contents()` in the namespace `Test`. If we run our test we will see that it is broken, because we expecting 'test' but got 'Wow!'. Very promising ) Small demo is also available at http://3v4l.org/K1b9k

Moreover, if we need to call an original function we can easily do this by using qualified name:

```php
namespace Test;

function file_get_contents($filename) {
    echo 'Wow!';
    return \file_get_contents($filename);
}
```

### Aspect-oriented programming with functions

Go! AOP framework has an experimental support for system function interception from the version 0.4.0. This means that there is an ability to create an advice for system functions!

Function interception can be very-very slow, so please do not try to intercept all system functions in all namespaces. However it's so amazing. Let's try:

* Enable function interception in the kernel by setting 'interceptFunctions' => true for the kernel.
* Create an advice within aspect to intercept functions:
```php
use Go\Aop\Aspect;
use Go\Aop\Intercept\FunctionInvocation;
use Go\Lang\Annotation\Around;

/**
 * Function interceptor aspect
 */
class FunctionInterceptorAspect implements Aspect
{

    /**
     * @param FunctionInvocation $invocation
     *
     * @Around("execution(Test\*(*))")
     *
     * @return mixed
     */
    public function aroundFunction(FunctionInvocation $invocation)
    {
        echo 'Calling Around Interceptor for function: ',
            $invocation->getFunction()->getName(),
            '()',
            ' with arguments: ',
            json_encode($invocation->getArguments()),
            PHP_EOL;

        return $invocation->proceed();
    }
}
```
* Register this aspect in the kernel

Here we define an advice with an around pointcut `execution(Test\*(*))`. Pointcut can be translated as "around execution of any (*) system functions inside the `Test` namespace". Body of this method is an advice that will be invoked around the original function. So we have a full control over the return value and original invocation by calling `$invocation->proceed()` at the end.
Look at the screenshot from ZF2 framework:

{% img  /images/function-aop.png 'Function interception with Go! AOP framework' 'ZF2 function interception' %}

If you want to try this by hand, feel free to install the Go! AOP framework with composer and open the `demos/` folder in the browser:

```
composer require lisachenko/go-aop-php:0.4.0
```

Thank you for attention!