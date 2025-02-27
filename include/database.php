<?php
/**
 * Database file
 * 
 * This file defines class that maintains database connection.
 * @author Paul Bukowski <pbukowski@telaxus.com>
 * @copyright Copyright &copy; 2006, Telaxus LLC
 * @version 1.0
 * @license MIT
 * @package epesi-base
 */
defined("_VALID_ACCESS") || die('Direct access forbidden');

/**
 * Include AdoDB.
 */
require_once('misc.php');

/**
 * This class maintains database connection.
 * @package epesi-base
 * @subpackage database
 */
class DB {
	public static $ado;
	private static $queries=array();
	private static $queries_qty=0;

	public static function get_driver()
	{
		$driver = DATABASE_DRIVER;
		if ($driver == 'mysqlt') $driver = 'mysqli';
		return $driver;
	}
	/**
	 * Connect to database.
	 */
	public static function Connect() {
		if(isset(self::$ado)) { //return forced new adodb connection
			$new = NewADOConnection(self::get_driver());
			$new->autoRollback = true; // default is false 
			if(!@$new->NConnect(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME))
				throw new Exception("Connect to database failed");
		} else {
			self::$ado = NewADOConnection(self::get_driver());
			self::$ado->autoRollback = true; // default is false 
			if(!@self::$ado->Connect(DATABASE_HOST, DATABASE_USER, DATABASE_PASSWORD, DATABASE_NAME))
				throw new Exception("Connect to database failed");
			$new = self::$ado;
		}
		$new->fetchMode = ADODB_FETCH_BOTH;
        if (self::is_mysql()) {
			// For MySQL
    		$new->Execute('SET NAMES "utf8"');
		} elseif (self::is_postgresql()) {
			// For PostgreSQL
			@$new->Execute('SET bytea_output = "escape";');
		}
		return $new;
	}

	/**
	 * Destroy database connection.
	 */
	public static function Disconnect() {
		self::$ado->Close();
		self::$ado = null;
	}

    public static function is_postgresql()
    {
        $ret = stripos(DATABASE_DRIVER, 'postgre') !== false;
        return $ret;
    }

    public static function is_mysql()
    {
        $ret = stripos(DATABASE_DRIVER, 'mysql') !== false;
        return $ret;
    }
    
 	/**
	 * Statistics only. Get number of queries till now.
	 * @return integer
	 */
	public static function GetQueries() {
		return self::$queries;
	}

	public static function GetQueriesQty() {
		return self::$queries_qty;
	}
	
	public static function & dict() {
		static $dict;
		if(!isset($dict)) $dict = NewDataDictionary(self::$ado);
		return $dict;
	}
	
	public static function DropTable($name) {
		$dict = self::dict();
		$arr = $dict->DropTableSQL($name);
		$ret = $dict->ExecuteSQLArray($arr);
		return $ret==2;
	}
	
	public static function CreateTable($name, $cols, $opts=null) {
		$dict = self::dict();
		$def_opts = array('postgres'=>' WITH OIDS','mysql' => ' ENGINE=InnoDB DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci');
		$arr = $dict->CreateTableSQL($name,$cols,isset($opts)?array_merge($def_opts,$opts):$def_opts);
		if($arr===false) return false;
		$ret = $dict->ExecuteSQLArray($arr);
		if($ret != 2) trigger_error(print_r($arr, true).'\n'.self::ErrorMsg().'\n', E_USER_ERROR);
		return $ret==2;
	}
	
	public static function DropIndex($name,$tab=null) {
		$dict = self::dict();
		$arr = $dict->DropIndexSQL($name,$tab);
		$ret = $dict->ExecuteSQLArray($arr);
		return $ret==2;
	}
	
	public static function CreateIndex($name, $tab, $cols, $opts=null) {
		$dict = self::dict();
		$arr = $dict->CreateIndexSQL($name,$tab,$cols, $opts);
		if($arr===false) return false;
		$ret = $dict->ExecuteSQLArray($arr);
		if($ret != 2) trigger_error(print_r($arr, true).'\n'.self::ErrorMsg().'\n', E_USER_ERROR);
		return $ret==2;
	}
	


