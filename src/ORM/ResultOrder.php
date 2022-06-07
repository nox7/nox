<?php

	namespace Nox\ORM;

	class ResultOrder{

		public array $orderClauses = [];

		/**
		 * Inputs a result order but using function syntax
		 * E.g. "RAND()"
		 * @param string $functionCallString
		 * @return $this
		 */
		public function byFunction(string $functionCallString): self{
			$this->orderClauses[] = $functionCallString;
			return $this;
		}

		/**
		 * Adds a column order clause. Returns itself for chaining.
		 */
		public function by(string $columnName, string $order): self{
			$this->orderClauses[] = sprintf("`%s` %s", $columnName, $order);
			return $this;
		}

	}