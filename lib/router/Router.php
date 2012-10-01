<?php
namespace lib\router;

use
\lib\router\RouterInterface,
\lib\router\RouteDefinitionInterface,
\lib\router\RouteException,
\lib\Request;

/**
 * Router
 * Responsible for matching Request to route and URI creation
 * 
 * @package Moss Core
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
class Router implements RouterInterface {

	protected $baseName;
	protected $identifier;

	protected $lang;
	protected $controller;
	protected $action;

	protected $normal = false;
	protected $direct = true;

	protected $namespaceController = 'controller';
	protected $namespaceSeparator = '\\';
	protected $moduleSeparator = ':';

	/** @var array|RouteDefinitionInterface[] */
	protected $definitions = array();

	/** @var array|RouteDefinitionInterface[] */
	protected $routesByIdentifier = array();

	/** @var array|RouteDefinitionInterface[] */
	protected $routesByDomain = array();


	/**
	 * Creates router instance
	 *
	 * @param bool $normal if true generates normal links by default
	 * @param bool $direct if true generates absolute links by default
	 */
	public function __construct($normal = false, $direct = true) {
		$this->normal = (bool) $normal;
		$this->direct = (bool) $direct;
	}


	/**
	 * Registers route definition into routing table
	 *
	 * @param \lib\router\RouteDefinitionInterface $RouteDefinition
	 *
	 * @return Router|RouterInterface
	 */
	public function register(RouteDefinitionInterface $RouteDefinition) {
		$hash = spl_object_hash($RouteDefinition);

		$this->definitions[$hash] = $RouteDefinition;

		if(!isset($this->routesByIdentifier[$RouteDefinition->identify()])) {
			$this->routesByIdentifier[$RouteDefinition->identify()] = array();
		}
		$this->routesByIdentifier[$RouteDefinition->identify()][] =& $this->definitions[$hash];

		if(!isset($this->routesByDomain[$RouteDefinition->getDomain()])) {
			$this->routesByDomain[$RouteDefinition->getDomain()] = array();
		}

		$this->routesByDomain[$RouteDefinition->getDomain()][] =& $this->definitions[$hash];

		return $this;
	}

	/**
	 * Matches request to route
	 * Throws RangeException if no matching route found
	 *
	 * @param Request $Request
	 *
	 * @return Router|RouterInterface
	 * @throws RouteException
	 */
	public function match(Request $Request) {
		$this->resolveDomain($Request);
		$this->retrieveRequest($Request);

		krsort($this->routesByDomain);

		if(!empty($Request->identifier)) {
			$Request->identifier = $this->resolveIdentifier($Request->identifier);

			$this->resolveComponent($Request, $Request->identifier);
			$this->retrieveRequest($Request);
			return $this;
		}

		$Route = null;
		foreach($this->routesByDomain as $block) {
			foreach($block as $Definition) {
				if($Definition->matchRequest($Request)) {
					$Route = $Definition;
					break;
				}
			}

			if($Route) {
				break;
			}
		}

		if(!$Route) {
			throw new RouteException('Route ' . $Request->url . ' not found!');
		}

		$this->resolveComponent($Request, $Route->identify());
		$this->retrieveRequest($Request);

		$Request->self = $this->make();

		foreach($Request->query as $key => $value) {
			$_GET[$key] = $value;
		}

		return $this;
	}

	/**
	 * Makes link
	 * If corresponding route exists - friendly link is generated, otherwise normal
	 *
	 * @param null|string $identifier controller identifier, if null request controller is used
	 * @param array       $arguments  additional arguments
	 * @param bool        $normal     if true forces normal link
	 * @param bool        $direct     if true forces direct link
	 *
	 * @return string
	 * @throws \RangeException
	 */
	public function make($identifier = null, $arguments = array(), $normal = false, $direct = false) {
		$identifier = $this->resolveIdentifier($identifier);

		$arguments = $arguments && is_array($arguments) ? $arguments : array();

		try {
			if($this->normal || $normal) {
				throw new \RangeException('Forced to generate the normal address');
			}

			$Definition = null;
			if(isset($this->routesByIdentifier[$identifier])) {
				foreach($this->routesByIdentifier[$identifier] as $Definition) {
					if($Definition->matchIdentifier($identifier, $arguments)) {
						break;
					}
				}
			}
			else {
				throw new \RangeException('Route not found, fall back to normal address');
			}

			$url = $Definition->make($this->baseName, $arguments, $this->direct || $direct);
		}
		catch(\RangeException $e) {
			$url = '?' . http_build_query(array_merge(array('controller' => str_replace(array($this->moduleSeparator, '/'), '_', $identifier)), $arguments), null, '&');
			if($this->direct || $direct) {
				$url = rtrim($this->baseName, '/') . '/' . ltrim($url, './');
			}
		}

		return $url;
	}

	/**
	 * Resets routing table
	 * @return Router|RouterInterface
	 */
	public function reset() {
		$this->routesByIdentifier = array();

		return $this;
	}

	/**
	 * Resolves identifier from passed data
	 *
	 * @param string $identifier
	 *
	 * @return string
	 */
	protected function resolveIdentifier($identifier) {
		$arr = $this->parseIdentifier($identifier, $this->lang, $this->controller, $this->action);
		return sprintf('%2$s%1$s%3$s%1$s%4$s', $this->moduleSeparator, $arr['lang'], $arr['controller'], $arr['action']);
	}

	/**
	 * Resolves base domain for all incoming requests
	 * Resolving is based on current request domain and registered path domains
	 *
	 * @param Request $Request
	 */
	protected function resolveDomain(Request $Request) {
		if(substr_count($Request->baseName, '.') <= 1) {
			return;
		}

		$pos = strpos($Request->baseName, '//') + 2;
		foreach(array_keys($this->routesByDomain) as $domain) {
			if(empty($domain)) {
				continue;
			}

			if(strpos($Request->baseName, $domain) === $pos) {
				$Request->baseName = substr($Request->baseName, 0, $pos) . substr($Request->baseName, $pos + strlen($domain) + 1);
			}
		}
	}

	/**
	 * Resolves component path from controller identifier
	 *
	 * @param Request $Request
	 * @param         $identifier
	 */
	protected function resolveComponent(Request $Request, $identifier) {
		$arr = $this->parseIdentifier($identifier);

		$Request->lang = $arr['lang'] ? $arr['lang'] : $Request->lang;

		$Request->controller = isset($arr['controller']) && $arr['controller'] ? $arr['controller'] : $Request->controller;
		$Request->controller = ltrim($Request->controller, $this->moduleSeparator);

		$Request->action = isset($arr['action']) && $arr['action'] ? $arr['action'] : $Request->action;

		if($modPos = strpos($Request->controller, $this->moduleSeparator)) {
			$module = substr($Request->controller, 0, $modPos);
			$Request->controller = $module . $this->namespaceSeparator . $this->namespaceController . $this->namespaceSeparator . substr($Request->controller, $modPos + strlen($this->moduleSeparator));
		}

		$Request->controller = str_replace(array($this->moduleSeparator, '/'), $this->namespaceSeparator, $Request->controller);
	}

	/**
	 * Retrieves request data for further routing
	 *
	 * @param Request $Request
	 */
	protected function retrieveRequest(Request $Request) {
		$this->baseName = $Request->baseName;
		$this->identifier = $Request->identifier;

		$arr = $this->parseIdentifier($Request->identifier);

		$this->lang = $arr['lang'];
		$this->controller = $arr['controller'];
		$this->action = $arr['action'];

	}

	/**
	 * Splits identifier into associative array
	 *
	 * @param string      $identifier controller identifier
	 * @param null|string $lang       language identifier if not found in passed identifier
	 * @param null|string $controller controller identifier if not found in passed identifier
	 * @param null|string $action     action identifier if not found in passed identifier
	 *
	 * @return array
	 * @throws \InvalidArgumentException
	 */
	protected function parseIdentifier($identifier, $lang = null, $controller = null, $action = null) {
		if(empty($identifier)) {
			return array(
				'lang' => $lang,
				'controller' => $controller,
				'action' => $action
			);
		}

		if(substr_count($identifier, $this->moduleSeparator) < 3) {
			throw new \InvalidArgumentException(sprintf('Invalid controller identifier - %s', $identifier));
		}

		$quotedSeparator = preg_quote($this->moduleSeparator);
		preg_match_all('/^((?P<lang>[a-z]{2})'.$quotedSeparator.')?(?P<controller>.+)'.$quotedSeparator.'(?P<action>[0-9a-z_]+)?$/i', $identifier, $matches, PREG_SET_ORDER);

		return array(
			'lang' => isset($matches[0]['lang']) && $matches[0]['lang'] ? $matches[0]['lang'] : $lang,
			'controller' => isset($matches[0]['controller']) && $matches[0]['controller'] ? $matches[0]['controller'] : $controller,
			'action' => isset($matches[0]['action']) && $matches[0]['action'] ? $matches[0]['action'] : $action,
		);
	}
}