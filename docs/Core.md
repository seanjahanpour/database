# Jahan\Database\Core  







## Methods

| Name | Description |
|------|-------------|
|[__construct](#core__construct)||
|[commit](#corecommit)|call PDO->commit()
Commit transaction started with start_transaction|
|[delete_record](#coredelete_record)|run query for deleting fields in database|
|[exec](#coreexec)|call PDO->exec.|
|[get_connection](#coreget_connection)|Get PDO instance|
|[get_field](#coreget_field)|Get value of a single field from a single row in database|
|[get_list](#coreget_list)|run query and get all rows of the result.|
|[get_record](#coreget_record)|run query and get the first row of the result.|
|[get_row](#coreget_row)|Get single record from database.|
|[get_rows](#coreget_rows)|Get multiple records from database|
|[get_value](#coreget_value)|get value of a single field from a row in database|
|[insert](#coreinsert)|insert a record into database.|
|[insert_record](#coreinsert_record)|run query for inserting a new row in database|
|[prepare](#coreprepare)|call PDO->prepare|
|[rollback](#corerollback)|call PDO->rollback()
Rollback transaction started with start_transaction|
|[set_class](#coreset_class)|Set class to be instantiated for the very next call to fetch data from database.|
|[start_transaction](#corestart_transaction)|call PDO->beginTransaction()
PDO beginTransaction()|
|[update](#coreupdate)|Undocumented function|
|[update_record](#coreupdate_record)|run query for updating fields in database|




### Core::__construct  

**Description**

```php
 __construct (void)
```

 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`void`


<hr />


### Core::commit  

**Description**

```php
public commit (void)
```

call PDO->commit()
Commit transaction started with start_transaction 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`bool`

> true on success


<hr />


### Core::delete_record  

**Description**

```php
public delete_record (string $query, array $params)
```

run query for deleting rows in database 

 

**Parameters**

* `(string) $query`
* `(array) $params`

**Return Values**

`int`

> returns number of rows affected


<hr />


### Core::exec  

**Description**

```php
public exec (string $query, bool $use_read_connection)
```

call PDO->exec. 

 

**Parameters**

* `(string) $query`
* `(bool) $use_read_connection`

**Return Values**

`int|false`

> number of affected rows. or false on failure.


<hr />


### Core::get_connection  

**Description**

```php
public get_connection (bool $read_connection)
```

Get PDO instance 

 

**Parameters**

* `(bool) $read_connection`

**Return Values**

`\PDO`




<hr />


### Core::get_field  

**Description**

```php
public get_field (string $table, array|string $field, string $where, array $params, string $group_by, string $order_by, int $offset)
```

Get value of a single field from a single row in database 

 

**Parameters**

* `(string) $table`
: table name or expression  
* `(array|string) $field`
: to get value of. This should be a single member array or string with single field name.  
* `(string) $where`
: where clause  
* `(array) $params`
: where clause parameters is any  
* `(string) $group_by`
: grouping expression  
* `(string) $order_by`
: sorting expression  
* `(int) $offset`
: offset.  

**Return Values**

`\mix`

> field value from database or blank string.


<hr />


### Core::get_list  

**Description**

```php
public get_list (string $query, array $params)
```

run query and get all rows of the result. 

 

**Parameters**

* `(string) $query`
* `(array) $params`

**Return Values**

`array|null`

> array of result or null on failure or empty result set.


<hr />


### Core::get_record  

**Description**

```php
public get_record (string $query, array $params)
```

run query and get the first row of the result. 

 

**Parameters**

* `(string) $query`
* `(array) $params`

**Return Values**

`array|object|null`




<hr />


### Core::get_row  

**Description**

```php
public get_row (string $table, string|array $fields, string $where, array $params, string $group_by, string $order_by, int $offset)
```

Get single record from database. 

 

**Parameters**

* `(string) $table`
: table name or expression  
* `(string|array) $fields`
: List of fields to SELECT from database. It can be comma separated list of array of string.  
Example: 'id,first_name, last_name'  
Example: ['id', 'first_name', 'last_name']  
Example: '*'  
Empty string or array is same as '*'  
* `(string) $where`
: where clause  
* `(array) $params`
: array of values for where clause  
* `(string) $group_by`
: grouping expression  
* `(string) $order_by`
: sorting expression  
* `(int) $offset`
: offset. No offset if no value passed.  

**Return Values**

`array|object|null`

> depending on PDO option set by __construct, result will return an object or an array. Null will be returned when no results where found.


<hr />


### Core::get_rows  

**Description**

```php
public get_rows (string $table, array|string $fields, string $where, array $params, string $group_by, string $order_by, int $limit, int $offset)
```

Get multiple records from database 

 

**Parameters**

* `(string) $table`
: table name or expression  
* `(array|string) $fields`
: List of fields to SELECT from database. It can be comma separated list of array of string.  
Example: 'id,first_name, last_name'  
Example: ['id', 'first_name', 'last_name']  
Example: '*'  
Empty string or array is same as '*'  
* `(string) $where`
: where clause  
* `(array) $params`
: array of values for where clause  
* `(string) $group_by`
: grouping expression  
* `(string) $order_by`
: sorting expression  
* `(int) $limit`
: maximum number of records  
* `(int) $offset`
: offset.  

**Return Values**

`array|object|null`

> depending on PDO option set by __construct, result will return an object or an array. Null will be returned when no results where found.


<hr />


### Core::get_value  

**Description**

```php
public get_value (string $query, array $params)
```

get value of a single field from a row in database 

NOTE: query must set the field name to "value". For example: "SELECT id AS value FROM user LIMIT 1"; 

**Parameters**

* `(string) $query`
* `(array) $params`
: prepare parameters  

**Return Values**

`\mix`

> value of field or null on failure.


<hr />


### Core::insert  

**Description**

```php
public insert (string $table, array $data, array $allowed, bool $ignore)
```

insert a record into database. 

 

**Parameters**

* `(string) $table`
: table name or expression  
* `(array) $data`
: associative array of keys and values  
* `(array) $allowed`
: array with list of all the fields that are allowed to be inserted. Empty array allows all fields passed in $data to be inserted.  
* `(bool) $ignore`
: if $ignore is set  

**Return Values**

`?string`

> he insert id of the new record or null on failure


<hr />


### Core::insert_record  

**Description**

```php
public insert_record (string $query, array $params)
```

run query for inserting a new row in database 

 

**Parameters**

* `(string) $query`
* `(array) $params`

**Return Values**

`string`

> last inserted id.


<hr />


### Core::prepare  

**Description**

```php
public prepare (string $query, bool $use_read_connection,, array $driver_options)
```

call PDO->prepare 

 

**Parameters**

* `(string) $query`
* `(bool) $use_read_connection,`
: default to use read connection  
* `(array) $driver_options`

**Return Values**

`\PDOStatement`




<hr />


### Core::rollback  

**Description**

```php
public rollback (void)
```

call PDO->rollback()
Rollback transaction started with start_transaction 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`bool`

> true on success


<hr />


### Core::set_class  

**Description**

```php
public set_class (string $class, array $params)
```

Set class to be instantiated for the very next call to fetch data from database. 

 

**Parameters**

* `(string) $class`
* `(array) $params`

**Return Values**

`$this`




<hr />


### Core::start_transaction  

**Description**

```php
public start_transaction (void)
```

call PDO->beginTransaction()
PDO beginTransaction() 

 

**Parameters**

`This function has no parameters.`

**Return Values**

`object`




<hr />


### Core::update  

**Description**

```php
public update (string $table, array $data, string $where, array $params, array $allowed)
```

Undocumented function 

 

**Parameters**

* `(string) $table`
: table name or expression  
* `(array) $data`
: associative array of fields and values  
* `(string) $where`
: where clause  
* `(array) $params`
: parameters for where clause if needed.  
* `(array) $allowed`
: list of allowed fields to be updated. Any field in $data that is not in this list will be removed. If $allowed is not passed or empty array is passed, all fields will be allowed.  

**Return Values**

`?int`

> number of rows affected or null on failure


<hr />


### Core::update_record  

**Description**

```php
public update_record (string $query, array $params)
```

run query for updating fields in database 

 

**Parameters**

* `(string) $query`
* `(array) $params`

**Return Values**

`int`

> returns number of rows affected


<hr />