	public static function TypeControl($sql, & $arr) {
		$x = preg_split('/(%[%DTdsbf])/', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);

		if (isset($arr) && !is_array($arr)) {
			$arr = array($arr);
		} elseif (!isset($arr)) {
		    $arr = array();
		}
	
		$ret = '';
		$j=0;
		$arr_count = count($arr);
		foreach($x as $y) {
		    if ($y == '%%') {
		        $ret .= '%';
		        continue;
		    }
			if($arr_count<=$j) {
				$ret .= $y;
				continue;
			}
			switch ($y) {
				case '%d' :
					if (!is_null($arr[$j])) {
						if (!is_numeric($arr[$j]))
							trigger_error('Argument '.$j.' is not number('.$y.'): <ul><li>'.$sql.'</li><li>'.print_r($arr,true).'</li></ul>',E_USER_ERROR);
						$arr[$j] = (int)($arr[$j]);
					}
					$j++;
					$ret .= '?';
					break;
				case '%f' :
					if (!is_null($arr[$j])) {
						if (!is_numeric($arr[$j]))
							trigger_error('Argument '.$j.' is not number('.$y.'): <ul><li>'.$sql.'</li><li>'.print_r($arr,true).'</li></ul>',E_USER_ERROR);
						$arr[$j] = (float)($arr[$j]);
					}
					$j++;
					$ret .= '?';
					break;
				case '%s' :
					if (!is_null($arr[$j])) 
						$arr[$j] = (string)$arr[$j];
					$j++;
					$ret .= '?';
					break;
				case '%D' :
					$arr[$j] = self::BindDate($arr[$j]);
					$j++;
					$ret .= '?';
					break;
				case '%T' :
					$arr[$j] = self::BindTimeStamp($arr[$j]);
					$j++;
					$ret .= '?';
					break;
				case '%b' :
					$arr[$j] = (boolean) $arr[$j]?1:0;
					$j++;
					$ret .= '?';
					break;
				default:
					$ret .= $y;
			}
		}
		return $ret;
	}
	
	public static function ifelse($a,$b,$c) {
        if(self::is_postgresql())
    	    return '(CASE WHEN '.$a.' THEN '.$b.' ELSE '.$c.' END)';
	    return 'IF('.$a.','.$b.','.$c.')';
	}
	
    public static function call_with_retry($method,$args) {
        for($timeout_count=2;$timeout_count>=0;$timeout_count--) {
            try {
                $ret = call_user_func_array(array(self::$ado,$method), $args);
                break;
            } catch(DBRetryQueryException $ex) {
                if($timeout_count==0) throw new Exception('DB gone away');
            }
        }
        return $ret;
    }
	
	//ADODB mess.... someday we can replace it by __static_call (PHP6). Generated by ado.php helper
		

public static function Version() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Version"), $args);
return $ret;
}

/** Get server version info... @returns An array with 2 elements: $arr['string'] is the description string, and $arr[version] is the version (also a string). */
public static function ServerInfo() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"ServerInfo"), $args);
return $ret;
}


public static function Time() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Time"), $args);
return $ret;
}


public static function SQLDate( $fmt , $col = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"SQLDate"), $args);
return $ret;
}

/** * Should prepare the sql statement and return the stmt resource. * For databases that do not support this, we return the $sql. To ensure * compatibility with databases that do not support prepare: * * $stmt = $db->Prepare("insert into table (id, name) values (?,?)"); * $db->Execute($stmt,array(1,'Jill')) or die('insert failed'); * $db->Execute($stmt,array(2,'Joe')) or die('insert failed'); * * @param sql SQL to send to database * * @return return FALSE, or the prepared statement, or the original sql if * if the database does not support prepare. * */
public static function Prepare( $sql ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Prepare"), $args);
return $ret;
}

/** * Some databases, eg. mssql require a different function for preparing * stored procedures. So we cannot use Prepare(). * * Should prepare the stored procedure and return the stmt resource. * For databases that do not support this, we return the $sql. To ensure * compatibility with databases that do not support prepare: * * @param sql SQL to send to database * * @return return FALSE, or the prepared statement, or the original sql if * if the database does not support prepare. * */
public static function PrepareSP( $sql , $param = true ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"PrepareSP"), $args);
return $ret;
}

