<?php
namespace Jahan\Database;

use Jahan\Filter\Str as Filter;
use PDO;
use PDOStatement;
use InvalidArgumentException;

class Core
{
	protected $error_handler;
	protected bool $handler;
	protected array $write_creds;
	protected array $read_creds;
	protected const ERROR_CODE = 2;
	protected $fetch_class_or_object = '';
	protected array $fetch_class_params = [];
	protected ?PDO $read_connection = null;
	protected ?PDO $write_connection = null;

	/**
	 * @param array $read_creds 
	* example:
	* <code>
	* $read_creds =	[
	* 	'dsn' 	=> 'mysql:host=123.123.123.123;dbname=my_database;charset=utf8mb4',
	* 	'user'	=> 'user',
	* 	'password' => 'secret',
	* 	'options' => [ 
	* 			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
	* 			\PDO::ATTR_EMULATE_PREPARES => false,
	* 			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
	* 		]
	* 	];
	* </code>	
	* @param array $write_creds same as $read_creds. Same credentials can be used if the read and write database are the same.
	* 		passing empty array will disables write functions.
	* @param callable|null $error_handler
	*/
	public function __construct(array $read_creds, array $write_creds=[], ?callable $error_handler=null)
	{
		$this->error_handler = $error_handler;
		$this->handler = (empty($error_handler)) ? false : true;
		$this->write_creds = $write_creds;
		$this->read_creds = $read_creds;

		$options = [
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
		];

		if(empty($this->write_creds['options'])) {
			$this->write_creds['options'] = $options;
		}
		if(empty($this->read_creds['options'])) {
			$this->read_creds['options'] = $options;
		}
	}

	/**
	 * Get single record from database.
	 *
	 * @param string $table table name or expression
	 * @param string|array $fields List of fields to SELECT from database. It can be comma separated list of array of string.
	 * 				Example: 'id,first_name, last_name'
	 * 				Example: ['id', 'first_name', 'last_name']
	 * 				Example: '*'
	 * 				Empty string or array is same as '*'
	 * @param string $where where clause
	 * @param array $params array of values for where clause
	 * @param string $group_by grouping expression
	 * @param string $order_by sorting expression
	 * @param int $offset offset. No offset if no value passed.
	 * 
	 * @return array|object|null depending on PDO option set by __construct, result will return an object or an array. Null will be returned when no results where found.
	 */
	public function get_row(string $table, $fields, string $where='',array $params=[], string $group_by='', string $order_by='', int $offset=0)
	{
		$records = $this->get_rows($table, $fields, $where, $params, $group_by, $order_by, 1, $offset);

		if (!empty($records)) {
			return $records[0];
		} else {
			return null;
		}
	}

	/**
	 * Get multiple records from database
	 *
	 * @param string $table table name or expression
	 * @param array|string $fields List of fields to SELECT from database. It can be comma separated list of array of string.
	 * 				Example: 'id,first_name, last_name'
	 * 				Example: ['id', 'first_name', 'last_name']
	 * 				Example: '*'
	 * 				Empty string or array is same as '*'
	 * @param string $where where clause
	 * @param array $params array of values for where clause
	 * @param string $group_by grouping expression
	 * @param string $order_by sorting expression
	 * @param integer $limit maximum number of records
	 * @param integer $offset offset.
	 * @return array|object|null depending on PDO option set by __construct, result will return an object or an array. Null will be returned when no results where found.
	 */
	public function get_rows(string $table, $fields, string $where, $params=[], string $group_by='', string $order_by='', int $limit=0, int $offset=0)
	{
		$query = $this->build_select_query($table, $fields, $where, $group_by, $order_by, $limit, $offset);
		return $this->run_query($query, $params);
	}

