# Router

Independent routing component from MOSS Framework

For licence details see Licence.md

For ``Request`` description see REQUEST.md

## Registering routes

Each route is represented by ``RouteDefinition`` instance

	$Route = new \lib\router\RouteDefinition($domain, $pattern, $controller, $arguments, $forceDirs);

	$Router = new \lib\router\Router();
	$Router->register( $Route );

Where:

* ``$domain`` defines domain or subdomain for pattern
* ``$pattern`` is a regular expression, can include route variables eg. ``foo/{bar:\d}`` where variable ``bar`` can contain only digits
* ``$controller`` controller/action identifier
* ``$arguments`` by default its an empty array, when route should contain additional parameters, they should be passed as key-value pairs in this array
* ``$forceDirs`` if true, subdomains will be converted into subdirectories in pattern (useful when you are to lazy to use virtual hosts in development)

Additionaly ``RouteDefinitions`` can be limited to concrete schema (protocol) and/or method via ``RouteDefinition::setSchema()`` and ``RouteDefinition::setMethod()``

## Resolving route

After route registration, router is ready to resolve incoming requests whitch are represented by ``Request`` object.

	$Request = new \lib\Request();
	$Router->match($Request);

If router finds matching route - updates ``Request`` object so it contains parameters defined in matching route definition.
If unable to match ``Request`` to route, ``RouteException`` will be thrown.

All data from incoming request and those received from route definition are available in Request object

## Generating link

When generating links, router finds first matching route and uses defined pattern to create url. If no routes matches passed parameters, normal url is generated.

	$url = $Request->make($controller, $arguments, $normal, $direct);

Where

* ``$controller`` is same controller identifier whitch was passed to corresponding route definition
* ``$arguments`` array containing required and additional arguments for route, additional arguments (those not included in pattern) will be added to generated link as query string
* ``$normal`` if true, forces _normal_ link
* ``$direct`` if true, forces direct link (by default direct links are generated, this can be canged in router class),