/** * PEAR DB Compat */
public static function Quote( $s ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Quote"), $args);
return $ret;
}

/** Requested by "Karsten Dambekalns" */
public static function QMagic( $s ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"QMagic"), $args);
return $ret;
}


public static function q( &$s ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"q"), $args);
return $ret;
}

/** * Lock a row, will escalate and lock the table if row locking not supported * will normally free the lock at the end of the transaction * * @param $table name of table to lock * @param $where where clause to use, eg: "WHERE row=12". If left empty, will escalate to table lock */
public static function RowLock( $table , $where ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"RowLock"), $args);
return $ret;
}


public static function CommitLock( $table ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"CommitLock"), $args);
return $ret;
}


public static function RollbackLock( $table ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"RollbackLock"), $args);
return $ret;
}


public static function Param( $name , $type = 'C' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Param"), $args);
return $ret;
}


public static function InParameter( &$stmt , &$var , $name , $maxLen = 4000 , $type = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"InParameter"), $args);
return $ret;
}


public static function OutParameter( &$stmt , &$var , $name , $maxLen = 4000 , $type = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"OutParameter"), $args);
return $ret;
}


public static function Parameter( &$stmt , &$var , $name , $isOutput = false , $maxLen = 4000 , $type = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Parameter"), $args);
return $ret;
}


public static function IgnoreErrors( $saveErrs = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"IgnoreErrors"), $args);
return $ret;
}

/** Improved method of initiating a transaction. Used together with CompleteTrans(). Advantages include: a. StartTrans/CompleteTrans is nestable, unlike BeginTrans/CommitTrans/RollbackTrans. Only the outermost block is treated as a transaction.
b. CompleteTrans auto-detects SQL errors, and will rollback on errors, commit otherwise.
c. All BeginTrans/CommitTrans/RollbackTrans inside a StartTrans/CompleteTrans block are disabled, making it backward compatible. */
public static function StartTrans( $errfn = 'ADODB_TransMonitor' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"StartTrans"), $args);
return $ret;
}

/** Used together with StartTrans() to end a transaction. Monitors connection for sql errors, and will commit or rollback as appropriate. @autoComplete if true, monitor sql errors and commit and rollback as appropriate, and if set to false force rollback even if no SQL error detected. @returns true on commit, false on rollback. */
public static function CompleteTrans( $autoComplete = true ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"CompleteTrans"), $args);
return $ret;
}


public static function FailTrans() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"FailTrans"), $args);
return $ret;
}

/** Check if transaction has failed, only for Smart Transactions. */
public static function HasFailedTrans() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"HasFailedTrans"), $args);
return $ret;
}

/** * Execute SQL * * @param sql SQL statement to execute, or possibly an array holding prepared statement ($sql[0] will hold sql text) * @param [inputarr] holds the input data to bind to. Null elements will be set to null. * @return RecordSet or false */
public static function &Execute( $sql , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("Execute",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"Execute", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &CreateSequence( $seqname = 'adodbseq' , $startID = 1 ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"CreateSequence"), $args);
return $ret;
}


public static function &DropSequence( $seqname = 'adodbseq' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"DropSequence"), $args);
return $ret;
}

/** * Generates a sequence id and stores it in $this->genID; * GenID is only available if $this->hasGenID = true; * * @param seqname name of sequence to use * @param startID if sequence does not exist, start at this ID * @return 0 if not supported, otherwise a sequence id */
public static function &GenID( $seqname = 'adodbseq' , $startID = 1 ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"GenID"), $args);
return $ret;
}

/** * @param $table string name of the table, not needed by all databases (eg. mysql), default '' * @param $column string name of the column, not needed by all databases (eg. mysql), default '' * @return the last inserted ID. Not all databases support this. */
public static function &Insert_ID( $table = '' , $column = '' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Insert_ID"), $args);
return $ret;
}

