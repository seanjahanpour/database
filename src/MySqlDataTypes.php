<?php
namespace Jahan\Database;


class DBDataTypes
{
	const TINYINT 		= 1;	//Integer from -128 to 127 when signed, or from 0 to 255 when unsigned.
	const SMALLINT 		= 2;	//Integer from -32768 to 32767 when signed, or from 0 to 65535 when unsigned.
	const MEDIUMINT 	= 3;	//Integer from -8388608 to 8388607 when signed, or from 0 to 16777215 when unsigned.
	const I 		= 4;	//for INT: Integer from -2147483648 to 2147483647 when signed, or from 0 to 4294967295 when unsigned.
	const BIGINT 		= 5;	//Integer from -9223372036854775808 to 9223372036854775807 when signed, or from 0 to 18446744073709551615 when unsigned.
	const DECIMAL		= 6;	//Fixed-point number
	const F			= 7;	//for Float: Single-precision floating-point number
	const D			= 8;	//for Double: Double-precision floating-point number
	const CHAR		= 9;	//Fixed-length string with limit up to 255 bytes
	const VARCHAR		= 10;	//Variable-length string with limit up to 65,535 bytes
	const TINYTEXT		= 11;	//String for variable-length text data up to 255 bytes
	const TEXT		= 12;	//String for variable-length text data up to 65,535 bytes
	const MEDIUMTEXT	= 13;	//String for variable-length text data up to 16,777,215 bytes
	const LONGTEXT		= 14;	//String for variable-length text data up to 4,294,967,295 bytes
	const JSON		= 15;	//A synonym of LONGTEXT with a default json_valid() CHECK (check added in MariaDB Community Server 10.4.3).
	const TINYBLOB		= 16; 	//String for variable-length binary data up to 255 bytes
	const BLOB		= 17;	//String for variable-length binary data up to 65,535 bytes
	const MEDIUMBLOB	= 18;	//String for variable-length binary data up to 16,777,215 bytes
	const LONGBLOB		= 19; 	//String for variable-length binary data up to 4,294,967,295 bytes
	const BINARY		= 20;	//Fixed-length string for binary data with limit up to 255 bytes
	const VARBINARY		= 21;	//Variable-length string for binary data with limit up to 65,535 bytes
	const BIT		= 22;	//Bit data
	const ENUM		= 23;	//Value from up to 65,535 options
	const TIME		= 24;	//Hours, minutes, seconds
	const YEAR		= 25;	//Two-digit or four-digit year
	const DATE		= 26;	//Year, month, day
	const TIMESTAMP		= 27;	//Year, month, day, hours, minutes, seconds with dates from 1970 to 2038
	const DATETIME		= 28;	//Year, month, day, hours, minutes, seconds with dates from 1000 to 9999
	const B			= 29;	//See TINYINT
}