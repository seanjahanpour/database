<?php
namespace Jahan\Database;

use Jahan\Filter\Str as Filter;

class DataAccessBase
{
	public const TABLE = '';
	public const PK = 'id';

	public const LAZY_LOAD_FIELDS = [];
	public const INDEX_FIELDS = [];
	public const UPDATABLES = [];
	public const INSERTABLES = [];
	public const FIELDS = [];
	public const FIELD_PROPERTIES = [];
	public array $lazy_load_changed = [];

	protected bool $loaded_from_db = false;

	protected Core $db;

	public function __construct(Core $db)
	{
		$this->db = $db;
	}

	public function __set($property, $value)
	{
		if( in_array($property, static::LAZY_LOAD_FIELDS)) {
			$this->$property = $value;
			$this->lazy_load_changed[] = $property;
		}
	}

	public function __get($property)
	{
		$pk = static::PK;

		if( isset($this->$pk)  &&   (!isset($this->$property))   &&  in_array($property, static::LAZY_LOAD_FIELDS) ) {
			//a lazyload property is being accessed that hasn't been pulled from db.
			$this->load_field($property);
			return $this->$property;
		}
	}

	public function load_field(string $field_name)
	{
		$field_name = Filter::alpha_numeric($field_name, ['_']);
		$table = static::TABLE;
		$pk_field_name = static::PK;
		$pk = $this->$pk_field_name;
		$query = "SELECT $field_name AS value FROM $table WHERE $pk_field_name = :pk LIMIT 1";
		$this->$field_name = $this->db->get_value($query, ['pk'=>$pk]);
		return $this;
	}


	public static function load($id, Core $db)
	{
		//get list of fields in this table
		$fields = static::FIELDS;

		//remove all lazy loading fields
		foreach($fields as $key=>$field) {
			if(in_array($field, static::LAZY_LOAD_FIELDS)) {
				unset($fields[$key]);
			}
		}

		$table = static::TABLE;
		$pk_field_name = static::PK;

		$instance = $db->set_class(static::class, [$db])->get_record($table, $pk_field_name, $id, $fields);
		foreach(static::LAZY_LOAD_FIELDS as $field) {
			unset($instance->$field);
		}

		$instance->lazy_load_changed = [];

		$instance->loaded_from_db = true;
		
		return $instance;
	}

	public function __debugInfo()
	{
		unset($this->db);
		return get_object_vars($this);
	}
}