/** * Portable Insert ID. Pablo Roca * * @return the last inserted ID. All databases support this. But aware possible * problems in multiuser environments. Heavy test this before deploying. */
public static function &PO_Insert_ID( $table = '' , $id = '' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"PO_Insert_ID"), $args);
return $ret;
}

/** * @return # rows affected by UPDATE/DELETE */
public static function &Affected_Rows() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Affected_Rows"), $args);
return $ret;
}

/** * @return the last error message */
public static function &ErrorMsg() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"ErrorMsg"), $args);
return $ret;
}

/** * @return the last error number. Normally 0 means no error. */
public static function &ErrorNo() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"ErrorNo"), $args);
return $ret;
}


public static function &MetaError( $err = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaError"), $args);
return $ret;
}


public static function &MetaErrorMsg( $errno ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaErrorMsg"), $args);
return $ret;
}

/** * @returns an array with the primary key columns in it. */
public static function &MetaPrimaryKeys( $table , $owner = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaPrimaryKeys"), $args);
return $ret;
}

/** * @returns assoc array where keys are tables, and values are foreign keys */
public static function &MetaForeignKeys( $table , $owner = false , $upper = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaForeignKeys"), $args);
return $ret;
}

/** * Will select, getting rows from $offset (1-based), for $nrows. * This simulates the MySQL "select * from table limit $offset,$nrows" , and * the PostgreSQL "select * from table limit $nrows offset $offset". Note that * MySQL and PostgreSQL parameter ordering is the opposite of the other. * eg. * SelectLimit('select * from table',3); will return rows 1 to 3 (1-based) * SelectLimit('select * from table',3,2); will return rows 3 to 5 (1-based) * * Uses SELECT TOP for Microsoft databases (when $this->hasTop is set) * BUG: Currently SelectLimit fails with $sql with LIMIT or TOP clause already set * * @param sql * @param [offset] is the row to start calculations from (1-based) * @param [nrows] is the number of rows to get * @param [inputarr] array of bind variables * @param [secs2cache] is a private parameter only used by jlim * @return the recordset ($rs->databaseType == 'array') */
public static function &SelectLimit( $sql , $nrows = -1 , $offset = -1 , $inputarr = false , $secs2cache = 0 ) {
$args = func_get_args();
if (!isset($args[1])) $args[1] = $nrows;
if (!isset($args[2])) $args[2] = $offset;
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[3]);
$ret = self::call_with_retry("SelectLimit",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"SelectLimit", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

/** * Create serializable recordset. Breaks rs link to connection. * * @param rs the recordset to serialize */
public static function &SerializableRS( &$rs ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"SerializableRS"), $args);
return $ret;
}


