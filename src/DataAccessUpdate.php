<?php
namespace Jahan\Database;

use Jahan\Filter\Str as StrFilter;

class DataAccessUpdate
{
	public function __construct(Core $db, string $folder = 'tables/', string $namespace = '', string $base_class = '\Jahan\Database\DataAccessBase')
	{
		if(!file_exists(trim($folder, '/'))) {
			$files = glob($folder . '*.php');
			if(empty($files)) {
				return; //nothing to do.
			}
		} else {
			echo $folder;
			echo "Folder not exists";
			return;	//nothing to do.
		}

		echo "<pre>";

		foreach($files as $file) {
			$class_name = $namespace . "\\" . basename($file, '.php');
			include_once $file;

			if(!class_exists($class_name)) {
				die("Unable to find class $class_name");
			}

			//get table name from class
			$table = $class_name::TABLE;

			//create table if it doesn't exist
			$db->exec("CREATE TABLE IF NOT EXISTS $table (id INT NOT NULL)");

			//load table information


			$table_info = $db->get_list("DESCRIBE `$table`");			
			
			$field_properties = [];
			foreach($table_info as $field_info) {
				$field_info = (array) $field_info;

				$nullable = ($field_info['Null'] == 'YES') ? true : false;
				$type = strstr($field_info['Type'], '(', true);
				$type = ($type) ?: $field_info['Type'];

				$default = $field_info['Default'];
				if($default === null) {
					if($nullable) {
						$default = 'null';
					}
				}
				
				$default = ($default === 'null') ? 'null' : "'$default'";

				switch (strtolower($type)) {
					case 'varchar':
					case 'varbinary':
					case 'char':
					case 'text':
					case 'blob':
					case 'mediumtext':
					case 'mediumblob':
					case 'longtext':
					case 'longblob':
						break;
					case 'tinyint':
					case 'tinyint unsigned':
					case 'smallint':
					case 'smallint unsigned':
					case 'int':
					case 'int unsigned':
					case 'bigint':
					case 'bigint unsigned':
					case 'decimal':
					case 'float':
					case 'float unsigned':
						$default = ($default === 'null') ? 'null' : trim($default, "'");
						break;
					case 'timestamp':
					case 'datetime':
						if(stripos($field_info['Default'],'CURRENT_TIMESTAMP') !== false) {
							$default = 'CURRENT_TIMESTAMP()';
						} else {
							$default = trim($default, "'");
						}		
						break;
					case 'date':
						break;
					case 'enum':
					case 'set':
						break;
					default:
						throw New \Exception("Unknow field $table.{$field_info['Type']} (looking for $type)");
				}
				
				$field_name = StrFilter::alpha_numeric($field_info['Field'], [], '_');
				$field_name_first_character = substr($field_name, 0, 1);
				if($field_name_first_character == StrFilter::numbers_only($field_name_first_character, [])) {
					//first character variable name cannot be a number in php.
					$field_name = '_' . $field_name;
				}

				$field_properties[$field_name] = [
					'name' => $field_info['Field'],
					'type' => $field_info['Type'], 
					'nullable' => $nullable, 
					'default' => $default, 
					'extra' => $field_info['Extra']
				];
			}

			$field_properties_from_class = $class_name::FIELD_PROPERTIES;

			//add/modify non matching fields to database
			foreach($field_properties_from_class as $class_field_key => $class_field) {
				if(!empty($field_properties[$class_field_key])) {
					//field exists in database
					$table_field = $field_properties[$class_field_key];

					$they_are_the_same = true;
					

					if(
						($table_field['name'] != $class_field['name']) ||
						($table_field['type'] != $class_field['type']) ||
						($table_field['nullable'] != $class_field['nullable']) ||
						($table_field['default'] != $class_field['default']) ||
						($table_field['extra'] != $class_field['extra'])  
					  ){
							$they_are_the_same = false;
					}

					if($they_are_the_same) {
						continue;
					}

					//database field definition is different than what is shown in class, update the database
					$query = "ALTER TABLE `$table` CHANGE COLUMN `{$class_field['name']}` " . $this->make_field_definition($class_field);
					$db->exec($query);
					echo "==================\n====== Updated ======\n==================\n";
					
					echo "===================================\n";
					echo "Table: $table\n";
					echo "Field: $class_field_key\n\n";
					echo "Class:\n";
					var_dump($class_field);
					echo "Table\n";
					var_dump($table_field);
					echo "\n\n\n";
				} else {
					//add field to database
					echo $class_field_key . " missing in table fields<br>";

					$query = "ALTER TABLE `$table` ADD COLUMN " . $this->make_field_definition($class_field);
					$db->exec($query);

					echo "==================\n====== ADDED ======\n==================\n";
					
					echo "===================================\n";
					echo "Table: $table\n";
					echo "Field: $class_field_key\n\n";
					echo "Class:\n";
					var_dump($class_field);
					echo "Table\n";
					var_dump($table_field);
					echo "\n\n\n";
				}
			}

			//find differences that database has but local doesn't
			foreach($field_properties as $field_key => $table_field) {
				if(!empty($field_properties_from_class[$field_key])) {
					//field exists in class
					$class_field = $field_properties_from_class[$field_key];

					$they_are_the_same = true;
					

					if(
						($table_field['name'] != $class_field['name']) ||
						($table_field['type'] != $class_field['type']) ||
						($table_field['nullable'] != $class_field['nullable']) ||
						($table_field['default'] != $class_field['default']) ||
						($table_field['extra'] != $class_field['extra'])  
					  ){
							$they_are_the_same = false;
					}

					if($they_are_the_same) {
						continue;
					}
					
					echo "=============== Field Definition Is Different ====================\n";
					echo "============ THIS SHOULD RESOLVE ITSELF BY TABLE UPDATE ABOVE ============\n";
					echo "Table: $table\n";
					echo "Field: $field_key\n\n";
					echo "Class:\n";
					var_dump($class_field);
					echo "Table\n";
					var_dump($table_field);
					echo "\n\n\n";
				} else {
					echo "================ Field NOT FOUND In Class ===================\n";
					echo "Table: $table\n";
					echo "Field: $field_key\n\n";
					var_dump($table_field);
					echo "\n\n\n";
				}
			}			
		}
	}

	protected function make_field_definition($field_info) 
	{
		$field_name = $field_info['name'];
		$type = $field_info['type'];
		$null = ($field_info['nullable']) ? '' : 'NOT NULL';
		$default = (empty($field_info['default'])) ? '' : "DEFAULT {$field_info['default']}";
		$extra = $field_info['extra'];
		
		return "`$field_name` $type $null $default $extra";
	}
}