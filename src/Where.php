<?php
namespace Jahan\Database;

/**
 * This is a helper class to create where clause for MySql queries.
 * Usage:
 * echo new \Jahan\Database\Where('id','=',10); //id = 10
 * echo \Jahan\Database\Where::build('deleted_at', 'IS NOT NULL'); //deleted_at IS NOT NULL
 */
class Where
{
	public $field;
	public $operator;
	public $value;

	public function __construct($field, $operator, $value = '')
	{
		$this->field = $field;
		$this->operator = $operator;
		$this->value = $value;
	}

	public static function build($field, $operator, $value = '') :string
	{
		$obj = new Where($field, $operator, $value);
		return (string) $obj;
	}
	
	public function getClause() :string
	{
		$operator = strtoupper($this->operator);
		$result = (string) $this->field . ' ' . $operator;

		if(is_string($this->value)) {
			if(!empty($this->value) && $this->value[0] != ':') {
				$this->value = '"' . $this->value . '"';
			}
		}

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