public static function &GetAll( $sql , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("GetAll",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"GetAll", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &GetAssoc( $sql , $inputarr = false , $force_array = false , $first2cols = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("GetAssoc",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"GetAssoc", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &CacheGetAssoc( $secs2cache , $sql = false , $inputarr = false , $force_array = false , $first2cols = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[2]);
$ret = self::call_with_retry("CacheGetAssoc",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheGetAssoc", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

/** * Return first element of first row of sql statement. Recordset is disposed * for you. * * @param sql SQL statement * @param [inputarr] input bind array */
public static function &GetOne( $sql , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("GetOne",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"GetOne", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &CacheGetOne( $secs2cache , $sql = false , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[2]);
$ret = self::call_with_retry("CacheGetOne",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheGetOne", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &GetCol( $sql , $inputarr = false , $trim = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("GetCol",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"GetCol", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &CacheGetCol( $secs , $sql = false , $inputarr = false , $trim = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[2]);
$ret = self::call_with_retry("CacheGetCol",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheGetCol", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &Transpose( &$rs , $addfieldnames = true ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Transpose"), $args);
return $ret;
}


public static function &OffsetDate( $dayFraction , $date = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"OffsetDate"), $args);
return $ret;
}

/** * * @param sql SQL statement * @param [inputarr] input bind array */
public static function &GetArray( $sql , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("GetArray",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"GetArray", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &CacheGetAll( $secs2cache , $sql = false , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[2]);
$ret = self::call_with_retry("CacheGetAll",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheGetAll", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &CacheGetArray( $secs2cache , $sql = false , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[2]);
$ret = self::call_with_retry("CacheGetArray",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheGetArray", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

/** * Return one row of sql statement. Recordset is disposed for you. * * @param sql SQL statement * @param [inputarr] input bind array */
public static function &GetRow( $sql , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("GetRow",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"GetRow", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &CacheGetRow( $secs2cache , $sql = false , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[2]);
$ret = self::call_with_retry("CacheGetRow",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheGetRow", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

/** * Insert or replace a single record. Note: this is not the same as MySQL's replace. * ADOdb's Replace() uses update-insert semantics, not insert-delete-duplicates of MySQL. * Also note that no table locking is done currently, so it is possible that the * record be inserted twice by two programs... * * $this->Replace('products', array('prodname' =>"'Nails'","price" => 3.99), 'prodname'); * * $table table name * $fieldArray associative array of data (you must quote strings yourself). * $keyCol the primary key field name or if compound key, array of field names * autoQuote set to true to use a hueristic to quote strings. Works with nulls and numbers * but does not work with dates nor SQL functions. * has_autoinc the primary key is an auto-inc field, so skip in insert. * * Currently blob replace not supported * * returns 0 = fail, 1 = update, 2 = insert */
public static function &Replace( $table , $fieldArray , $keyCol , $autoQuote = false , $has_autoinc = false ) {
$args = func_get_args();
$ret = self::call_with_retry("Replace",$args);
return $ret;
}

/** * Will select, getting rows from $offset (1-based), for $nrows. * This simulates the MySQL "select * from table limit $offset,$nrows" , and * the PostgreSQL "select * from table limit $nrows offset $offset". Note that * MySQL and PostgreSQL parameter ordering is the opposite of the other. * eg. * CacheSelectLimit(15,'select * from table',3); will return rows 1 to 3 (1-based) * CacheSelectLimit(15,'select * from table',3,2); will return rows 3 to 5 (1-based) * * BUG: Currently CacheSelectLimit fails with $sql with LIMIT or TOP clause already set * * @param [secs2cache] seconds to cache data, set to 0 to force query. This is optional * @param sql * @param [offset] is the row to start calculations from (1-based) * @param [nrows] is the number of rows to get * @param [inputarr] array of bind variables * @return the recordset ($rs->databaseType == 'array') */
public static function &CacheSelectLimit( $secs2cache , $sql , $nrows = -1 , $offset = -1 , $inputarr = false ) {
$args = func_get_args();
if(!isset($args[2])) $args[2] = $nrows;
if(!isset($args[3])) $args[3] = $offset;
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[4]);
$ret = self::call_with_retry("CacheSelectLimit",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheSelectLimit", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

/** * Flush cached recordsets that match a particular $sql statement. * If $sql == false, then we purge all files in the cache. */
public static function &CacheFlush( $sql = false , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("CacheFlush",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheFlush", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &xCacheFlush( $sql = false , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[1]);
$ret = self::call_with_retry("xCacheFlush",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"xCacheFlush", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

/** * Execute SQL, caching recordsets. * * @param [secs2cache] seconds to cache data, set to 0 to force query. * This is an optional parameter. * @param sql SQL statement to execute * @param [inputarr] holds the input data to bind to * @return RecordSet or false */
public static function &CacheExecute( $secs2cache , $sql = false , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[2]);
$ret = self::call_with_retry("CacheExecute",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CacheExecute", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}


public static function &AutoExecute( $table , $fields_values , $mode = 'INSERT' , $where = false , $forceUpdate = true , $magicq = false ) {
$args = func_get_args();
$ret = self::call_with_retry("AutoExecute",$args);
return $ret;
}

/** * Generates an Update Query based on an existing recordset. * $arrFields is an associative array of fields with the value * that should be assigned. * * Note: This function should only be used on a recordset * that is run against a single table and sql should only * be a simple select stmt with no groupby/orderby/limit * * "Jonathan Younger" */
public static function &GetUpdateSQL( &$rs , $arrFields , $forceUpdate = false , $magicq = false , $force = NULL ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"GetUpdateSQL"), $args);
return $ret;
}

/** * Generates an Insert Query based on an existing recordset. * $arrFields is an associative array of fields with the value * that should be assigned. * * Note: This function should only be used on a recordset * that is run against a single table. */
public static function &GetInsertSQL( &$rs , $arrFields , $magicq = false , $force = NULL ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"GetInsertSQL"), $args);
return $ret;
}

/** * Update a blob column, given a where clause. There are more sophisticated * blob handling functions that we could have implemented, but all require * a very complex API. Instead we have chosen something that is extremely * simple to understand and use. * * Note: $blobtype supports 'BLOB' and 'CLOB', default is BLOB of course. * * Usage to update a $blobvalue which has a primary key blob_id=1 into a * field blobtable.blobcolumn: * * UpdateBlob('blobtable', 'blobcolumn', $blobvalue, 'blob_id=1'); * * Insert example: * * $conn->Execute('INSERT INTO blobtable (id, blobcol) VALUES (1, null)'); * $conn->UpdateBlob('blobtable','blobcol',$blob,'id=1'); */
public static function &UpdateBlob( $table , $column , $val , $where , $blobtype = 'BLOB' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"UpdateBlob"), $args);
return $ret;
}

/** * Usage: * UpdateBlob('TABLE', 'COLUMN', '/path/to/file', 'ID=1'); * * $blobtype supports 'BLOB' and 'CLOB' * * $conn->Execute('INSERT INTO blobtable (id, blobcol) VALUES (1, null)'); * $conn->UpdateBlob('blobtable','blobcol',$blobpath,'id=1'); */
public static function &UpdateBlobFile( $table , $column , $path , $where , $blobtype = 'BLOB' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"UpdateBlobFile"), $args);
return $ret;
}


public static function &BlobDecode( $blob ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"BlobDecode"), $args);
return $ret;
}


public static function &BlobEncode( $blob ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"BlobEncode"), $args);
return $ret;
}


public static function &SetCharSet( $charset ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"SetCharSet"), $args);
return $ret;
}


public static function &IfNull( $field , $ifNull ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"IfNull"), $args);
return $ret;
}


