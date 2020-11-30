<?php
namespace Jahan\Database;

use \Jahan\Interfaces\LoggerInterface as Logger;
use \Jahan\Filter\Str as Filter;

class Base
{
	protected array $updatable = [];
	protected Logger $logger;
	protected DBWriter $dbwriter;
	protected DBReader $dbreader;

	public function __construct(DBReader $dbreader, DBWriter $dbwriter,Logger $logger)
	{
		$dbreader->logger = $logger;
		$dbwriter->logger = $logger;
		$this->logger = $logger;
		$this->dbreader = $dbreader;
		$this->dbwriter = $dbwriter;
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

	protected function run_query(string $query, array $params = [], bool $reading = true) :array
	{
		$pdo = $reading ? $this->dbreader->connect() : $this->dbwriter->connect();
		try {
			$statement = $pdo->prepare($query);

			if($statement === false) {
				$this->handle_query_errors($query . print_r($pdo->errorInfo(),1));
			} elseif(empty($params)) {
				$success = $statement->execute();
			} else {
				$success = $statement->execute( $this->add_colon_to_keys($params) );
			}

			if($success) {
				return $statement->fetchAll();
			} else {
				$this->handle_query_errors('Faild query '.$query . print_r($pdo->errorInfo(),1));
				return [];
			}
		} catch (\Exception $e) {
			$this->handle_query_errors('Failed query ' . $query . print_r($pdo->errorInfo(),1));
			return [];
		}
	}

	protected function run_insert_query(string $query, array $params = []) : int
	{
		try {
			$pdo = $this->dbwriter->connect();

			$statement = $pdo->prepare($query);

			if($statement === false) {
				$this->handle_query_errors($query . print_r($pdo->errorInfo(),1));
			} elseif(empty($params)) {
				$success = $statement->execute();
			} else {
				$success = $statement->execute( $this->add_colon_to_keys($params) );
			}

			if($success) {
				return $statement->lastInsertId();
			} else {
				$this->handle_query_errors('Faild query '.$query . print_r($pdo->errorInfo(),1));
				return 0;
			}
		} catch (\Exception $e) {
			$this->handle_query_errors('Failed query ' . $query . print_r($pdo->errorInfo(),1));
			return 0;
		}
	}
	
	function run_update_query(string $query, array $params=[]) :int
	{
		try {
			$pdo = $this->dbwriter->connect();

			$statement = $pdo->prepare($query);

			if($statement === false) {
				$this->handle_query_errors($query . print_r($pdo->errorInfo(),1));
			} elseif(empty($params)) {
				$success = $statement->execute();
			} else {
				$success = $statement->execute( $this->add_colon_to_keys($params) );
			}

			if($success) {
				return $statement->rowCount();
			} else {
				$this->handle_query_errors('Faild query '.$query . print_r($pdo->errorInfo(),1));
				return 0;
			}
		} catch (\Exception $e) {
			$this->handle_query_errors('Failed query ' . $query . print_r($pdo->errorInfo(),1));
			return 0;
		}
	}

	function run_delete_query(string $query, array $params=[]) :int
	{
		return $this->run_update_query($query, $params);
	}

	protected function handle_query_errors(string $error_message) 
	{
		$trace = debug_backtrace();
		unset($trace[0]);//remove call to this function.
		$globals_to_log = [];
		$globals_to_log['_GET'] = $_GET;
		$globals_to_log['_POST'] = $_POST;
		$globals_to_log['_COOKIE'] = $_COOKIE;
		$globals_to_log['_SERVER'] = $_SERVER;
		$message = "Query and Error: $error_message <br>\n<br>\n_SERVER: <pre>" . print_r(debug_backtrace(), true) . "<br>\n<br>\n" . print_r($globals_to_log, true) . "</pre>";
		$this->logger->error($message);
		debug($message);
		throw new Exception('Oops! Something went wrong. Please try again. Application support have been notified.');
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