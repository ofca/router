<?php
namespace lib\router;

use \lib\Request;

/**
 * Route definition interface for Router
 * @package Moss Core
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
interface RouteDefinitionInterface {

	/**
	 * Returns definition identifier
	 *
	 * @abstract
	 * @return string
	 */
	public function identify();

	/**
	 * Returns definition domain
	 *
	 * @abstract
	 * @return null|string
	 */
	public function getDomain();

	/**
	 * Checks if Request matches route definition
	 * If so - parses it
	 *
	 * @param Request $Request
	 *
	 * @return bool
	 */
	public function matchRequest(Request $Request);

	/**
	 * Checks if controller identifier and arguments match route definition
	 *
	 * @param string $identifier controller identifier
	 * @param array  $arguments  route arguments
	 *
	 * @return bool
	 */
	public function matchIdentifier($identifier, $arguments = array());

	/**
	 * Creates friendly link based on route definition
	 *
	 * @param string $baseName  basename used for direct links
	 * @param array  $arguments link arguments
	 * @param bool   $direct    it true, will create direct link
	 *
	 * @return string
	 */
	public function make($baseName, $arguments, $direct);
}