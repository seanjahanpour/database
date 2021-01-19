<?php
namespace Jahan\Database;

use Jahan\Filter\Str as StrFilter;

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

	protected bool $_loaded_from_db_ = false;

	protected Core $_db_;

	public function __construct(Core $db)
	{
		$this->_db_ = $db;
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
		$field_name = StrFilter::alpha_numeric($field_name, ['_']);
		$table = static::TABLE;
		$pk_field_name = static::PK;
		$pk = $this->$pk_field_name;

		$pk_field_name = (empty(static::FIELD_PROPERTIES[$pk_field_name]['name'])) ? $pk_field_name : static::FIELD_PROPERTIES[$pk_field_name]['name'];
		$field_name = (empty(static::FIELD_PROPERTIES[$field_name]['name'])) ? $field_name : static::FIELD_PROPERTIES[$field_name]['name'];

		$query = "SELECT `$field_name` AS value FROM `$table` WHERE `$pk_field_name` = :pk LIMIT 1";
		$this->$field_name = $this->_db_->get_value($query, ['pk'=>$pk]);
		return $this;
	}

	public static function create($id, Core $db)
	{
		$fields = static::get_all_fields_except_lazy_load();

		$table = static::TABLE;
		$pk_field_name = static::PK;
		$pk_field_name = (empty(static::FIELD_PROPERTIES[$pk_field_name]['name'])) ? $pk_field_name : static::FIELD_PROPERTIES[$pk_field_name]['name'];

		$instance = $db->set_class(static::class, [$db])->get_row($table, $fields, "`$pk_field_name` = :pk", ['pk'=>$id]);

		//clean up lazy loads, incase we are loading to override previous pull from database.
		foreach(static::LAZY_LOAD_FIELDS as $field) {
			unset($instance->$field);
		}
		
		$instance->lazy_load_changed = [];
		$instance->_loaded_from_db_ = true;
		
		return $instance;
	}

	public function load()
	{
		$fields = static::get_all_fields_except_lazy_load();

		$table = static::TABLE;
		$pk_field_name = static::PK;
		$id = $this->$pk_field_name;
		$pk_field_name = (empty(static::FIELD_PROPERTIES[$pk_field_name]['name'])) ? $pk_field_name : static::FIELD_PROPERTIES[$pk_field_name]['name'];

		$this->_db_->set_object($this)->get_row($table, $fields, "`$pk_field_name` = :pk", ['pk'=>$id]);

		//clean up lazy loads, incase we are loading to override previous pull from database.
		foreach(static::LAZY_LOAD_FIELDS as $field) {
			unset($this->$field);
		}
		
		$this->lazy_load_changed = [];
		$this->_loaded_from_db_ = true;
		
		return $this;
	}

	public function save()
	{
		return $this->save_fields(static::FIELDS);
	}

	public function update()
	{
		return $this->save_fields(static::UPDATABLES);
	}

	public function insert()
	{
		return $this->save_fields(static::INSERTABLES);
	}

	public function __debugInfo()
	{
		unset($this->_db_);
		return get_object_vars($this);
	}

	protected static function get_all_fields_except_lazy_load()
	{
		$fields = static::FIELDS;

		//remove all lazy loading fields
		foreach($fields as $key=>$field) {
			if(in_array($field, static::LAZY_LOAD_FIELDS)) {
				unset($fields[$key]);
				continue;
			}

			//get raw field name from FIELD_PROPERTIES if defined
			$fields[$key] = (empty(static::FIELD_PROPERTIES[$field]['name'])) ? $field : static::FIELD_PROPERTIES[$field]['name'];
		}

		return $fields;
	}
	
	protected function save_fields(array $fields)
	{
		$field_values = [];

		foreach($fields as $field) {
			if(  in_array($field, static::LAZY_LOAD_FIELDS) &&  !in_array($field, $this->lazy_load_changed)  ) {
				//skip all lazy load fields that haven't loaded or changed.
				continue;
			}

			if(  is_object($this->$field)  ) {
				$field_values[$field] = (string) $this->$field;
			} else {
				$field_values[$field] = $this->$field;
			}
		}

		$pk = static::PK;
		$id = $this->$pk;
		$pk = (empty(static::FIELD_PROPERTIES[$pk]['name'])) ? $pk : static::FIELD_PROPERTIES[$pk]['name'];

		$field_values_with_raw_columnname = [];
		foreach($field_values as $key=>$value) {
			$key = (empty(static::FIELD_PROPERTIES[$key]['name'])) ? $key : static::FIELD_PROPERTIES[$key]['name'];
			$field_values_with_raw_columnname[$key] = $value;
		}

		if($this->_loaded_from_db_) {
			return $this->_db_->update(static::TABLE, $field_values_with_raw_columnname, "`$pk` = :pk", ['pk'=>$id]);
		} else {
			return $this->_db_->insert(static::TABLE, $field_values_with_raw_columnname);
		}
	}

	protected function get_fields_for_saving()
	{
		return static::FIELDS;
	}

	protected function get_fields_for_update()
	{
		return static::UPDATABLES;
	}

	protected function get_fields_for_insert()
	{
		return static::INSERTABLES;
	}
}