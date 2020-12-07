<?php
namespace Jahan\Database;

use Jahan\Filter\Str as Filter;
use PDO;
use PDOStatement;

class Base
{
	protected $error_handler;
	protected bool $handler;
	protected array $write_db_creds;
	protected array $read_db_creds;
	protected array $cache = [];
	protected const ERROR_CODE = 2;

	/**
	 * 
	 *
	 * @param array $write_db_creds 
	 * 	example:
	 * 		[
	 * 		'dsn' 	=> 'mysql:host=123.123.123.123;dbname=my_database;charset=utf8mb4',
	 * 		'user'	=> 'root',
	 * 		'password' => 'secret',
	 * 		'options' => [ 
	 * 				\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
	 * 				\PDO::ATTR_EMULATE_PREPARES => false,
	 * 				\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
	 * 			]
	 * 		]
	 * 	
	 * @param array $read_db_creds same as $write_db_creds. Same credentials can be used.
	 * @param callable|null $error_handler
	 */
	public function __construct(array $write_db_creds, array $read_db_creds,?callable $error_handler = null)
	{
		$this->error_handler = $error_handler;
		$this->handler = (empty($error_handler)) ? false : true;
		$this->write_db_creds = $write_db_creds;
		$this->read_db_creds = $read_db_creds;

		$options = [
			PDO::ATTR_EMULATE_PREPARES => false,
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ
		];

		if(empty($this->write_db_creds['options'])) {
			$this->write_db_creds['options'] = $options;
		}
		if(empty($this->read_db_creds['options'])) {
			$this->read_db_creds['options'] = $options;
		}
	}

	public function get_row(string $query, array $params = []) :array
	{
		$result = $this->run_query($query, $params);
		if(!empty($result[0])) {
			return $result[0];
		} else {
			return [];
		}
	}

	public function get_value(string $query,array $params = [],string $field = 'value')
	{
		$result = $this->get_row($query, $params);
		if(!empty($result)) {
			if(array_key_exists($field, $result)) {
				return $result[$field];
			} else {
				throw new \InvalidArgumentException("Query is not returning the expected field");
			}
		}
		return '';
	}

	public function get_list(string $query, array $params = []) :array
	{
		return $this->run_query($query, $params);
	}

	public function update(string $query, array $params = []) : int
	{
		return $this->run_update_query($query, $params);
	}

	public function insert(string $query, array $params = []) : string
	{
		return $this->run_insert_query($query,$params);
	}

	public function delete(string $query, array $params=[]) :int
	{
		return $this->run_update_query($query, $params);
	}

	/**
	* Cache are stored specific to query and params. When this function is called, and cache value for
	*	given query+params doesn't exists, cache will be created and stored using $cache_key column from results.
	* When calling on cached result, $cache_key is ignored.
	*
	* Usage:
	*	get_cached_row(1, "SELECT * FROM table1 WHERE category= :c1", ['c1'=>'fun_record']);
	*		returns row with id=1
	*	get_cached_row('unique_value', "SELECT * FROM table1", [], 'unique_row');
	*		returns row with unique_row = unique_value
	*/
	public function get_cached_row($key_value, string $query, array $params = [],string $cache_key = 'id') :array
	{
		$cache_id = Filter::alpha_numeric($query) . Filter::alpha_numeric(implode('',$params));

		//if cache is empty, built it.
		if(empty($this->cache[$cache_id])) {
			$result = $this->run_query($query, $params);
			if (!empty($result)) {
				foreach ($result as $row) {
					//do we already have a row with this key?
					if(  !empty($this->cache[$cache_id][  $row[$cache_key]  ])  ) {
						//store multiple rows for the same key in an array that contains all rows.
						//if array for key is already created, just add this row to it. 
						//otherwise make the previous value into an array element and add this $row to the array.
						if(  empty($this->cache[$cache_id][  $row[$cache_key]  ][$cache_key])  ) {
							$this->cache[$cache_id][  $row[$cache_key]  ][] = $row;
						} else {
							$this->cache[$cache_id][  $row[$cache_key]  ]= [  $this->cache[$cache_id][  $row[$cache_key]  ]  ];
							$this->cache[$cache_id][  $row[$cache_key]  ][] = $row;
						}
					} else {
						//store $row in cache using the key
						$this->cache[$cache_id][  $row[$cache_key]  ] = $row;
					}
				}
			} else {
				$this->cache[$cache_id] = [];
			}
		}


		if(!empty($this->cache[$cache_id][$key_value])) {
			return $this->cache[$cache_id][$key_value];
		} else {
			return [];
		}
	}

	public function get_list_from_cache(string $query, array $params = []) :array
	{
		$cache_id = Filter::alpha_numeric($query) . Filter::alpha_numeric(implode('',$params));
		
		if(!empty($this->cache[$cache_id])) {
			return $this->cache[$cache_id];
		} else {
			return [];
		}
	}

	protected function run_query(string $query, array $params = [], $read_connection = true) :array
	{
		$pdo = $this->connect($read_connection);
		$statement = $this->run($pdo, $query, $params);
		if(!empty($statement)) {
			return $statement->fetchAll();
		} else {
			return [];
		}
	}

	protected function connect(bool $to_read = true) 
	{
		static $read_connection = null;
		static $write_connection = null;

		if($to_read) {
			if(isset($read_connection)) {
				return $read_connection;
			}
			$settings = $this->read_db_creds;
		} else {
			if(isset($write_connection)) {
				return $write_connection;
			}
			$settings = $this->write_db_creds;		
		}

		$connection = new PDO($settings['dsn'], $settings['user'], $settings['password'], $settings['options']);

		if($to_read) {
			$read_connection = $connection;
		} else {
			$write_connection = $connection;
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
				$this->call_error_handler($query . print_r($pdo->errorInfo(),1), $this->ERROR_CODE, __FILE__, __LINE__);
				return null;
			} elseif(empty($params)) {
				$success = $statement->execute();
			} else {
				$success = $statement->execute( $this->add_colon_to_keys($params) );
			}

			if($success) {
				return $statement;
			} else {
				$this->call_error_handler('Faild query '.$query . print_r($pdo->errorInfo(),1), $this->ERROR_CODE, __FILE__, __LINE__);
				return null;			
			}
		} catch (\Exception $e) {
			$this->call_error_handler('Failed query ' . $query . print_r($pdo->errorInfo(),1), $this->ERROR_CODE, __FILE__, __LINE__);
			return null;
		}		
	}

	protected function run_insert_query(string $query, array $params = []) : string
	{
		$pdo = $this->connect(false);
		$statement = $this->run($pdo, $query, $params);
		if(!empty($statement)) {
			return $pdo->lastInsertId();
		} else {
			return '';
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
}