<?php

namespace TeraBlaze\Libraries;

use TeraBlaze\Registry;
use TeraBlaze\RequestMethods;

/**
 * Class Http
 * @package TeraBlaze\Libraries
 *
 * Extends the core Http class
 * for easy loading in controllers and models
 */
class Http extends \TeraBlaze\Http\Http
{
	public function initialize($http_conf = "default")
	{
		$configuration = $this->container->get('configuration');
		
		if ($configuration) {
			$configuration = $configuration->initialize();
			$parsed = $configuration->parse("config/http");
			
			if (!empty($parsed->{$http_conf})) {
				$this->agent = empty($parsed->{$http_conf}->agent) ?
				RequestMethods::server("HTTP_USER_AGENT", "Curl/PHP " . PHP_VERSION) :
				$parsed->{$http_conf}->agent;
			}
		}
		
		return $this;
		
	}
}