	/**
	 * Get value of a single field from a single row in database
	 *
	 * @param string $table table name or expression
	 * @param array|string $field to get value of. This should be a single member array or string with single field name.
	 * @param string $where where clause
	 * @param array $params where clause parameters is any
	 * @param string $group_by grouping expression
	 * @param string $order_by sorting expression
	 * @param integer $offset offset.
	 * @return mix field value from database or blank string.
	 */
	public function get_field(string $table, $field, string $where='',array $params=[], string $group_by='', string $order_by='', int $offset=0)
	{
		$select_field = $this->build_select_field_list($field);
		$select_field .= ' AS value';
		$record = $this->get_row($table, $select_field, $where, $params, $group_by, $order_by, $offset);
		
		if(!empty($record)) {
			if(is_array($record)) {
				return $record['value'];
			} elseif(is_object($record)) {
				return $record->value;
			}
		}

		return '';
	}

	/**
	 * insert a record into database.
	 * 
	 * @param  string $table   table name or expression
	 * @param  array $data    associative array of keys and values
	 * @param  array $allowed array with list of all the fields that are allowed to be inserted. Empty array allows all fields passed in $data to be inserted.
	 * @param  bool $ignore  if $ignore is set
	 * 
	 * @return ?string  he insert id of the new record or null on failure
	 */
	public function insert(string $table, array $data, array $allowed=[], bool $ignore=false) 
	{
		if(!empty($allowed)) {
			$this->remote_forbidden_fields($data, $allowed);			
		}

		$ignore_mod = ($ignore) ? "IGNORE" : "";

		if(!empty($data)) {
			$query = "INSERT $ignore_mod INTO $table SET ";
			$query .= $this->build_update_field_list($data);
			return $this->insert_record($query,$data);
		}
		return null;
	}

	/**
	 * Undocumented function
	 *
	 * @param string $table table name or expression
	 * @param array $data associative array of fields and values
	 * @param string $where where clause
	 * @param array $params parameters for where clause if needed.
	 * @param array $allowed list of allowed fields to be updated. Any field in $data that is not in this list will be removed. If $allowed is not passed or empty array is passed, all fields will be allowed.
	 * @return ?integer number of rows affected or null on failure
	 */
	public function update(string $table, array $data, string $where, array $params=[], array $allowed=[]) :int
	{
		if(!empty($allowed)) {
			$this->remote_forbidden_fields($data, $allowed);
		}

		if(!empty($data)) {
			$query = "UPDATE $table SET ";
			$query .= $this->build_update_field_list($data);
			$query .= " WHERE $where";
			return $this->update_record($query, array_merge($data,$params));
		}

		return null;
	}

	/**
	 * run query and get the first row of the result. 
	 *
	 * @param string $query
	 * @param array $params
	 * @return array|object|null
	 */
	public function get_record(string $query, array $params=[])
	{
		$records = $this->run_query($query, $params);

		if (!empty($records)) {
			return $records[0];
		} else {
			return null;
		}
	}

	/**
	 * get value of a single field from a row in database
	 *
	 * NOTE: query must set the field name to "value". For example: "SELECT id AS value FROM user LIMIT 1";
	 * 
	 * @param string $query
	 * @param array $params prepare parameters
	 * @return mix value of field or null on failure.
	 */
	public function get_value(string $query, array $params=[])
	{
		$record = $this->get_record($query, $params);
		if(is_array($record)) {
			return $record['value'];
		} elseif(is_object($record)) {
			return $record->value;
		}

		return null;
	}

	/**
	 * run query and get all rows of the result.
	 *
	 * @param string $query
	 * @param array $params
	 * @return array|null array of result or null on failure or empty result set.
	 */
	public function get_list(string $query, array $params=[])
	{
		return $this->run_query($query, $params);
	}

	/**
	 * run query for updating fields in database
	 *
	 * @param string $query
	 * @param array $params
	 * @return integer returns number of rows affected
	 */
	public function update_record(string $query, array $params=[]) : int
	{
		return $this->run_update_query($query, $params);
	}

	/**
	 * run query for inserting a new row in database
	 *
	 * @param string $query
	 * @param array $params
	 * @return string last inserted id.
	 */
	public function insert_record(string $query, array $params=[]) :string
	{
		return $this->run_insert_query($query,$params);
	}

