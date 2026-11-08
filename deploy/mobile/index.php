<?php

use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));
require '/srv/rsu/vendor/autoload.php';
$request = Request::capture();
if (! $request->is('api/mobile/*', 'up')) {
    http_response_code(404);
    exit;
}
$request->headers->set('Accept', 'application/json');
(require '/srv/rsu/bootstrap/app.php')->handleRequest($request);