public static function &LogSQL( $enable = true ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"LogSQL"), $args);
return $ret;
}


public static function &GetCharSet() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"GetCharSet"), $args);
return $ret;
}

/** * Usage: * UpdateClob('TABLE', 'COLUMN', $var, 'ID=1', 'CLOB'); * * $conn->Execute('INSERT INTO clobtable (id, clobcol) VALUES (1, null)'); * $conn->UpdateClob('clobtable','clobcol',$clob,'id=1'); */
public static function &UpdateClob( $table , $column , $val , $where ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"UpdateClob"), $args);
return $ret;
}


public static function &MetaType( $t , $len = -1 , $fieldobj = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaType"), $args);
return $ret;
}

public static function RenameColumn( $tabname , $oldcolumn , $newcolumn , $flds ) {
$qrs = DB::dict()->RenameColumnSQL($tabname, $oldcolumn, $newcolumn, $newcolumn.' '.$flds);
foreach($qrs as $q)
	DB::Execute($q);
return true;
}

/** * Change the SQL connection locale to a specified locale. * This is used to get the date formats written depending on the client locale. */
public static function &SetDateLocale( $locale = 'En' ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"SetDateLocale"), $args);
return $ret;
}


public static function &GetActiveRecordsClass( $class , $table , $whereOrderBy = false , $bindarr = false , $primkeyArr = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"GetActiveRecordsClass"), $args);
return $ret;
}


public static function &GetActiveRecords( $table , $where = false , $bindarr = false , $primkeyArr = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"GetActiveRecords"), $args);
return $ret;
}

/** * Close Connection */
public static function &Close() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Close"), $args);
return $ret;
}

/** * Begin a Transaction. Must be followed by CommitTrans() or RollbackTrans(). * * @return true if succeeded or false if database does not support transactions */
public static function &BeginTrans() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"BeginTrans"), $args);
return $ret;
}


