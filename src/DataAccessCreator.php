<?php
namespace Jahan\Database;

use Jahan\Filter\Str as StrFilter;

/**
 * DataAccessCreator generates classes representing all the fields and details of all tables in the database.
 * One class per table is created.
 */

class DataAccessCreator
{
	public function __construct(Core $db, string $folder = 'tables/', string $namespace = '', string $base_class = '\Jahan\Database\DataAccessBase')
	{
		$tables = $db->get_list("SHOW TABLES");

		if(!file_exists($folder)) {
			mkdir($folder);
		}
		
		if(!empty($base_class)) {
			$base_class = 'extends ' . $base_class;
		}

		foreach($tables as $table) {
			$table = (array) $table;
			$table = array_values($table)[0];

			/*if(strpos($table,'pma') !== false) {
				continue; //skip all PHP Myadmin Tables;
			}*/

			$class_name = ucwords($table, '_');
			$class_name = StrFilter::alpha_numeric($class_name);

			$file_name = $folder . $class_name . '.php';
			if(!file_exists($file_name)) {
				

				$content = <<<CORE
					<?php
					namespace $namespace;

					use Jahan\Database\DateTime;
					use Jahan\Filter\Str as StrFilter;

					class $class_name $base_class
					{
						public const TABLE = '$table';
					
					CORE;
				
				$lazy_load_fields = [];
				$updateables = [];
				$insertables = [];
				$fields = [];
				$field_properties = [];
				$primary_key = '';
				$constants = [];
				$need_setter = [];
				
				$table_info = $db->get_list("DESCRIBE `$table`");

				foreach($table_info as $field_info) {
					$field_info = (array) $field_info;

					$type = strstr($field_info['Type'], '(', true);
					$type = ($type) ?: $field_info['Type'];

					$var_type = ($field_info['Null'] == 'YES') ? '?' : '';
					$updateable = true;
					$insertable = true;
					$lazy_load = false;

					$field_name = $field_info['Field'];
					$field_name_raw = $field_name;

					$field_name = StrFilter::alpha_numeric($field_name, [], '_');
					$field_name_first_character = substr($field_name, 0, 1);
					if($field_name_first_character == StrFilter::numbers_only($field_name_first_character, [])) {
						//first character variable name cannot be a number in php.
						$field_name = '_' . $field_name;
					}
					

					$nullable = ($field_info['Null'] == 'YES') ? 'true' : 'false';

					$default = ($field_info['Default'] === null && $nullable === 'true') ? 'null' : "'{$field_info['Default']}'";
					$property_default = 'NOT SET';

					if($field_info['Key'] == 'PRI') {
						$primary_key = $field_name;
						if(stripos($field_info['Extra'], 'auto_increment') !== false) {
							$updateable = false;
							$need_setter[] = ['type'=>'auto_increment', 'name'=> $field_name];
						}	
					}

					switch (strtolower($type)) {
						case 'varchar':
						case 'varbinary':
							$max_length = StrFilter::numbers_only($field_info['Type'],[]);
							$lazy_load = ($max_length > 255);
						case 'char':
							$var_type .= 'string';
							break;
						case 'text':
						case 'blob':
						case 'mediumtext':
						case 'mediumblob':
						case 'longtext':
						case 'longblob':
							$var_type .= 'string';
							$lazy_load = true;
							break;
						case 'tinyint':
							$length = StrFilter::numbers_only($field_info['Type'],[]);
							if($length == 1) {
								$var_type .= 'bool';
								if($default != 'null') {
									$default = ($field_info['Default'] == '1') ? 'true' : 'false';
									$property_default = $field_info['Default']; //0 or 1 for false or true
								}
								break;
							}
						case 'tinyint unsigned':
						case 'smallint':
						case 'smallint unsigned':
						case 'int':
						case 'int unsigned':
						case 'bigint':
						case 'bigint unsigned':
							$var_type .= 'int';
							$default = ($default === 'null') ? 'null' : trim($default, "'");
							break;
						case 'decimal':
						case 'float':
						case 'float unsigned':
							$var_type .= 'float';
							$default = ($default === 'null') ? 'null' : trim($default, "'");
							break;
						case 'timestamp':
						case 'datetime':
							if($default !== 'null') {
								$default = '';
								$property_default = $field_info['Default'];
							}
							


							if(stripos($field_info['Default'],'CURRENT_TIMESTAMP') !== false) {
								$insertable = false;
								$updateable = false;
								$property_default = 'CURRENT_TIMESTAMP()';
							}
							
							if(stripos($field_info['Extra'], 'on update CURRENT_TIMESTAMP()') !== false) {
								$updateable = false;
							}
							
							
							
							$need_setter[] = ['type'=>'DateTime', 'name'=> $field_name];

							$var_type .= 'DateTime';
							
							break;
						case 'date':
							$var_type .= 'string';
							break;
						case 'enum':
						case 'set':
							$var_type .= 'string';

							$matches = [];
							preg_match_all("@'(.*?)'@", $field_info['Type'], $matches);

							$this_field_constants = [];

							if(!empty($matches)) foreach($matches[1] as $match) {
								$const = $field_name;
								if(!empty($match)) {
									$m = StrFilter::alpha_numeric($match, [], '_');
									$const = $const . '_' . $m;
								}
								$const = strtoupper($const);
								$constants[$const] = $match;
								$this_field_constants[$const] = $match;
							}

							$need_setter[] = ['type'=>'enum', 'name'=> $field_name, 'values'=>$this_field_constants];
							break;
						default:
							throw New \Exception("Unknow field $table.{$field_info['Type']} (looking for $type)");
					}

					$property_default = ($property_default == 'NOT SET') ? $default : $property_default;
					$field = $field_name;

					if($lazy_load) {
						$lazy_load_fields[] = $field;
					}

					if($insertable) {
						$insertables[] = $field;
					}

					if($updateable) {
						$updateables[] = $field;
					}

					$fields[] = ['field'=>$field,'type'=>$var_type,'default'=>$default];
					$field_properties[] = ['field'=>$field_name_raw,'type'=>$field_info['Type'], 'nullable'=>$nullable, 'default'=>$property_default, 'extra'=>$field_info['Extra']];
				}

				if(!empty($primary_key)) {
					$content .= "	public const PK = '$primary_key';" . PHP_EOL;
				}
				
				$content .= PHP_EOL;

				if(!empty($constants)) {
					foreach($constants as $name=>$const) {
						$content .= "	const $name = '$const';" . PHP_EOL;
					}

					$content .= PHP_EOL;					
				}



				if(!empty($lazy_load_fields)) {
					$lazy_load = implode("', '", $lazy_load_fields);
					$content .= "	public const LAZY_LOAD_FIELDS = ['$lazy_load'];		//fields to not load when loading record. This fields are automatically pulled from database on access" . PHP_EOL . PHP_EOL;
				}

				$content .= "	public const INDEX_FIELDS = [];		//empty array means do not show any fields for index page" . PHP_EOL;
				$content .= "				// To change any of class const programatically, change the variable type to static, or declare new variable with the same name" . PHP_EOL;

				if(!empty($updateables)) {
					$tmp = implode("', '", $updateables);
					$content .= "	public const UPDATABLES = ['$tmp'];	//empty array means all fields are updatable" . PHP_EOL;
				}

				if(!empty($insertables)) {
					$tmp = implode("', '", $insertables);
					$content .= "	public const INSERTABLES = ['$tmp'];	//empty array means all fields are insertable" . PHP_EOL;
				}
				
				$content .= PHP_EOL;

				if(!empty($fields)) {
					$tmp = array_column($fields, 'field');
					$tmp = implode("', '", $tmp);
					$content .= "	public const FIELDS = ['$tmp'];" . PHP_EOL;

					$content .= "	public const FIELD_PROPERTIES = [" . PHP_EOL;
					foreach($field_properties as $field) {
						$field_name_tmp = StrFilter::alpha_numeric($field['field'], [], '_');
						$field_name_tmp_first_character = substr($field_name_tmp, 0, 1);
						if($field_name_tmp_first_character == StrFilter::numbers_only($field_name_tmp_first_character, [])) {
							//first character variable name cannot be a number in php.
							$field_name_tmp = '_' . $field_name_tmp;
						}

						$content .= "		'" . $field_name_tmp . "' => ['name' => '{$field['field']}', 'type' => \"{$field['type']}\", 'nullable' => {$field['nullable']}, 'default' => \"{$field['default']}\", 'extra' => \"{$field['extra']}\"]," . PHP_EOL;
					}
					$content .= "	];" . PHP_EOL . PHP_EOL;
					
					foreach($fields as $field) {
						if(in_array($field['field'], $lazy_load_fields)) {
							$exposure = 'protected';
						} else {
							$exposure = 'public';
						}
						
						$name = $field['field'];
						if(in_array($name, array_column($need_setter, 'name'))) {
							$exposure = 'protected';
							$name = '_' . $name;
						}

						$content .= "	$exposure {$field['type']} \$$name";
						
						if(empty(  trim($field['default'], "' \t\n\r\0\x0B") )) {
							$content .= ";";
						} else {
							$content .= " = {$field['default']};";
						}

						$content .= PHP_EOL;
					}
				}

				$content .= PHP_EOL;
				
				if(!empty($need_setter)) {
					$content .= "	public function __set(\$property, \$value)" . PHP_EOL;
					$content .= "	{" . PHP_EOL;
					$content .= "		\$property = StrFilter::alpha_numeric(\$property, [], '_');" . PHP_EOL;
					$content .= PHP_EOL;
					$content .= "		switch(\$property) {" . PHP_EOL;
								
					$date_time_fields = [];
					foreach($need_setter as $field) {
						if($field['type'] == 'DateTime') {
							$date_time_fields[] = $field;
						} elseif($field['type'] == 'enum') {
							$content .= "			case '{$field['name']}':" . PHP_EOL;
							$content .= "				if( in_array(\$value, ['" . implode("','",$field['values']) . "']) ) {" . PHP_EOL;
							$content .= "					\$this->_{$field['name']} = \$value;" . PHP_EOL;
							$content .= "				} else {" . PHP_EOL;
							$content .= "					throw new \InvalidArgumentException('Invalid value for {$field['name']}');" . PHP_EOL;
							$content .= "				}" . PHP_EOL;
							$content .= "				break;" . PHP_EOL;
						} elseif($field['type'] == 'auto_increment') {
							//read only field. Only set when not loaded from database.
							$content .= "			case '{$field['name']}':" . PHP_EOL;
							$content .= "				if( !\$this->loaded_from_db ) {" . PHP_EOL;
							$content .= "					\$this->_{$field['name']} = \$value;" . PHP_EOL;
							$content .= "				}" . PHP_EOL;
							$content .= "				break;" . PHP_EOL;
						} else {
							$content .= "			case '{$field['name']}':" . PHP_EOL;
							$content .= "				\$this->_{$field['name']} = \$value;" . PHP_EOL;
							$content .= "				break;" . PHP_EOL;							
						}					
					}
					if(!empty($date_time_fields)) {
						foreach($date_time_fields as $field) {
							$content .= "			case '{$field['name']}':" . PHP_EOL;
						}
						$content .= "				\$property = '_' . \$property;" . PHP_EOL;
						$content .= "				if(\$value === null) {" . PHP_EOL;
						$content .= "					\$this->\$property = null;" . PHP_EOL;
						$content .= "				} else {" . PHP_EOL;
						$content .= "					\$this->\$property = new DateTime(\$value);" . PHP_EOL;
						$content .= "				}" . PHP_EOL;
						$content .= "				break;" . PHP_EOL;
					}
					$content .= "			default:" . PHP_EOL;
					$content .= "				parent::__set(\$property, \$value);" . PHP_EOL;
					$content .= "				break;" . PHP_EOL;
					
					$content .= "		}" . PHP_EOL;
					$content .= "	}" . PHP_EOL;

					$content .= "	public function __get(\$property)" . PHP_EOL;
					$content .= "	{" . PHP_EOL;
	
					$content .= "		switch(\$property) {" . PHP_EOL;				
					
					if(!empty($need_setter)) foreach($need_setter as $field) {					
						$content .= "			case '{$field['name']}':" . PHP_EOL;
					}
					$content .= "				\$property = '_' . \$property;" . PHP_EOL;
					$content .= "				return \$this->\$property;" . PHP_EOL;
					$content .= "				break;" . PHP_EOL;
					$content .= "			default:" . PHP_EOL;
					$content .= "				return parent::__get(\$property);" . PHP_EOL;
					$content .= "		}" . PHP_EOL;
					$content .= "	}" . PHP_EOL;	
				}



				$content .= '}';


				file_put_contents($file_name, $content);
			}
			
		}
		
		echo "Don't forget to set proper permission and ownership for DB files.";
	}
}