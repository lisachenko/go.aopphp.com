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
