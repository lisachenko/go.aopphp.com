---
layout: page
title: "Initial configuration"
description: |
    This article describes initial configuration of Go! AOP kernel to use
    aspect-oriented programming in application
keywords: AOP, application, initial configuration, setup, aspect definition

date: 2013-02-09 00:58
comments: true
sharing: true
footer: true
---

Initial configuration consists of several steps:

1. Create an application aspect kernel
2. Configure the aspect kernel in the front controller
3. Create an aspect
4. Register the aspect in the aspect kernel

### Step 1: Create an application aspect kernel

The aim of this library is to provide easy AOP integration to your application.
Your first step then is to create the `AspectKernel` class
for your application. This class will manage all aspects of your
application in one place.

The library provides base class to make it easier to create your own kernel.
To create your application kernel extend the abstract class `Go\Core\AspectKernel`

```php
<?php
// app/ApplicationAspectKernel.php

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
    }
}
```

### 2. Configure the aspect kernel in the front controller

To configure the aspect kernel, call `init()` method of kernel instance.

```php
<?php
// front-controller, for Symfony2 application it's web/app_dev.php

// Initialize an application aspect container
$applicationAspectKernel = ApplicationAspectKernel::getInstance();
$applicationAspectKernel->init(array(
        'debug' => true, // Use 'false' for production mode
        // Cache directory
        'cacheDir' => __DIR__ . './../cache/', // Adjust this path if needed
        // Include paths restricts the directories where aspects should be applied, or empty for all source files
        'includePaths' => array(
            __DIR__ . '/../src/'
        )
));
```

### 3. Create an aspect

Aspect is the key element of AOP philosophy. And Go! library just uses simple PHP classes for declaring aspects!
Therefore it's possible to use all features of OOP for aspect classes.
As an example let's intercept all the methods and display their names:

```php
<?php
// Aspect/MonitorAspect.php

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
class MonitorAspect implements Aspect
{

    /**
     * Method that will be called before real method
     *
     * @param MethodInvocation $invocation Invocation
     * @Before("execution(public Example->*(*))")
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

Easy, isn't it? We declared here that we want to install a hook before the execution of
all dynamic public methods in the class Example. This is done with the help of annotation
`@Before("execution(public Example->*(*))")`
Hooks can be of any types, you will see them later.
But we doesn't change any code in the class Example! I can feel you astonishment now )

### 5. Register the aspect in the aspect kernel

To register the aspect just add an instance of it in the `configureAop()` method of the kernel:

``` php
<?php
// app/ApplicationAspectKernel.php

use Aspect/MonitorAspect;

//...

    protected function configureAop(AspectContainer $container)
    {
        $container->registerAspect(new MonitorAspect());
    }

//...
```

Now you are ready to use the power of aspects! Feel free to change anything everywhere.