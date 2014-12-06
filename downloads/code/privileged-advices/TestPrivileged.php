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
