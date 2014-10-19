<?php
class TestPrivileged
{
    private static $lastId = 0;
    private $id;

    public function __construct()
    {
        $this->id = self::$lastId++;
    }

    public function method1()
    {
        echo "TestPrivileged.method1";
    }
}


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