public static function &SetTransactionMode( $transaction_mode ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"SetTransactionMode"), $args);
return $ret;
}


public static function &MetaTransaction( $mode , $db ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaTransaction"), $args);
return $ret;
}

/** * If database does not support transactions, always return true as data always commited * * @param $ok set to false to rollback transaction, true to commit * * @return true/false. */
public static function &CommitTrans( $ok = true ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"CommitTrans"), $args);
return $ret;
}

/** * If database does not support transactions, rollbacks always fail, so return false * * @return true/false. */
public static function &RollbackTrans() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"RollbackTrans"), $args);
return $ret;
}

/** * return the databases that the driver can connect to. * Some databases will return an empty array. * * @return an array of database names. */
public static function &MetaDatabases() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaDatabases"), $args);
return $ret;
}

/** * @param ttype can either be 'VIEW' or 'TABLE' or false. * If false, both views and tables are returned. * "VIEW" returns only views * "TABLE" returns only tables * @param showSchema returns the schema/user with the table name, eg. USER.TABLE * @param mask is the input mask - only supported by oci8 and postgresql * * @return array of tables for current database. */
public static function &MetaTables( $ttype = false , $showSchema = false , $mask = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaTables"), $args);
return $ret;
}

/** * List columns in a database as an array of ADOFieldObjects. * See top of file for definition of object. * * @param $table table name to query * @param $normalize makes table name case-insensitive (required by some databases) * @schema is optional database schema to use - not supported by all databases. * * @return array of ADOFieldObjects for current table. */
public static function &MetaColumns( $table , $normalize = true ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaColumns"), $args);
return $ret;
}

/** * List indexes on a table as an array. * @param table table name to query * @param primary true to only show primary keys. Not actually used for most databases * * @return array of indexes on current table. Each element represents an index, and is itself an associative array. Array ( [name_of_index] => Array ( [unique] => true or false [columns] => Array ( [0] => firstname [1] => lastname ) ) */
public static function &MetaIndexes( $table , $primary = false , $owner = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaIndexes"), $args);
return $ret;
}

/** * List columns names in a table as an array. * @param table table name to query * * @return array of column names for current table. */
public static function &MetaColumnNames( $table , $numIndexes = false , $useattnum = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"MetaColumnNames"), $args);
return $ret;
}

/** * Different SQL databases used different methods to combine strings together. * This function provides a wrapper. * * param s variable number of string parameters * * Usage: $db->Concat($str1,$str2); * * @return concatenated string */
public static function &Concat() {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"Concat"), $args);
return $ret;
}

/** * Converts a date "d" to a string that the database can understand. * * @param d a date in Unix date time format. * * @return date string in database date format */
public static function &DBDate( $d ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"DBDate"), $args);
return $ret;
}


public static function &BindDate( $d ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"BindDate"), $args);
return $ret;
}


public static function &BindTimeStamp( $d ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"BindTimeStamp"), $args);
return $ret;
}

/** * Converts a timestamp "ts" to a string that the database can understand. * * @param ts a timestamp in Unix date time format. * * @return timestamp string in database timestamp format */
public static function &DBTimeStamp( $ts ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"DBTimeStamp"), $args);
return $ret;
}

/** * Also in ADORecordSet. * @param $v is a date string in YYYY-MM-DD format * * @return date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format */
public static function &UnixDate( $v ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"UnixDate"), $args);
return $ret;
}

/** * Also in ADORecordSet. * @param $v is a timestamp string in YYYY-MM-DD HH-NN-SS format * * @return date in unix timestamp format, or 0 if before TIMESTAMP_FIRST_YEAR, or false if invalid date format */
public static function &UnixTimeStamp( $v ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"UnixTimeStamp"), $args);
return $ret;
}

/** * Also in ADORecordSet. * * Format database date based on user defined format. * * @param v is the character date in YYYY-MM-DD format, returned by database * @param fmt is the format to apply to it, using date() * * @return a date formated as user desires */
public static function &UserDate( $v , $fmt = 'Y-m-d' , $gmt = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"UserDate"), $args);
return $ret;
}

