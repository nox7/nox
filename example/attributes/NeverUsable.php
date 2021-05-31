<?php
	/**
	 * This is an example of a custom attribute that will
	 * make sure whatever route you put it on _never_ is used.
	 */

	#[Attribute(Attribute::TARGET_METHOD)]
	class NeverUsable implements \Nox\Router\Interfaces\RouteAttribute{

		public function getAttributeResponse(): \Nox\Router\AttributeResponse
		{
			return new \Nox\Router\AttributeResponse(
				false,
				null,
				null
			);
		}
	}