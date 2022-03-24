<?php
	namespace Nox\Http\Attributes;

	/**
	 * An extendable classes that lets the router know this attribute should only be instantiated
	 * if a Route has been chosen as the correct route to serve a request - after all other checks have passed.
	 * @since 1.5.0
	 */
	#[\Attribute(\Attribute::TARGET_METHOD)]
	class ChosenRouteAttribute{

	}