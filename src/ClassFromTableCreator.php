<?php
namespace Jahan\Database;

use Jahan\Filter\Str as StrFilter;

class ClassFromTableCreator
{
	public function __construct(Base $db, string $folder = 'tables/', string $namespace = '')
	{
		$tables = $db->get_list("SHOW TABLES");

		if(!file_exists($folder)) {
			mkdir($folder);
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

					class $class_name
					{
						protected string \$_table = '$table';
					
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

					$default = "'{$field_info['Default']}'";

					if($field_info['Key'] == 'PRI') {
						$primary_key = $field_info['Field'];
						$updateable = false;
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
								$var_type = 'bool';
								$default = ($field_info['Default'] == '1') ? 'true' : 'false';
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
							$default = trim($default, "'");
							break;
						case 'decimal':
						case 'float':
						case 'float unsigned':
							$var_type .= 'float';
							$default = trim($default, "'");
							break;
						case 'timestamp':
						case 'datetime':
							if(stripos($field_info['Default'],'CURRENT_TIMESTAMP') !== false) {
								$insertable = false;
								$updateable = false;
							}
							
							if(stripos($field_info['Extra'], 'on update CURRENT_TIMESTAMP') !== false) {
								$updateable = false;
							}
							
							$need_setter[] = ['type'=>'\DateTime', 'name'=> StrFilter::alpha_numeric($field_info['Field'], [], '_')];

							$var_type = '\DateTime';
							$default = '';
							break;
						case 'date':
							$var_type .= 'string';
							break;
						case 'enum':
							$var_type .= 'string';

							$matches = [];
							preg_match_all("@'(.*?)'@", $field_info['Type'], $matches);

							$this_field_constants = [];

							if(!empty($matches)) foreach($matches[1] as $match) {
								$const = StrFilter::alpha_numeric($field_info['Field'], [], '_');
								if(!empty($match)) {
									$m = StrFilter::alpha_numeric($match, [], '_');
									$const = $const . '_' . $m;
								}
								$const = strtoupper($const);
								$constants[$const] = $match;
								$this_field_constants[$const] = $match;
							}

							$need_setter[] = ['type'=>'enum', 'name'=> StrFilter::alpha_numeric($field_info['Field'], [], '_'), 'values'=>$this_field_constants];
							break;
						default:
							throw New \Exception("Unknow field $table.{$field_info['Type']} (looking for $type)");
					}

					$field = $field_info['Field'];

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
					
					$nullable = ($field_info['Null'] == 'YES') ? 'true' : 'false';
					$field_properties[] = ['field'=>$field,'type'=>$field_info['Type'], 'nullable'=>$nullable, 'default'=>$field_info['Default'], 'extra'=>$field_info['Extra']];
				}

				if(!empty($primary_key)) {
					$content .= "	protected string \$_pk = '$primary_key';" . PHP_EOL;
				}
				
				$content .= PHP_EOL;

				if(!empty($constants)) {
					foreach($constants as $name=>$const) {
						$content .= "	const $name = '$const';" . PHP_EOL;
					}

					$content .= PHP_EOL;					
				}



				if(!empty($lazy_load_fields)) {
					$lazy_load = implode(', ', $lazy_load_fields);
					$content .= "	protected array \$lazy_load_fields = ['$lazy_load'];		//fields to not load when loading record. This fields are automatically pulled from database on access" . PHP_EOL . PHP_EOL;
				}

				$content .= "	protected array \$index_fields = [];		//empty array means do not show any fields for index page" . PHP_EOL;

				if(!empty($updateables)) {
					$tmp = implode("', '", $updateables);
					$content .= "	protected array \$updatables = ['$tmp'];	//empty array means all fields are updatable" . PHP_EOL;
				}

				if(!empty($insertables)) {
					$tmp = implode("', '", $insertables);
					$content .= "	protected array \$insertables = ['$tmp'];	//empty array means all fields are insertable" . PHP_EOL;
				}
				
				$content .= PHP_EOL;

				if(!empty($fields)) {
					$tmp = array_column($fields, 'field');
					$tmp = implode("', '", $tmp);
					$content .= "	protected array \$fields = ['$tmp'];" . PHP_EOL;

					$content .= "	protected array \$field_properties = [" . PHP_EOL;
					foreach($field_properties as $field) {
						$content .= "		'{$field['field']}' => ['type' => \"{$field['type']}\", 'nullable' => {$field['nullable']}, 'default' => \"{$field['default']}\", 'extra' => \"{$field['extra']}\"]," . PHP_EOL;
					}
					$content .= "	];" . PHP_EOL . PHP_EOL;
					
					foreach($fields as $field) {
						if(in_array($field['field'], $insertables)) {
							$exposure = 'public';
						} else {
							$exposure = 'protected';
						}

						if($exposure == 'public' && !in_array($field['field'], $updateables)) {
							$exposure = 'protected';
						}

						if(in_array($field['field'], array_column($need_setter, 'name'))) {
							$exposure = 'protected';
						}

						$name = $field['field'];
						if($exposure != 'public') {
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
					
					$content .= "		switch(\$property) {" . PHP_EOL;				
					foreach($need_setter as $field) {
						if($field['type'] == '\DateTime') {
							$content .= "			case '{$field['name']}':" . PHP_EOL;
							$content .= "				\$this->_{$field['name']} = new \DateTime(\$value);" . PHP_EOL;
							$content .= "				break;" . PHP_EOL;
						}
						if($field['type'] == 'enum') {
							$content .= "			case '{$field['name']}':" . PHP_EOL;
							$content .= "				if( in_array(\$value, ['";
							
							$content .= implode("','",$field['values']);

							$content .= "']) ) {" . PHP_EOL;
							$content .= "					\$this->_{$field['name']} = \$value;" . PHP_EOL;
							$content .= "				} else {" . PHP_EOL;
							$content .= "					throw new \InvalidArgumentException('Invalid value for {$field['name']}');" . PHP_EOL;
							$content .= "				}" . PHP_EOL;
							$content .= "				break;" . PHP_EOL;
						}					
					}
					
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