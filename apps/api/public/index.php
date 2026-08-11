<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\Kernel;
use Symfony\Component\HttpFoundation\Request;

require dirname(__DIR__) . '/vendor/autoload.php';

$rootDir = dirname(__DIR__);
$config = Config::fromEnv($rootDir);
$routeDefinitions = require $rootDir . '/config/routes.php';

$kernel = new Kernel($config, $routeDefinitions);
$request = Request::createFromGlobals();

$response = $kernel->handle($request);
$response->send();