/** * * @param v is the character timestamp in YYYY-MM-DD hh:mm:ss format * @param fmt is the format to apply to it, using date() * * @return a timestamp formated as user desires */
public static function &UserTimeStamp( $v , $fmt = 'Y-m-d H:i:s' , $gmt = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"UserTimeStamp"), $args);
return $ret;
}


public static function &escape( $s , $magic_quotes = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"escape"), $args);
return $ret;
}

/** * Quotes a string, without prefixing nor appending quotes. */
public static function &addq( $s , $magic_quotes = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"addq"), $args);
return $ret;
}

/** * Correctly quotes a string so that all strings are escaped. We prefix and append * to the string single-quotes. * An example is $db->qstr("Don't bother",magic_quotes_runtime()); * * @param s the string to quote * @param [magic_quotes] if $s is GET/POST var, set to get_magic_quotes_gpc(). * This undoes the stupidity of magic quotes for GPC. * * @return quoted string to be sent back to database */
public static function &qstr( $s , $magic_quotes = false ) {
$args = func_get_args();
$ret = call_user_func_array(array(self::$ado,"qstr"), $args);
return $ret;
}

/** * Will select the supplied $page number from a recordset, given that it is paginated in pages of * $nrows rows per page. It also saves two boolean values saying if the given page is the first * and/or last one of the recordset. Added by Iván Oliva to provide recordset pagination. * * See readme.htm#ex8 for an example of usage. * * @param sql * @param nrows is the number of rows per page to get * @param page is the page number to get (1-based) * @param [inputarr] array of bind variables * @param [secs2cache] is a private parameter only used by jlim * @return the recordset ($rs->databaseType == 'array') * * NOTE: phpLens uses a different algorithm and does not use PageExecute(). * */
public static function &PageExecute( $sql , $nrows , $page , $inputarr = false , $secs2cache = 0 ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[0] = self::TypeControl($sql,$args[3]);
$ret = self::call_with_retry("PageExecute",$args);
self::$queries_qty++;
if(SQL_TIMES)self::$queries[] = array("func"=>"PageExecute", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

/** * Will select the supplied $page number from a recordset, given that it is paginated in pages of * $nrows rows per page. It also saves two boolean values saying if the given page is the first * and/or last one of the recordset. Added by Iván Oliva to provide recordset pagination. * * @param secs2cache seconds to cache data, set to 0 to force query * @param sql * @param nrows is the number of rows per page to get * @param page is the page number to get (1-based) * @param [inputarr] array of bind variables * @return the recordset ($rs->databaseType == 'array') */
public static function &CachePageExecute( $secs2cache , $sql , $nrows , $page , $inputarr = false ) {
$args = func_get_args();
if(SQL_TIMES) $time = microtime(true);
$args[1] = self::TypeControl($sql,$args[4]);
$ret = self::call_with_retry("CachePageExecute",$args);
if(SQL_TIMES)self::$queries[] = array("func"=>"CachePageExecute", "args"=>$args, "time"=>microtime(true)-$time, "caller"=>get_function_caller());
return $ret;
}

	public static function like() {
		static $like = null;
		if ($like===null) {
            if(!DB::is_postgresql()) $like = 'LIKE';
			else $like = 'ILIKE';
		}
		return $like;
	}


}

if (version_compare(PHP_VERSION, "5.3") == -1)
    @set_magic_quotes_runtime(false); // DEPRECATED since php 5.3
DB::connect();

class DBRetryQueryException extends Exception {}

if (class_exists('ErrorHandler', false)) {
	class DBErrorObserver extends ErrorObserver
	{
		public function update_observer($type, $message, $errfile, $errline, $errcontext, $backtrace)
		{
			if (DB::is_mysql() && preg_match('/mysql.+\[2006\:/', $message) || preg_match('/server closed the connection/', $message)) {
				try {
					DB::$ado = DB::Connect();
					throw new DBRetryQueryException();
					return false;
				} catch (Exeption $e) {
					return true;
				}
			}
			return true;
		}
	}
	$err = new DBErrorObserver();
	ErrorHandler::add_observer($err);
}
