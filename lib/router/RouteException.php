<?php
namespace lib\router;

/**
 * Route exception
 * @package Moss Exceptions
 * @author  Michal Wachowski <wachowski.michal@gmail.com>
 */
class RouteException extends \RuntimeException {

	/**
	 * @param string          $message
	 * @param int             $code
	 * @param \Exception|null $previous
	 */
	public function __construct($message = "Route not found", $code = 0, \Exception $previous = NULL) {
		parent::__construct($message, $code, $previous);
	}
}
