<?php

	namespace Nox\ORM;

	class ColumnQuery{

		public array $whereClauses = [];

		public function __construct(){}

		/**
		 * Starts a parenthesis condition group in the query
		 */
		public function startConditionGroup(): ColumnQuery{
			$this->whereClauses[] = [
				"clauseType"=>"conditionGroup",
				"conditionGroupPosition"=>"start",
			];
			return $this;
		}

		/**
		 * Ends a parenthesis condition group in the query
		 */
		public function endConditionGroup(): ColumnQuery{
			$this->whereClauses[] = [
				"clauseType"=>"conditionGroup",
				"conditionGroupPosition"=>"end",
			];
			return $this;
		}

		/**
		 * Adds an AND join
		 */
		public function and(): ColumnQuery{
			$this->whereClauses[] = [
				"clauseType"=>"joinCondition",
				"clauseJoinWord"=>"AND",
			];
			return $this;
		}

		/**
		 * Adds an OR join
		 */
		public function or(): ColumnQuery{
			$this->whereClauses[] = [
				"clauseType"=>"joinCondition",
				"clauseJoinWord"=>"OR",
			];
			return $this;
		}

		/**
		 * Adds a column query and returns self for chaining
		 */
		public function where(string $columnName, string $condition, mixed $value, bool $raw = false): ColumnQuery{
			$this->whereClauses[] = [
				"clauseType"=>"where",
				"column"=>$columnName,
				"condition"=>$condition,
				"value"=>$value,
				"raw"=>$raw,
			];
			return $this;
		}

	}