	/**
	 * run query for deleting fields in database
	 *
	 * @param string $query
	 * @param array $params
	 * @return integer returns number of rows affected
	 */
	public function delete_record(string $query, array $params=[]) :int
	{
		return $this->run_update_query($query, $params);
	}

	/**
	 * Set class to be instantiated for the very next call to fetch data from database.
	 *
	 * @param string $class
	 * @param array $params
	 * @return $this
	 */
	public function set_class(string $class, array $params=[])
	{
		$this->fetch_class_or_object = $class;
		$this->fetch_class_params = $params;
		return $this;
	}
 
	/**
	 * Set object to load query result into.
	 * 
	 * @param object $object
	 * @return $this
	 */
	public function set_object(object $obj) {
		$this->fetch_class_or_object = $obj;
		$this->fetch_class_params = [];
		return $this;
	}

	/**
	 * call PDO->beginTransaction()
	 * PDO beginTransaction()
	 *
	 * @return object
	 */
	public function start_transaction(): object
	{
		$pdo = $this->connect(false); //write connection
		$pdo->beginTransaction();
		return $this;
	}

	/**
	 * call PDO->commit()
	 * Commit transaction started with start_transaction
	 *
	 * @return boolean true on success
	 */
	public function commit(): bool
	{
		$pdo = $this->connect(false); //write connection
		return $pdo->commit();
	}

	/**
	 * call PDO->rollback()
	 * Rollback transaction started with start_transaction
	 *
	 * @return boolean true on success
	 */
	public function rollback(): bool
	{
		$pdo = $this->connect(false); //write connection
		return $pdo->rollback();
	}

	/**
	 * call PDO->exec. 
	 *
	 * @param string $query
	 * @param boolean $use_read_connection
	 * @return integer|false number of affected rows. or false on failure.
	 */
	public function exec(string $query): int
	{
		$pdo = $this->connect(false);	//write connection
		return $pdo->exec($query);
	}

	/**
	 * call PDO->prepare
	 *
	 * @param string $query
	 * @param boolean $use_read_connection, default to use read connection
	 * @param array $driver_options
	 * @return PDOStatement
	 */
	public function prepare(string $query, bool $use_read_connection=true, array $driver_options=[]) :PDOStatement
	{
		$pdo = $this->connect($use_read_connection);
		return $pdo->prepare($query, $driver_options);
	}

	/**
	 * Get PDO instance
	 *
	 * @param boolean $read_connection
	 * @return PDO
	 */
	public function get_connection(bool $read_connection = true) 
	{
		return $this->connect($read_connection);
	}



	protected function run_query(string $query, array $params=[], bool $use_read_connection=true)
	{
		$pdo = $this->connect($use_read_connection);
		$statement = $this->run($pdo, $query, $params);
		if(!empty($statement)) {
			if(empty($this->fetch_class_or_object)) {
				return $statement->fetchAll();
			} else {
				$fetch_class = $this->fetch_class_or_object;
				$fetch_class_params = $this->fetch_class_params;
				$this->fetch_class_or_object = '';
				$this->fetch_class_params = [];

				if(is_object($fetch_class)) {
					$statement->setFetchMode(PDO::FETCH_INTO, $fetch_class);
					$result = $statement->fetch();
					return [$result];
				} else {
					return $statement->fetchAll(PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE, $fetch_class, $fetch_class_params);
				}
			}
		} else {
			return null;
		}
	}

	protected function connect(bool $to_read = true) :PDO
	{
		if($to_read) {
			if(isset($this->read_connection)) {
				return $this->read_connection;
			}
			$settings = $this->read_creds;
		} else {
			if(isset($this->write_connection)) {
				return $this->write_connection;
			}
			$settings = $this->write_creds;		
		}

		if(empty($settings)) {
			$connection_type = ($to_read) ? "read" : "write";
			throw new InvalidArgumentException("Database connection settings not provided for $connection_type operation.");
		}

		$connection = new PDO($settings['dsn'], $settings['user'], $settings['password'], $settings['options']);

		if($to_read) {
			$this->read_connection = $connection;
		} else {
			$this->write_connection = $connection;
		}

		return $connection;
	}

