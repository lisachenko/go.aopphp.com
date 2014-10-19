---
layout: post
title: "Caching like a PRO"
date: 2014-10-19 21:02:00 +0400
comments: true
categories: aop cookbook
keywords: caching, php, aop, annotations
description: |
    As web applications become more large-scaled, the questions of performance optimization are more frequently considered in initial design. One of the optimization techniques used extensively is caching. Cache contains pre-processed data which is ready to be used without redoing the processing. This article shows the possible ways of doing caching in PHP, including aspect-oriented approach.
---

As web applications become more large-scaled, the questions of performance optimization are more frequently considered in initial design. One of the optimization techniques used extensively is caching. Cache contains pre-processed data which is ready to be used without redoing the processing. This article shows the possible ways of doing caching in PHP, including aspect-oriented approach.

Caching is probably the most known technique in computer science, it appears everywhere: CPU, disk cache buffers, opcode cache, memcache, SQL cache, etc. Since it is contained everywhere, we can't extract it into a single place to keep it under our control. So cache invalidation is one of the hardest things. There is a good quote:

{% blockquote Phil Karlton %}
There are only two hard things in Computer Science: cache invalidation and naming things.
{% endblockquote %}
Let's have a look at caching in the PHP.
<!-- more -->

### Caching process

Ok, what is caching or cache? 

