<?php

namespace Shirazsoft\Gateway;

use Illuminate\Support\Facades\Facade;

/**
 * @see \Shirazsoft\Gateway\GatewayResolver
 */
class Gateway extends Facade
{
	/**
	 * The name of the binding in the IoC container.
	 *
	 * @return string
	 */
	protected static function getFacadeAccessor()
	{
		return 'gateway';
	}
}
