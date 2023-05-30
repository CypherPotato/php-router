<?php

include 'router.php';

class myController
{
    static function test()
    {
        echo "hello from controller!";
    }
}

set_route('GET', '/', function () {
    echo 'Hello, world! <a href="/test1">Go to another page</a>';
});

set_route('GET', '/test1', [myController::class, 'test']);

set_route('GET', '/parameter-route/<test>', function ($params) {
    echo 'Visited by: ' . $params['test'];
});

router_execute();
