<?php

	namespace Nox\ORM;

	class ResultOrder{

		public array $orderClauses = [];

		public function __construct(){

		}

		/**
		 * Adds a column order clause. Returns itself for chaining.
		 */
		public function by(string $columnName, string $order): ResultOrder{
			$this->orderClauses[] = sprintf("`%s` %s", $columnName, $order);
			return $this;
		}

	}