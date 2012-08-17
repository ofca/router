<?php

require 'lib/router/RouteException.php';
require 'lib/router/Request.php';
require 'lib/router/RouteDefinition.php';
require 'lib/router/Router.php';

$Router = new \lib\router\Router();

for($i = 0; $i < 100; $i++) {
	for($j = 0; $j < 100; $j++) {
		$Router->register(new \lib\router\RouteDefinition(
			'subdomain'.$i,
			'/link'.$j.'/',
			'pl:app:front:MainController:action'.$j
		));
	}
}

$Router->register(new \lib\router\RouteDefinition(
	null,
	'/{word:\w}.html',
	'pl:app:front:MainController:test'
));

$Request = new \lib\Request();
$Request->domain = 'localhost';
$Request->url = '/test.html';

$Router->match($Request);

var_dump($Request);