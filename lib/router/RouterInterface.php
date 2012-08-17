<?php
namespace lib\router;

use
\lib\router\RouteDefinitionInterface,
\lib\Request;

/**
 * Router interface
 * @package Moss Core
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
interface RouterInterface {

	/**
	 * Registers route definition into routing table
	 *
	 * @param RouteDefinitionInterface $RouteDefinition
	 *
	 * @return RouterInterface
	 */
	public function register(RouteDefinitionInterface $RouteDefinition);

	/**
	 * Matches request to route
	 * Throws RangeException if no matching route found
	 *
	 * @param Request $Request
	 *
	 * @return RouterInterface
	 * @throws RouteException
	 */
	public function match(Request $Request);

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
	public function make($identifier = null, $arguments = array(), $normal = false, $direct = false);

	/**
	 * Resets routing table
	 * @return RouterInterface
	 */
	public function reset();
}