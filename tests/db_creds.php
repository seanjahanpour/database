<?php 

return [
	'fetch_obj'=>[
		'user'	=> 'test',
		'password' => 'k:bxY5t+PQW]3;Jb',
		'dsn' => 'mysql:host=localhost;dbname=test;charset=utf8mb4',
		'options' => [ 
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
			\PDO::ATTR_EMULATE_PREPARES => false,
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		],
	],
	'fetch_assoc'=>[
		'user'	=> 'test',
		'password' => 'k:bxY5t+PQW]3;Jb',
		'dsn' => 'mysql:host=localhost;dbname=test;charset=utf8mb4',
		'options' => [ 
			\PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
			\PDO::ATTR_EMULATE_PREPARES => false,
			\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
		],
	]
];