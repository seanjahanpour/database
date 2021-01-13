<?php
namespace Test;
 
class CoreTestSetClassClass
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
			throw new \Exception();
		}
	}
}