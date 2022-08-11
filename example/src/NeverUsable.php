<?php
	/**
	 * This is an example of a custom attribute that will
	 * make sure whatever route you put it on _never_ is used.
	 */

	use Nox\Router\AttributeResponse;
	use Nox\Router\Interfaces\RouteAttribute;

	#[Attribute(Attribute::TARGET_METHOD)]
	class NeverUsable implements RouteAttribute{

		public function getAttributeResponse(): AttributeResponse
		{
			// By not providing responseCode or newRequestPath
			// the router will simply skip over whatever route this attribute
			// is added to. If one of the other two arguments are provided
			// then the router will stop on this route and either rewrite
			// the request, send the HTTP response code, or both.
			return new AttributeResponse(
				isRouteUsable:false,
				responseCode:null,
				newRequestPath:null,
			);
		}
	}