	protected function call_error_handler(string $error, int $code, string $file, int $line) 
	{
		$message = "File: $file Line: $line\tMessage: $error";
		if($this->handler) {			
			call_user_func($this->error_handler, "Code: $code\t" . $message);
		} else {
			throw new \RuntimeException($message, $code); 
		}
	}

	protected function run(PDO $pdo, string $query, array $params) :?PDOStatement
	{
		try {
			$statement = $pdo->prepare($query);

			if($statement === false) {
				$this->call_error_handler($query . print_r($pdo->errorInfo(),1), self::ERROR_CODE, __FILE__, __LINE__);
				return null;
			} elseif(empty($params)) {
				$success = $statement->execute();
			} else {
				$success = $statement->execute( $this->add_colon_to_keys($params) );
			}

			if($success) {
				return $statement;
			} else {
				$this->call_error_handler('Faild query '.$query . print_r($pdo->errorInfo(),1), self::ERROR_CODE, __FILE__, __LINE__);
				return null;			
			}
		} catch (\Exception $e) {
			$this->call_error_handler('Failed query ' . $query . print_r($pdo->errorInfo(),1), self::ERROR_CODE, __FILE__, __LINE__);
			return null;
		}		
	}

	protected function run_insert_query(string $query, array $params=[]) : string
	{
		$pdo = $this->connect(false);
		$statement = $this->run($pdo, $query, $params);
		if(!empty($statement)) {
			return $pdo->lastInsertId();
		} else {
			return null;
		}
	}
	
	protected function run_update_query(string $query, array $params=[]) :int
	{
		$pdo = $this->connect(false);
		$statement = $this->run($pdo, $query, $params);
		if(!empty($statement)) {
			return $statement->rowCount();
		} else {
			return 0;
		}
	}

	protected function add_colon_to_keys(array $subject_array) : array
	{
		if($this->is_indexed_array($subject_array)) {
			return $subject_array;
		}
	
		$tmp = [];
		foreach ($subject_array as $key=> $value) {
			$key = Filter::alpha_numeric($key,'','_');
			$tmp[':'.$key] = $value;
		}
		return $tmp;
	}

	protected function is_indexed_array(array $subject_array) :bool
	{
		return (array_keys($subject_array) === range(0, count($subject_array) - 1));
	}

	protected function build_select_field_list($fields=[]) :string
	{
		if (empty($fields)) {
			return '*';
		} 
		
		if (is_array($fields)) {
			return implode(',', $fields);
		}
		
		return $fields;	//string just return itself
	}

	protected function build_update_field_list(array &$data) :string
	{
		$fields = [];
		foreach ($data as $key=>$value) {
			if($value === null) {
				$fields[] = "`$key` = NULL ";
				unset($data[$key]);
			}else{
				$fields[] = "`$key` = :" . Filter::alpha_numeric($key,'','_');
			}
		}

		return implode(',', $fields);
	}


	protected function build_partial_query(string $where='', string $group_by='', string $order_by='', int $limit=0, int $offset=0)
	{
		if(!empty($where)) {
			$where = 'WHERE ' . $where;
		}
		
		if(!empty($group_by)) {
			$group_by = 'GROUP BY ' . $group_by;
		}
	
		if(!empty($order_by)) {
			$order_by = 'ORDER BY ' . $order_by;
		}
	
		if(!empty($limit)) {
			$limit = 'LIMIT ' . $limit . ' OFFSET ' . $offset;
		} else {
			$limit = '';
		}

		return " $where $group_by $order_by $limit";
	}

	protected function build_select_query(string $table, $fields=[], $where='', string $group_by='', string $order_by='', int $limit=1, int $offset=0)
	{
		$select = $this->build_select_field_list($fields);
		return "SELECT $select FROM $table " . $this->build_partial_query($where, $group_by, $order_by, $limit, $offset);
	}

	protected function remote_forbidden_fields(array &$data, array $allowed)
	{
		foreach ($data as $key => $value) {
			if (!in_array($key, $allowed)) {
				unset($data[$key]);	
			}
		}
	}
}