{% blockquote Cache (computing) http://en.wikipedia.org/wiki/Cache_(computing) %}
In computing, a cache is a component that transparently stores data so that future requests for that data can be served faster. The data that is stored within a cache might be values that have been computed earlier or duplicates of original values that are stored elsewhere. If requested data is contained in the cache (cache hit), this request can be served by simply reading the cache, which is comparatively faster. Otherwise (cache miss), the data has to be recomputed or fetched from its original storage location, which is comparatively slower. Hence, the greater the number of requests that can be served from the cache, the faster the overall system performance becomes.
{% endblockquote %}

So, caching is a technique to optimize the performance of a system by storing data in a fast storage. There is nothing difficult here: just take data from a slow data source and put it into a faster data source. The faster and bigger the cache is, the more performance gain we can receive. A question for self-test: how many types of cache do you know in PHP? 
 
### Caching. Elementary.

Imagine that you have a code in a service class that returns information about something:

```php
class ImportantService
{
    /**
     * Returns information about object by its unique identifier
     *
     * @return object
     */
    public function getInformation($uniqueIdentifier)
    {
        return $this->dataSource->getOne($uniqueIdentifier);
    }
}
```

This service and method is pretty clear, but your boss has just discovered that it takes several seconds to query this information and asks you to fix this. What would you do in order to improve the performance of this method? Of course, the easiest way to do this is to write this data into cache and then just check if there is a record in our cache instead of making hard query to a busy data source server.

At elementary level we can do this easy with memcache extension:

```php
class ImportantService
{
    private $cache = null;

    /**
     * Default constructor
     */
    public function __construct()
    {
        $this->cache = $memcache = new Memcache();
        $memcache->addServer('memcache.local');
    }


    /**
     * Returns information about object by its unique identifier
     *
     * @return object
     */
    public function getInformation($uniqueIdentifier)
    {
        $result = $this->cache->get($uniqueIdentifier);
        if ($result === false) {
            $result = $this->dataSource->getOne($uniqueIdentifier);
            $this->cache->set($uniqueIdentifier, $result);
        }
        
        return $result;
    }
}
```
Now we use cache and store results from original data source for future use. On a subsequent query with unique identifier we can take a result from cache and just return it instead of doing hard query. So this code will work faster and this should make your boss happier. However I should put here a warning for beginners not to write code like this. Be more experienced and write code better!

### Caching. Pre-Intermediate.

What's wrong with the previous example with caching? Ok, there are two issues in it. Firstly, we don't use a [Dependency Injection (DI)](http://en.wikipedia.org/wiki/Dependency_injection) to inject instance of cache and we hard-coded cache initialization in constructor. Secondly, we put logic of caching into the original service. This implementation violates [Single Responsibility Principle (SRP)](http://en.wikipedia.org/wiki/Single_responsibility_principle) and can cause a lot of problems with testing, as we won't be able to query the information directly from a data source without caching. We may as well violate another principle - [DRY](http://en.wikipedia.org/wiki/Don%27t_repeat_yourself). This may occur if there are several methods in the class that should be cached, and we write extra lines of code in each method:

```php

$result = $this->cache->get($uniqueIdentifier);
if ($result === false) {
    $result = ...
    $this->cache->set($uniqueIdentifier, $result);
}

return $result;
```

You can notice that we put the same lines of code everywhere where we need to add caching. This is known as cross-cutting concern. Caching is a typical example of it, and traditional object-oriented paradigm offers only few ways to extract this logic into one place. One of them is proxy pattern: define a class with magic `__call()` method and wrap an object with caching proxy:

```php
class CachingProxy
{
    private $cache = null;
    private $instance = null;
    
    public function __construct(Memcache $cache, $instance)
    {
        $this->cache    = $cache;
        $this->instance = $instance;
    }

    public function __call($method, $arguments)
    {
        if (substr($method, 0, 3) !== 'get') {
            $result = call_user_func_array($method, $arguments);
        } else {
            $uniqueId = $method . serialize($arguments);
            $result = $this->cache->get($uniqueId);
            if ($result === false) {
                $result = call_user_func_array($method, $arguments);
                $this->cache->set($uniqueId, $result);
            }
        }
        
        return $result;
    }
}

$service = new ImportantService();
$cachedService = new CachingProxy($memcacheInstance, $service);

$result = $cachedService->getInformation(123); // First call goes to a data source
$more   = $cachedService->getInformation(123); // From cache now
```

Much better now! We extracted the logic of caching into a separate class and can wrap any instance. Our original service still has transparent logic and doesn't need any instance of cache to work. But this solution has another two issues. The first issue is that proxy slows down execution of each method due to magic `__call()` and slow `call_user_func_array()` function. The second issue is more serious. Proxy violates inheritance and [Liskov Substitution Principle (LSP)](http://en.wikipedia.org/wiki/Liskov_substitution_principle). This means that we can't pass an instance of proxy everywhere where original class is expected:

```php
function expectsImportantService(ImportantService $service) 
{
    return $service->getInformation(123);
}

$service = new ImportantService();
expectsImportantService($service); // OK

$cachedService = new CachingProxy($memcacheInstance, $service);
expectsImportantService($cachedService); // Catchable Fatal Error, expecting instance of ImportantService
```

This example shows that caching proxy is not a perfect solution because it is violating typehints and decreasing performance of application. However, it can be used to extract logic of caching into a single class and to keep original classes clean.

### Caching. Intermediate.

At this level developers understand that caching logic should be separated from original code and LSP should be used. This is possible with decorator pattern, when caching class extends an original service class and overwrites methods to introduce an additional logic. Usually, it is done automatically with reflection and code generation.

```php

class CachedImportantService extends ImportantService
{
    private $cache = null;

    /**
     * Default constructor
     */
    public function __construct($cache)
    {
        $this->cache = $cache
        parent::__construct();
    }

    /**
     * Returns information about object by its unique identifier
     *
     * @return object
     */
    public function getInformation($uniqueIdentifier)
    {
        $result = $this->cache->get($uniqueIdentifier);
        if ($result === false) {
            $result = parent::getInformation($uniqueIdentifier); // call original parent method
            $this->cache->set($uniqueIdentifier, $result);
        }
        
        return $result;
    }
}
```

This solution requires a lot of code generation and it's still duplicated, because we need to override each method that should be cached with our implementation. It also requires to rewrite the source code or adjust definition of service to use an extended `CachedImportantService` instead of the original one. But we can use a framework for this, for example, there is a nice one [Ocramius/ProxyManager](https://github.com/Ocramius/ProxyManager).

Nevertheless, decorators and proxies can't be used for static methods. Imagine that we have `ImportantService::staticGetInformation()` method which is used somewhere in the source code:

```php
class ImportantService
{
    /**
     * Returns information about object by its unique identifier
     *
     * @return object
     */
    public static function getInformation($uniqueIdentifier)
    {
        return self::$dataSource->getOne($uniqueIdentifier);
    }
}

function testStaticMethod()
{
    return ImportantService::getInformation(123); // no way to cache it or to replace with decorator/proxy
}
```

So, even advanced proxies can't help us extract caching logic for static methods into the proxy/decorator. They won't work for final classes either, because a final class can't be extended.

### Caching. Advanced

In this article we compared all the possible ways to cache a result for method. Each of them has its own advantages and issues, because object-oriented paradigm doesn't have any instruments for solving cross-cutting problems. Is there a way to get rid of them? We want to achieve the following things:

 - extract logic of caching into a single class (like with Proxy pattern)
 - use Liskov Substitution and Open-Closed Principles
 - have an ability to cache static methods and methods in a final class.
 
Now you are ready for aspect-oriented paradigm. AOP was designed to solve such cross-cutting issues in an elegant way with advices, aspects and joinpoints. It performs weaving of custom logic into original methods without changing the source code. Caching logic that we extracted for proxy earlier in the article is a typical body of advice in AOP. Our manual check for methods starting with "get" is a definition of pointcut in AOP terms. With AOP we can implement caching as follows:

 - intercepting execution of static and dynamic methods declared as "cacheable" in all classes,
 - adding an extra check for presence of value in the cache before executing the original method,
 - if there isn't any value in the cache, we invoke an original method and store its result in the cache.

My preferred way to declare method as "cacheable" is to use an annotation. 

```php
use Annotation\Cacheable;

class ImportantService
{
    /**
     * Returns information about object by its unique identifier
     * @Cacheable
     *
     * @return object
     */
    public function getInformation($uniqueIdentifier)
    {
        return $this->dataSource->getOne($uniqueIdentifier);
    }
}
```

Then we just need to define an aspect for caching, that will intercept all methods with `Cacheable` annotation:

```php
use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Around;

/**
 * Caching aspect
 */
class CachingAspect implements Aspect
{
    private $cache = null;

    public function __construct(Memcache $cache)
    {
        $this->cache = $cache;
    }
    
    /**
     * This advice intercepts the execution of cacheable methods
     *
     * The logic is pretty simple: we look for the value in the cache and if we have a cache miss
     * we then invoke original method and store its result in the cache.
     *
     * @param MethodInvocation $invocation Invocation
     *
     * @Around("@annotation(Annotation\Cacheable)")
     */
    public function aroundCacheable(MethodInvocation $invocation)
    {
        $obj   = $invocation->getThis();
        $class = is_object($obj) ? get_class($obj) : $obj;
        $key   = $class . ':' . $invocation->getMethod()->name;
        
        $result = $this->cache->get($key);
        if ($result === false) {
            $result = $invocation->proceed();
            $this->cache->set($key, $result);
        }

        return $result;
    }
}
```

This aspect then will be registered in the AOP kernel. AOP engine will analyze each loaded class during autoloading and if a method matches the `@Around("@annotation(Annotation\Cacheable)")` pointcut then AOP will change it on the fly to include a custom logic of invoking an advice. Class name will be preserved, so AOP can easily cache static methods and even methods in final classes.

AOP allows us to extract caching logic into a single method (called 'advice'), it works like a decorator, so we don't slow down methods that are not cached (compared with proxy pattern), moreover, it doesn't repeat the code several times (DRY) and it's an awesome result.

Many developers have doubts about AOP, annotations and pointcut matching ) It's a typical question, so I want to make some clarifications. First of all, pointcut matching is performed only once, there won't be any extra checks during a normal execution of an application. Modified classes are stored in the cache and are friendly for opcode cachers, this means that the performance will be good. Annotations are parsed only once during pointcut matching and are also cached (in case you want to read some values from an annotation inside an advice). Bootstrap time for framework is about 20ms, this should be fast enough for your typical applications. 
 
Assuming that we use AOP for caching of methods which can take several hundreds ms or even up to several seconds to complete, AOP overhead is minimal (20ms bootstrap and several ms for calling an advice). This approach gives a new instrument for developers, it can solve annoying cross-cutting concerns, like caching with simple aspect. Use it! Enjoy It!
 
 
PS. There is a demo site on Heroku with caching example: http://demo.aopphp.com/?showcase=cacheable You can try it with enabled/disabled AOP (blue button at top). 
 
PSS. If you're looking for a way to use AOP caching with Laravel, visit an article (Spain): http://blog.carlosgoce.com/realizando-cache-con-aop-en-laravel-4/