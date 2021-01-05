<?php
declare(strict_types=1);
namespace Test;

use Exception;
use Jahan\Database\Core;
use stdClass;

use function PHPUnit\Framework\assertEquals;

final class TestCore extends BaseCase
{
	protected static ?Core $core;

	public function setup(): void
	{
	}

	public static function setUpBeforeClass(): void
	{
		$database_creds = include 'db_creds.php';
		self::$core = new Core($database_creds['object'], $database_creds['object'], function($error) {
			throw new \Exception($error);
		});

		$query = "DROP TABLE IF EXISTS coreTest";
		self::$core->exec($query);

		$query = "CREATE TABLE IF NOT EXISTS coreTest (
			id INT NOT NULL AUTO_INCREMENT, 
			col1 VARCHAR(10),
			col2 INT,
			updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			inserted_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
			deleted_at TIMESTAMP NULL DEFAULT NULL,

			PRIMARY KEY (id)
			) ENGINE=InnoDB
		";

		self::$core->exec($query);	//default to use write connection to database.

		$query = "INSERT IGNORE INTO coreTest SET id=:id, col1=:col1, col2=:col2";
		$param = ['id'=>1, 'col1'=>'row1', 'col2'=>1];
		self::$core->insert_record($query, $param);
		
		$param = ['id'=>2, 'col1'=>'row2', 'col2'=>2];
		self::$core->insert_record($query, $param);
	}

	public function test_get_record()
	{
		//test with params
		$query = "SELECT col1, col2 FROM coreTest WHERE id=:id";
		$result = self::$core->get_record($query, ['id'=>2]);

		$obj = new stdClass;
		$obj->col1 = 'row2';
		$obj->col2 = 2;

		$this->assertEquals($obj, $result);


		//test without params
		$query = "SELECT col1, col2 FROM coreTest WHERE id = 2";
		$result = self::$core->get_record($query);

		$this->assertEquals($obj, $result);
	}

	public function test_get_row()
	{
		$result = self::$core->get_row('coreTest', ['col1', 'col2'], 'id=:id',['id'=>2]);

		$obj = new stdClass;
		$obj->col1 = 'row2';
		$obj->col2 = 2;

		$this->assertEquals($obj, $result);


		//test sort
		$result = self::$core->get_row('coreTest', ['col1','col2'], '', [], '', 'col1 DESC');

		$this->assertEquals($obj, $result);

		//test offset
		$result = self::$core->get_row('coreTest', ['col1','col2'], '', [], '', 'col1 ASC', 1);

		$this->assertEquals($obj, $result);
	}

	public function test_get_value()
	{
		//test with params
		$query = "SELECT col1 AS value FROM coreTest WHERE id=:id";
		$result = self::$core->get_value($query, ['id'=>2]);

		$this->assertEquals('row2', $result);	
		
		//test without params
		$query = "SELECT col1 AS value FROM coreTest WHERE id=2";
		$result = self::$core->get_value($query);

		$this->assertEquals('row2', $result);
	}

	public function test_get_field()
	{
		$result = self::$core->get_field('coreTest', 'col1', 'id=:id', ['id'=>2]);
		$this->assertEquals('row2', $result);	
	}

	public function test_get_list()
	{
		//test with params
		$query = "SELECT col1, col2 FROM coreTest WHERE 1 = :one";
		$result = self::$core->get_list($query, ['one'=>1]);

		$data = [];
		$obj1 = new stdClass();
		$obj1->col1 = 'row1';
		$obj1->col2 = 1;
		$data[] = $obj1;

		$obj2 = new stdClass();
		$obj2->col1 = 'row2';
		$obj2->col2 = 2;
		$data[] = $obj2;

		$this->assertEquals($data, $result);

		//test without params
		$query = "SELECT col1, col2 FROM coreTest";
		$result = self::$core->get_list($query);

		$this->assertEquals($data, $result);
	}

	public function test_get_rows()
	{
		$query = "SELECT col1, col2 FROM coreTest WHERE 1 = :one";
		$result = self::$core->get_rows('coreTest', ['col1', 'col2'], '1 = :one', ['one'=>1]);

		$data = [];
		$obj1 = new stdClass();
		$obj1->col1 = 'row1';
		$obj1->col2 = 1;
		$data[] = $obj1;

		$obj2 = new stdClass();
		$obj2->col1 = 'row2';
		$obj2->col2 = 2;
		$data[] = $obj2;

		$this->assertEquals($data, $result);	
	}

	public function test_update_record()
	{
		$query = "UPDATE coreTest SET col1='col1' WHERE id=:id";
		$result = self::$core->update_record($query, ['id'=>1]);

		$this->assertEquals(1, $result);

		$result = self::$core->get_value("SELECT col1 AS value FROM coreTest WHERE id=:id", ['id'=>1]);

		$this->assertEquals('col1', $result);

		//update back without params
		$query = "UPDATE coreTest SET col1='row1' WHERE id=1";
		$result = self::$core->update_record($query);

		$this->assertEquals(1, $result);

		$result = self::$core->get_value("SELECT col1 AS value FROM coreTest WHERE id=:id", ['id'=>1]);

		$this->assertEquals('row1', $result);
	}

	public function test_update()
	{
		$result = self::$core->update('coreTest', ['col1'=>'col1'], 'id=:id', ['id'=>1]);

		$this->assertEquals(1, $result);

		$result = self::$core->get_field('coreTest', 'col1', 'id=1');

		$this->assertEquals('col1', $result);

		//update back
		$result = self::$core->update('coreTest', ['col1'=>'row1'], 'id=1');

		$this->assertEquals(1, $result);

		$result = self::$core->get_field('coreTest', 'col1', 'id=1');

		$this->assertEquals('row1', $result);
	}

	public function test_insert_record_and_delete_record()
	{
		$query = "INSERT INTO coreTest SET col1=:col1, col2=:col2";
		$result = self::$core->insert_record($query, ['col1'=>'row3', 'col2'=>3]);

		$this->assertEquals(3, $result);

		$result = self::$core->get_value("SELECT col1 AS value FROM coreTest WHERE id=:id", ['id'=>3]);

		$this->assertEquals('row3', $result);

		//insert without parameters
		$query = "INSERT INTO coreTest SET col1='row4', col2=4";
		$result = self::$core->insert_record($query);

		$this->assertEquals(4, $result);

		$result = self::$core->get_value("SELECT col1 AS value FROM coreTest WHERE id=:id", ['id'=>4]);

		$this->assertEquals('row4', $result);




		//delete_record with parameter
		$query = "DELETE FROM coreTest WHERE id=:id";
		$result = self::$core->delete_record($query, ['id'=>3]);

		$this->assertEquals(1, $result);

		//delete_record without parameter
		$query = "DELETE FROM coreTest WHERE id=4";
		$result = self::$core->delete_record($query);

		$this->assertEquals(1, $result);


		$result = self::$core->get_value("SELECT COUNT(*) AS value FROM coreTest");

		$this->assertEquals(2, $result);
	}

	public function test_insert()
	{
		$result = self::$core->insert('coreTest',['col1'=>'row3', 'col2'=>3], []);
		$this->assertGreaterThan(2, $result);

		//test allowed field list
		$result = self::$core->insert('coreTest',['col1'=>'row4', 'col2'=>4, 'bad_field'=>true], ['col1','col2']);
		$this->assertGreaterThan(3, $result);



		$result = self::$core->get_field('coreTest', 'col1', '', [], '', 'col1 DESC');

		$this->assertEquals('row4', $result);




		//reset table
		$query = "DELETE FROM coreTest ORDER BY id DESC LIMIT 2";
		$result = self::$core->delete_record($query);

		$this->assertEquals(2, $result);

		$result = self::$core->get_value("SELECT COUNT(*) AS value FROM coreTest");

		$this->assertEquals(2, $result);
	}

	public function test_set_class()
	{
		$result = self::$core->set_class(CoreTestSetClassTest::class,[10])
				->get_record("SELECT id, col1, col2 FROM coreTest LIMIT 1");

		$obj = new CoreTestSetClassTest(10);
		$obj->id = 1;
		$obj->col1 = 'row1';
		$obj->col2 = 1;

		$obj2 = new stdClass();	//result shouldn't be a standard stdClass
		$obj2->id = 1;
		$obj2->col1 = 'row1';
		$obj2->col2 = 1;

		$this->assertEquals($obj, $result);
		$this->assertNotEquals($obj2, $result);

		//second run should have removed the class
		$result = self::$core->get_record("SELECT id, col1, col2 FROM coreTest LIMIT 1");
		
		$this->assertNotEquals($obj, $result);
		$this->assertEquals($obj2, $result);
	}

	public function test_start_transaction_and_commit_transaction()
	{
		self::$core->start_transaction();

		$query = "INSERT INTO coreTest SET col1=:col1, col2=:col2";
		self::$core->insert_record($query, ['col1'=>'row3', 'col2'=>3]);

		self::$core->commit();


		$result = self::$core->get_value("SELECT COUNT(*) AS value FROM coreTest");
		$this->assertEquals(3, $result);

		//cleanup
		$query = "DELETE FROM coreTest WHERE col2=:col2";
		self::$core->delete_record($query, ['col2'=>3]);

		$result = self::$core->get_value("SELECT COUNT(*) AS value FROM coreTest");
		$this->assertEquals(2, $result);



		//rollback test
		self::$core->start_transaction();

		$query = "INSERT INTO coreTest SET col1=:col1, col2=:col2";
		self::$core->insert_record($query, ['col1'=>'row3', 'col2'=>3]);

		self::$core->rollback();


		$result = self::$core->get_value("SELECT COUNT(*) AS value FROM coreTest");
		$this->assertEquals(2, $result);		
	}

	public function tearDown(): void
	{
	}

	public static function tearDownAfterClass(): void
	{
		self::$core = null;
	}
}


class CoreTestSetClassTest
{
	public int $id;
	public string $col1;
	public int $col2;
	public string $updated_at;
	public string $inserted_at;
	public string $deleted_at;

	public function __construct($var1)
	{
		if(empty($var1)) {
			throw new Exception();
		}
	}
}