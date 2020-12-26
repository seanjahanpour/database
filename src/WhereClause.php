<?php
namespace Jahan\Database;

use InvalidArgumentException;

class WhereClause
{
	public $field;
	public $operator;
	public $value;

	public function __construct($field, $operator, $value)
	{
		$this->field = $field;
		$this->operator = $operator;
		$this->value = $value;
	}

	public function getClause()
	{
		$operator = strtoupper($this->operator);
		$result = (string) $this->field . ' ' . $operator;

		switch($operator) {
			case '>':
			case '<':
			case '=':
			case '>=':
			case '<=':
			case '!=':
			case '<>':
			case '<=>':
			case 'AND':
			case '&&':
			case 'IS':
			case 'IS NOT':
			case 'NOT':
			case '!':
				$result .= ' ' . (string) $this->value;
				break;
			case 'OR':
			case '||':
				$result = '((' . (string) $this->field . ') ' . $operator . ' (' . (string) $this->value . '))';
				break;
			case 'LIKE':
			case 'NOT LIKE':
			case 'NOT REGEXP':
			case 'REGEXP':
			case 'RLIKE':
			case 'SOUND LIKE':
			case 'MEMBER OF':
				$result .= " '" . (string) $this->value . "'";
				break;						
			
			case 'IS NOT NULL':
			case 'IS NULL':
				break;//no value used
				
			case 'IN':
			case 'NOT IN':
				$result .= ' (' . (string) $this->value . ')';
				break;

			default:
				throw new \InvalidArgumentException("Unknown where clause operator '$operator'");
				break;
		}
		
		return $result;
	}

	public function __toString()
	{
		return $this->getClause();
	}
}