<?php
declare(strict_types=1);

use App\Application\Actions\User\ListUsersAction;
use App\Application\Actions\User\ViewUserAction;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\App;
use Slim\Interfaces\RouteCollectorProxyInterface as Group;

use App\Controllers\ChatworkController;

return function (App $app) {
    $container = $app->getContainer();
/*
    $app->any('/', function (Request $request, Response $response) {
        $app->logger->info("Slim-Skeleton '/' route");
        $response->getBody()->write('Hello world!');
        return $response;
    });
*/
    $app->any('/', ChatworkController::class . ':index');
};
