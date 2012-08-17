<?php
namespace lib\router;

use
\lib\router\RouteDefinitionInterface,
\lib\Request;

/**
 * Route definition, represents route for Router
 *
 * @package Moss Core
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
class RouteDefinition implements RouteDefinitionInterface {
	protected $identifier;
	protected $arguments;

	protected $domain;
	protected $pattern;
	protected $schema;
	protected $method;
	protected $cacheable = false;

	protected $required;
	protected $rebuild;

	protected $regexpDomain;
	protected $regexpUrl;

	private $forceDirs = false;

	/**
	 * Creates route definition instance
	 *
	 * @param null|string $domain     domain filter, if null fits all domains
	 * @param string      $pattern    route pattern, can include arguments e.g. /foo/{var:\d}/ where variable var is numeric
	 * @param string      $identifier controller identifier
	 * @param array       $arguments  route arguments
	 * @param bool        $forceDirs  forces conversion from subdomain into subdirectory
	 */
	public function __construct($domain, $pattern, $identifier, $arguments = array(), $forceDirs = false) {
		$this->domain = $domain;
		$this->pattern = $pattern;
		$this->identifier = $identifier;
		$this->arguments = (array) $arguments;
		$this->required = array();
		$this->forceDirs = (bool) $forceDirs;

		$this->redefineHost();
		$this->buildPatterns();
	}

	/**
	 * Sets route protocol schema
	 *
	 * @param string $schema
	 *
	 * @return RouteDefinition
	 */
	public function setSchema($schema) {
		$this->schema = $schema;
		return $this;
	}

	/**
	 * Sets route method
	 *
	 * @param string $method
	 *
	 * @return RouteDefinition
	 */
	public function setMethod($method) {
		$this->method = $method;
		return $this;
	}

	/**
	 * Toggles route cacheable
	 *
	 * @param bool $cacheable
	 *
	 * @return RouteDefinition
	 */
	public function setCacheble($cacheable) {
		$this->cacheable = (bool) $cacheable;
		return $this;
	}

	/**
	 * If force dirs is true translates subdomains into subdirectories
	 *
	 * @return void
	 */
	protected function redefineHost() {
		if(!$this->domain || !$this->forceDirs) {
			return;
		}

		$this->pattern = '/' . $this->domain . $this->pattern;
		$this->domain = null;
	}

	/**
	 * Builds route pattern and route regular expression
	 */
	protected function buildPatterns() {
		preg_match_all('#{([a-z]+):([^}]+)?}#i', $this->pattern, $match);

		$match[3] = array();
		foreach($match[0] as $key => $trash) {
			if(!isset($this->arguments[$match[1][$key]])) {
				$this->arguments[$match[1][$key]] = null;
			}

			$this->required[$match[1][$key]] = '*';

			$match[3][$key] = '#' . $match[1][$key] . '#';
			$match[2][$key] = '(?P<' . $match[1][$key] . '>[' . $match[2][$key] . ']+)';
			$match[1][$key] = ':' . $match[1][$key];
		}

		if(empty($this->required)) {
			$this->required = $this->arguments;
		}

		$this->regexpUrl = $this->pattern;
		$this->regexpUrl = str_replace($match[0], $match[3], $this->regexpUrl);
		$this->regexpUrl = preg_quote($this->regexpUrl, '/');
		$this->regexpUrl = str_replace($match[3], $match[2], $this->regexpUrl);

		if(!empty($this->regexpUrl)) {
			$this->regexpUrl .= '?';
		}

		$this->regexpUrl = '/^' . $this->regexpUrl . '$/i';
		$this->regexpDomain = '/^' . (!empty($this->domain) ? preg_replace('/^(https?|ftp):\/\/([^\/]+)\/?/i', '$2', $this->domain) : null) . '.*$/';

		$this->pattern = str_replace($match[0], $match[1], $this->pattern);
	}

	/**
	 * Returns definition identifier
	 *
	 * @return string
	 */
	public function identify() {
		return $this->identifier;
	}

	/**
	 * Returns definition domain
	 *
	 * @return null|string
	 */
	public function getDomain() {
		return $this->domain;
	}

	/**
	 * Returns true if route is cacheable
	 *
	 * @return bool
	 */
	public function isCacheable() {
		return $this->cacheable;
	}

	/**
	 * Checks if Request matches route definition
	 * If so - parses it
	 *
	 * @param Request $Request
	 *
	 * @return bool
	 */
	public function matchRequest(Request $Request) {
		if(!empty($this->schema) && strpos($Request->schema, $this->schema) === false) {
			return false;
		}

		if(!empty($this->method) && $this->method != $Request->method) {
			return false;
		}

		if(!preg_match($this->regexpDomain, $Request->domain) || !preg_match($this->regexpUrl, $Request->url)) {
			return false;
		}

		$Request->identifier = $this->identifier;
		$Request->cacheable = $this->cacheable;

		preg_match_all($this->regexpUrl, $Request->url, $match, PREG_SET_ORDER);
		if(isset($match[0]) && !empty($match[0])) {
			foreach(array_keys($this->arguments) as $argument) {
				if(isset($match[0][$argument])) {
					$this->arguments[$argument] = $match[0][$argument];
				}
			}
		}

		$Request->query = array_merge($this->arguments, (array) $Request->query);

		return true;
	}

	/**
	 * Checks if controller identifier and arguments match route definition
	 *
	 * @param string $identifier controller identifier
	 * @param array  $arguments  route arguments
	 *
	 * @return bool
	 */
	public function matchIdentifier($identifier, $arguments = array()) {
		if($this->identifier !== $identifier) {
			return false;
		}

		$arg = array();
		$req = array();
		foreach($this->required as $node => $value) {
			$req[$node] = $value == '*' && array_key_exists($node, $arguments) ? $arguments[$node] : $value;
			$arg[$node] = array_key_exists($node, $arguments) ? $arguments[$node] : null;
		}

		if(!empty($this->required) && $arg == $req) {
			return true;
		}

		return false;
	}

	/**
	 * Creates friendly link based on route definition
	 *
	 * @param string $baseName  basename used for direct links
	 * @param array  $arguments link arguments
	 * @param bool   $direct    it true, will create direct link
	 *
	 * @return string
	 */
	public function make($baseName, $arguments, $direct) {
		$arguments = array_merge($this->arguments, $arguments);

		$kArr = array();
		$vArr = array();
		$qArr = array();

		foreach($arguments as $argName => $argValue) {
			if(strpos($this->pattern, ':' . $argName) !== false) {
				$kArr[] = ':' . $argName;
				$vArr[] = ':' . $argName == $argValue ? null : $this->strip($argValue);
			}
			elseif(!empty($argValue) && (!isset($this->arguments[$argName]) || $this->arguments[$argName] != $argValue)) {
				$qArr[$argName] = $this->strip($argValue);
			}
		}

		$url = str_replace($kArr, $vArr, $this->pattern);
		$url = str_replace('//', '/', $url);

		if(!empty($qArr)) {
			$url .= '?' . http_build_query($qArr, null, '&');
		}

		if($direct || $this->domain) {
			$url = rtrim($this->rebuildDomain($baseName), '/') . '/' . ltrim($url, './');
		}
		else {
			$url = (strpos($url, '?') === 0 ? null : '.') . $url;
		}

		return $url;
	}

	/**
	 * Rebuilds domain in urls for external domains and subdomains
	 *
	 * @param string $baseName basename used for direct links
	 *
	 * @return string
	 */
	protected function rebuildDomain($baseName) {
		if($this->schema) {
			$baseName = preg_replace('/^(https?|ftp)\:\/\/(.*)$/i', strtolower($this->schema).'://$2', $baseName);
		}

		if($this->rebuild) {
			return $this->rebuild;
		}

		if(!$this->domain) {
			$this->rebuild = $baseName;
		}
		elseif(preg_match('/^(https?|ftp):\/\/.*$/i', $this->domain)) {
			$this->rebuild = $this->domain;
		}
		else {
			$pos = strpos($baseName, '//');
			$this->rebuild = substr($baseName, 0, $pos + 2) . $this->domain . '.' . substr($baseName, $pos + 2);
		}

		return $this->rebuild;
	}

	/**
	 * Strips string from non ASCII chars
	 *
	 * @param string $urlString string to strip
	 * @param string $separator char replacing non ASCII chars
	 *
	 * @return string
	 */
	protected function strip($urlString, $separator = '-') {
		$urlString = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $urlString);
		$urlString = strtolower($urlString);
		$urlString = preg_replace('#[^\w \-]+#i', null, $urlString);
		$urlString = preg_replace('/[ -]+/', $separator, $urlString);
		$urlString = trim($urlString, '-.');

		return $urlString;
	}
}