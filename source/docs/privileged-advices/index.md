---
layout: page
title: "Privileged advices"
date: 2013-06-22 11:26
keywords: privileged, advice, access private members, access protected members
description: |
    Privileged advice is a unique type of advices in Go! Aspect-Oriented framework. Privileged advice is
    working in the scope of target class, not in the scope of aspect, so there is a transparent access to the private and
    protected members of the target class.

comments: true
sharing: true
footer: true
---

For the most part, aspects and advices have the same standard PHP access-control rules as
classes. For example, an advice normally cannot access any private members of
other classes. This is usually sufficient and, in fact, desirable on most occasions.
However, in a few situations, an advice of aspect may need to access certain data members
or operations that are not exposed to outsiders. You can gain such access by
running the advice in the "privileged" scope.

Let’s see how this works in the following example. The TestPrivileged class
contains two private data members.

{% include_code lang:php privileged-advices/TestPrivileged.php %}

Consider a situation where PrivilegeTestAspect needs to access
the class’s private data member to perform its logic.

{% include_code lang:php privileged-advices/PrivilegeTestAspect.php %}

If we tried to execute this code, we would get a notice or a fatal for accessing the
TestPrivileged class's member $id:

{% codeblock %}
Notice: Undefined property: TestPrivileged::$id in ..\PrivilegeTestAspect.php on line 20
{% endcodeblock %}

If the property is declared as protected, then we will receive a fatal error:
{% codeblock %}
Fatal error: Cannot access protected property TestPrivileged::$id in ..\PrivilegeTestAspect.php on line 20
{% endcodeblock %}

If, however, we mark the advice as privileged by specifying the target scope (look at the annotation), the code executes
without error and behaves as expected:

```php
<?php

use Go\Aop\Aspect;
use Go\Aop\Intercept\MethodInvocation;
use Go\Lang\Annotation\Before;

class PrivilegeTestAspect implements Aspect
{
    /**
     * @Before("execution(public TestPrivileged->method1(*))", scope="target")
     * @param MethodInvocation $invocation
     */
    public function beforeTestMethod1(MethodInvocation $invocation)
    {
        /** @var TestPrivileged $callee|$this */
        $callee = $invocation->getThis();
        echo 'PrivilegeTestAspect:before objectId=', $callee->id;
    }
}
```
{% codeblock %}
PrivilegeTestAspect:before objectId=0
TestPrivileged.method1
{% endcodeblock %}

Now with the privileged advice, we could access the internal state of a class without changing the class.

There is one interesting fact about privileged advices. In the privileged advice body $this is bound to the target object
instance. Late Static Binding is also bound to the called class of target joinpoint:

```php
<?php

class PrivilegeTestAspect implements Aspect
{
    /**
     * @Before("execution(public TestPrivileged->method1(*))", scope="target")
     * @param MethodInvocation $invocation
     */
    public function beforeTestMethod1(MethodInvocation $invocation)
    {
        /** @var TestPrivileged $this */
        echo get_class($this); // TestPrivileged
        echo get_called_class(); // TestPrivileged

        echo static::$lastId;
    }
}
```


WARNING! Privileged advices have access to implementation details. Therefore, exercise restraint while using this feature.
If the classes change their implementation—which they are legitimately entitled to do—the aspect
accessing such implementation details will need to be changed as well.

Using the privileged advices feature will help in handling situations where you
need to access the private or protected members of classes. In this case, though, it is perhaps
more important to understand the negative implications of using this technique.

This article is based on the Chapter4, p4.5 of "Advanced AspectJ" book and adapted for PHP.