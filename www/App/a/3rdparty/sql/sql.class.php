<?php

//switch($GLOBALS['gDbh']->getAttribute(PDO::ATTR_DRIVER_NAME))
//{
//	case 'mysql':
//		define('INSERT_REDEF',  23000); //插入重复数据的错误，数据库中有很多只能有唯一值，但插入时一般不判断就直接插入由数据库自己保证
//		define('TABLE_EXISTS',  '42S01'); //42S01--表已经存在
//		define('INDEX_EXISTS',  '42000'); //42P07--索引已经存在，和表存在没区别
//		break;
//	case 'pgsql':
//		define('INSERT_REDEF',  23505);//插入重复数据的错误，数据库中有很多只能有唯一值，但插入时一般不判断就直接插入由数据库自己保证
//		define('TABLE_EXISTS',  '42P07'); //42P07--表已经存在
//		define('INDEX_EXISTS',  '42P07'); //42P07--索引已经存在，和表存在没区别
//		break;
//	case 'sqlsrv':
//		define('INSERT_REDEF', 23000);//插入重复数据的错误，数据库中有很多只能有唯一值，但插入时一般不判断就直接插入由数据库自己保证
//		define('TABLE_EXISTS', '42S01'); //42S01--表已经存在
//		define('INDEX_EXISTS', '42S11'); //42P07--索引已经存在
//		break;
//	default:
//		break;	
//}

$GLOBALS['transNum'] = 0;
//多表联合查询的问题要解决。在App\dataModel\class.task.inc.php文件的getCurTaskInfoByUser函数需要多表联合查询.这种情况也会越来越多
class SQL {

    private   $dbh;
    private   $sqlError;
    private   $errorCode;
    private   $mytransNum;
    private   $stmt;
    private   $dbDriver;
    function __construct($dbHandle=NULL)  
    {
        if ( NULL != $dbHandle )
        {
            $this->dbh      =  $dbHandle;
            $this->dbDriver = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME); 
        }
        //$this->transNum  = &$GLOBALS['transNum'];
        $this->mytransNum  = 0;
        $this->sqlError  = 0;
        $this->errorCode = 0;
        $this->stmt      = NULL;
    }
    function __destruct() 
    {
        $this->dbh = NULL;
        if ( $this->mytransNum != 0 )
        {
            except('error! $this->mytransNum != 0');
        }
    }

    function getDBH()
    {
        return $this->dbh;
    }

    function setDBH($dbh)
    {
        $this->dbh = $dbh;
        $this->dbDriver = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME); 
        return $this->dbh;
    }  

    function connect($dsn, $user, $password) 
    {
        try {
            $this->dbh = new PDO($dsn, $user, $password);
        } catch (PDOException $e) {
            $this->sqlError  = $e->getMessage();
            $this->errorCode = $e->getCode();
            echo 'Connection database failed: ' . $e->getMessage();
            die();
        }
        $this->dbh->setAttribute(PDO::ATTR_CASE, PDO::CASE_UPPER);
        //设置PDO的错误处理方式为异常
        $this->dbh->setAttribute ( PDO::ATTR_ERRMODE , PDO::ERRMODE_EXCEPTION );

        $this->dbDriver = $this->dbh->getAttribute(PDO::ATTR_DRIVER_NAME); 

        return true;
    }
    
    function reconnect()
    {
        if ($this->isCloud) {
            $GLOBALS['cloudDBH'] = NULL;
            $this->setDBH(getCloudDBH());
        } else {
            $GLOBALS['AppDBH'] = NULL;
            $this->setDBH(getAppDBH());
        }
    }

    function isCloudTB(&$tb)
    {
        if ( 'hic_' == substr($tb, 0, 4) )
        {
            return true;
        }
        return false;
    }

    function getCloudTB(&$tb)
    {
        //本地环境无需处理
        if ( DSTP_CLU == CLU_LOCAL )
        {
            return $tb;
        }

        //云环境把表名加前缀。但cloud_开头的公共管理表不处理
        if ( $this->isCloudTB($tb) )
        {
            return $tb;
        }

        // 表前缀需要保存在全局变量中，如果没有，则退出
        if (!isset($GLOBALS['SYSDB']['DBPRE'])) 
        {
            die('dbpre fail');
        }
        return $GLOBALS['SYSDB']['DBPRE'].'_'.$tb;
    }

    //建表时替换SQL
    private function strip_sql(&$sql)
    {
        //如果不是建表或者建索引的，无需处理。如果不是云环境，也无需处理表名
        if ( DSTP_CLU == CLU_CLOUD )
        {
            if ( 0 != preg_match ('/CREATE TABLE `(.*)`(.*)/' , $sql,$match ) )
            {
                $newName = $this->getCloudTB($match[1]);
                $sql     = str_replace ($match[1], $newName, $sql);

                // 在云环境中，如果不是cloud开头的，每个表都要添加一个字段CLOUDID区分属于哪个用户的。
                // 该字段建立索引。CLOUD开头的表，该字段无用，填写值为0		
                // 在建表内容的末尾插入如下语句
                // ,`CLOUDID`	INT NOT NULL DEFAULT 0,INDEX(`CLOUDID`)

                //CREATE TABLE `asm2attend` (
                //`ARID`	      INT NOT NULL, 
                //`UID`	      INT NOT NULL  
                //);
                if ( ';' != substr($sql, -1, 1) )
                {
                    $sql .= ';'; //过来的SQL语句，已经被剔掉了;号
                }
                //ENGINE=InnoDB DEFAULT CHARSET=utf8	
                switch($this->dbDriver)
                {
                case 'mysql':
                    if ( ENV_SAE == DSTP_ENV ) //SAE不支持InnoDB
                    {
                        $sql = str_replace (');', ',`CLOUDID`	INT NOT NULL DEFAULT 0,INDEX(`CLOUDID`))  DEFAULT CHARSET=utf8 COLLATE utf8_general_ci', $sql);
                    }
                    else
                    {
                        $sql = str_replace (');', ',`CLOUDID`	INT NOT NULL DEFAULT 0,INDEX(`CLOUDID`))  ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_general_ci', $sql);
                    }

                    break;
                case 'sqlite':
                    preg_match ('/CREATE TABLE `(.*)`(.*)/' , $sql,$match );
                    $sql = str_replace ($match[0], $match[0].'`CLOUDID`	INT NOT NULL DEFAULT 0,', $sql);
                    break;
                default://pgsql无法在建表语句里建立索引。需要在exec后再自己建立建索引语句建立
                    $sql = str_replace ('` (', '` (`CLOUDID`	INT NOT NULL DEFAULT 0,', $sql);
                    break;
                }

                //所有的UNIQUE都要加上CLOUDID字段共同构成	
                //UNIQUE (`NAME` )=>UNIQUE (`CLOUDID`,`NAME` )	
                $sql = str_replace ('UNIQUE  (', 'UNIQUE(', $sql);
                $sql = str_replace ('UNIQUE (', 'UNIQUE(', $sql);
                $sql = str_replace ('UNIQUE(', 'UNIQUE(`CLOUDID`,', $sql);
            }
            elseif( 0 != preg_match ('/CREATE INDEX (.*) ON (.*) \((.*)/' , $sql,$match2 ) )
            {
                $newName = $this->getCloudTB($match2[2]);
                $sql = str_replace ($match2[2], $newName, $sql);
            }
            else
            {

            }
        }

        switch($this->dbDriver)
        {
        case 'pgsql':
            $sql = str_ireplace('AUTO_INCREMENT', 'serial',  $sql);
            $sql = str_ireplace('`', '',  $sql);
            $sql = str_ireplace('VARBINARY', 'VARCHAR',  $sql);
            $sql = str_ireplace('BINARY', '',  $sql);
            $sql = str_ireplace('UINT', 'INT',  $sql);
            // pgsql下，text的性能和varchar一样，所以直接把所有varchar替换为text尽量避免输入太长引起错误
            $pattern = "/varchar(\s*)\((\s*)(\d+)(\s*)\)/i";
            $sql = preg_replace($pattern, 'TEXT', $sql);
            break;
        case 'mysql':
            $sql = str_ireplace('AUTO_INCREMENT', 'INT NOT NULL AUTO_INCREMENT',  $sql);
            $sql = str_ireplace('VARCHAR(4000)',  'TEXT',  $sql);
            $sql = str_ireplace('VARBINARY', 'VARCHAR',  $sql);
            $sql = str_ireplace('UINT', 'INT UNSIGNED',  $sql);
            $sql = str_ireplace('"', '`',  $sql);
            break;
        case 'sqlsrv':
            $sql = str_ireplace('AUTO_INCREMENT', 'Int identity (1,1)',  $sql);
            $sql = str_ireplace('`', '',  $sql);
            $sql = str_ireplace('VARCHAR', 'NVARCHAR',  $sql);
            $sql = str_ireplace('VARBINARY', 'VARCHAR',  $sql);
            $sql = str_ireplace('BINARY', '',  $sql);
            $sql = str_ireplace('UINT', 'INT',  $sql);
            $sql = str_ireplace(' text', ' ntext',  $sql);//有的字段名中就含有text,所以这儿前面要加个空格判断.不允许text当做开头
            $sql = str_ireplace("\ttext", ' ntext',  $sql);
            break;
        case 'sqlite':
            $sql = str_ireplace('PRIMARY KEY', 'UNIQUE',  $sql);
            $sql = str_ireplace('AUTO_INCREMENT', 'INTEGER PRIMARY KEY AUTOINCREMENT',  $sql);
            $sql = str_ireplace('VARBINARY', 'VARCHAR',  $sql);
            $sql = str_ireplace('UINT', 'INT',  $sql);
            $sql = str_ireplace('"', '`',  $sql);
            break;
        default:
            break;	
        }

        return $sql;
    }

    function exec($sql,$wArr=array()) 
    {
        $sql = $this->strip_sql($sql);
        try
        {
            if( ($GLOBALS['transNum'] > 0) && ('pgsql' == $this->dbDriver) )
            {
                if ( FALSE === $this->dbh->exec('SAVEPOINT exec_savepoint'))
                {
                    die('save faile');
                }
            }
            if ( NULL == $wArr && 'mysql' == $this->dbDriver )
            {
                //mysql建表时如果有中文注释不知为什么老出错 errCode:SQLSTATE[HY093]: Invalid parameter number: no parameters were bound
                $result = $this->dbh->exec($sql) ;  
            }
            else
            {
                $stmt = $this->dbh->prepare($sql);
                $stmt->execute($wArr);
                $this->stmt = NULL;
            }
        }
        catch(PDOException $e)
        {

            switch($this->dbDriver)
            {
            case 'mysql':
                $TABLE_EXISTS = '42S01';
                $INDEX_EXISTS = '42000';
                break;
            case 'pgsql':
                $TABLE_EXISTS = '42P07';
                $INDEX_EXISTS = '42P07';
                break;
            case 'sqlsrv':
                $TABLE_EXISTS = '42S01';
                $INDEX_EXISTS = '42S11';
                break;
            case 'sqlite':
                //$TABLE_EXISTS = 'HY000';
                //$INDEX_EXISTS = 'HY000';
                break;
            default:
                $TABLE_EXISTS = '';
                $INDEX_EXISTS = '';
                break;	
            }

            //自定义创建表，因为pgsql没有 IF NOT EXISTS语句，所有暂时没有对表是否存在进行处理
            //表已经存在的错误代码分别是：mysql:42S01  pgsql:42P07
            if ( $TABLE_EXISTS == $e->getCode() || $INDEX_EXISTS == $e->getCode() )
            {
                if( ($GLOBALS['transNum'] > 0) && ('pgsql' == $this->dbDriver) )
                {
                    if ( FALSE === $this->dbh->exec('ROLLBACK TO SAVEPOINT exec_savepoint'))
                    {
                        die('rollback fail');
                    }
                    //$this->dbh->exec('begin');
                }
                $this->errorCode = $e->getCode();
                return true;
            }
            $res = $this->exceptHandle($e, $sql, $wArr);
            if (false == $res) {
                return false;
            }
        }

        if ('b' == HIC_LOCAL )
        {
            //hic中，大量的写设备状态，实际是无需同步保存到flash中的。
            //为了减少flash的读写次数，除非必要，不做保存标记
            file_put_contents('/tmp/dbchange','');
        }

        if ( DSTP_CLU == CLU_CLOUD )
        {
            //如果是建表语句，则增加索引
        }
        return true;  
    }

    //开始一个事务
    function beginTransaction()
    {
        return; //多个表同时使用事务时。现在代码有问题会死锁。先取消
    }
    function beginTransaction1()
    {
        $GLOBALS['transNum'] = intval($GLOBALS['transNum'])+1;
        $this->mytransNum++;
        if ( $GLOBALS['transNum'] > 1 )
        {
            //事务不能嵌套，最外层有效，其它内层事务直接忽略不设
            return;
        }
        try
        {
            $this->dbh->beginTransaction();
        }
        catch(PDOException $e)
        {
            //$this->exceptHandle($e, "transNum is:".$GLOBALS['transNum']);
        }
        return;
    }


    //提交事务
    function commit()
    {
        return; //多个表同时使用事务时。现在代码有问题会死锁。先取消
    }
    function commit1()
    {
        $GLOBALS['transNum'] = intval($GLOBALS['transNum'])-1;
        $this->mytransNum--;
        if ( $GLOBALS['transNum'] != 0 )
        {
            //事务不能嵌套，最外层有效，其它内层事务直接忽略不设
            return;
        }
        try
        {
            $this->dbh->commit();
        }
        catch(PDOException $e)
        {
            //这儿应该判断错误码，如果是不支持事务，就直接返回，不用打印。默认就当牺牲事务功能
            //$this->exceptHandle($e, "transNum is:".$GLOBALS['transNum']);
        }
        return;

    }

    //事务回滚
    function rollBack()
    {
        return; //多个表同时使用事务时。现在代码有问题会死锁。先取消
    }
    function rollBack1()
    {
        $GLOBALS['transNum'] = intval($GLOBALS['transNum'])-1;
        $this->mytransNum--;
        if ( $GLOBALS['transNum'] != 0 )
        {
            //事务不能嵌套，最外层有效，其它内层事务直接忽略不设
            return;
        }
        try
        {
            $this->dbh->rollBack();
        }
        catch(PDOException $e)
        {
            //不支持事务，直接返回
            //echo 'rollBack fail : '.$e->getMessage().'<br>';
            $this->sqlError = $e->getMessage();
        }
        return;
    }

    function getSqlError()
    {
        return 	$this->sqlError;
    }

    function getErrCode()
    {
        return $this->errorCode;
    }

    ///////////////////////////以下接口不对外公开//////////////////////////
    private function exceptHandle($e,$sql,$params)
    {
        $this->sqlError = $e->getMessage();
        $this->errorCode = $e->errorInfo[1];

        if (DSTP_DEBUG) {
            debug(date('y-m-d H:i').' sql failed: '.$sql);
            debug('errCode:'.$this->errorCode);
            debug('errMsg:'.$this->sqlError);
            debug('errTrace:'.$e->getTraceAsString());
            return false;
        }

        debug(date('y-m-d H:i').' sql failed: '.$sql);
        debug('errMsg:'.$e->getMessage());
        switch($this->dbDriver)
        {
            case 'mysql':
                // 与服务器断开,重新连接
                if (2006 == $this->errorCode || 2013 == $this->errorCode)
                {
                    debug('db reconnect and try again');            
                    $this->reconnect();
                    try {
                        $this->stmt = $this->dbh->prepare($sql);
                        $this->stmt->execute($params);
                        return true;
                    } catch (Exception $ex) {
                        debug(date('y-m-d H:i').' still sql failed: '.$sql);
                        debug('errCode:'.$ex->getMessage() );
                    }
                }
                break;
            case 'pgsql':
                break;
            case 'sqlsrv':
                break;
            case 'sqlite':
                // 数据结构发生改变错误,重新尝试一次
                if (17 == $this->errorCode) {
                    try {
                        debug('db schema has changed execute again');            
                        $this->stmt = $this->dbh->prepare($sql);
                        $this->stmt->execute($params);
                        return true;
                    } catch (Exception $ex) {
                        debug(date('y-m-d H:i').' still sql failed: '.$sql);
                        debug('errCode:'.$ex->getMessage() );
                    }
                }
                break;
            default:
                break;
        }

        return false;
    }


    protected function _del($tableName,$where = CONDITION_TRUE, $wArr=array()) 
    {
        $r = false;
        if ( NULL != $where )
        {
            $where = "WHERE $where";
        }
        $sql = "DELETE FROM $tableName $where";
        try
        {
            $this->stmt = $this->dbh->prepare( $sql );
            $this->stmt->execute( $wArr );
            $r = $this->stmt->rowCount();
            $this->stmt = NULL;
        }
        catch(PDOException $e)
        {
            $this->stmt = NULL;
            $res = $this->exceptHandle($e, $sql, $wArr);
            if (false == $res) {
                return false;
            }
            $r = $this->stmt->rowCount();
            $this->stmt = NULL;
        }

        return $r;
    }


    protected function _update(&$tableName, &$infoArray, $ignoreFiled,$where = CONDITION_TRUE, $wArr=array()) 
    {
        $keys  = array();
        $infos = array();
        foreach ( $infoArray as $key=>&$info )
        {
            if ( isset($ignoreFiled) )
            {
                if (in_array($key, (array)$ignoreFiled))
                {
                    //跳过不处理的field，一般是主键不处理
                    continue;
                }
            }
            $keys[]  = $key.'=?';
            if ( '' === $info)
            {
                $infos[] = NULL;
            }
            else
            {
                $infos[] = &$info;
            }

        }
        if ( 0 == count( $keys ) )
        {
            return true;
        }
        if ( NULL != $wArr && is_array($wArr))
        {
            $infos = array_merge($infos, $wArr);
        }


        $setValue = implode(",",$keys);

        if ( NULL != $where )
        {
            $where = "WHERE $where";
        }
        $sql = "UPDATE $tableName SET $setValue $where"; 
        try
        {
            $this->stmt = $this->dbh->prepare( $sql );
            $this->stmt->execute( $infos );
            $this->stmt = NULL;
        }
        catch(PDOException $e)
        {
            $this->stmt = NULL;
            $res = $this->exceptHandle($e, $sql, $infos);
            if (false == $res) {
                return false;
            }
            $this->stmt = NULL;
        }
        return true;
    }

    function _add(&$tableName,&$infoArray,$ignoreFiled=NULL,$isReturnID=false) 
    {
        $keys  = array();
        $tags  = array();
        $infos = array();
        foreach ( $infoArray as $key=>&$info )
        {
            if ( isset($ignoreFiled) )
            {
                if (in_array($key, (array)$ignoreFiled))
                {
                    //跳过不处理的field，一般是主键不处理
                    continue;
                }
            }
            $keys[] = $key;
            $tags[] = '?';
            if ( '' === $info)
            {
                $infos[] = NULL;
            }
            else
            {
                $infos[] = &$info;
            }
        }
        $filed = implode(", ",$keys);
        $value = implode(", ",$tags);
        $sql = "INSERT INTO $tableName ($filed) VALUES ($value)";

        try
        {
            if( ($GLOBALS['transNum'] > 0) && ('pgsql' == $this->dbDriver) )
            {
                if ( FALSE === $this->dbh->exec('SAVEPOINT add_savepoint'))
                {
                    die('add save faile');
                }
            }
            $this->stmt = $this->dbh->prepare( $sql, array( PDO::ATTR_CURSOR => PDO::CURSOR_FWDONLY ) );
            $this->stmt->execute( $infos );
            $this->stmt = NULL;
        }
        catch(PDOException $e)
        {
            switch($this->dbDriver)
            {
            case 'pgsql':
                $INSERT_REDEF =  23505;
                break;
            case 'mysql':
            case 'sqlsrv':
            case 'sqlite':
                $INSERT_REDEF =  23000;
                break;
            default:
                $INSERT_REDEF =  0;
                break;	
            }

            $this->stmt = NULL;
            if ( $INSERT_REDEF == $e->getCode() )
            {
                // pgsql中某个语句出错，就必须回滚整个事务。而不允许把错误直接略过
                // 所以对于pgsql，还需要使用savepoint先回滚到原状态。
                //23000是对不允许重复的数据重复添加。对这种错误不打印,当作添加成功
                //代码里有很多这样的处理，不判断数据是否重复,而是由数据库保证
                if( ($GLOBALS['transNum'] > 0) && ('pgsql' == $this->dbDriver) )
                {
                    if ( FALSE === $this->dbh->exec('ROLLBACK TO SAVEPOINT add_savepoint'))
                    {
                        die('add rollback fail');
                    }
                    //$this->dbh->exec('begin');
                }
                $this->errorCode = $e->getCode();
                return INVALID_ID;
            }
            $res = $this->exceptHandle($e, $sql, $infos);
            if (false == $res) {
                return INVALID_ID;
            }
        }

        if ($isReturnID )
        {
            if( 'pgsql' == $this->dbDriver )
            {
                //pgsql的特殊处理
                //lastInsertId: PDO_PGSQL() requires you to specify the name of a sequence object for the name parameter
                $seq = trim($tableName).'_id_seq';//id实际上要改为主键名
                $lastID = $this->dbh->lastInsertId($seq); 
            }
            else
            {
                $lastID = $this->dbh->lastInsertId(); 
            }
            return $lastID;  
        }
        else
        {
            return 0;
        }
    }

    protected function _queryAll($tableName, $queryFiled='*', $where=CONDITION_TRUE, $wArr=array(), $offset=0, $num=-1) 
    {
        return $this->_query(strtolower($tableName), $queryFiled, $where, $wArr, $offset, $num) ;
    }


/*  copy from zend framework
     function sqlsrvlimit($sql, $count, $offset = 0)
     {
        $count = intval($count);
        if ($count <= 0) {
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("LIMIT argument count=$count is not valid");
        }

        $offset = intval($offset);
        if ($offset < 0) {
            require_once 'Zend/Db/Adapter/Exception.php';
            throw new Zend_Db_Adapter_Exception("LIMIT argument offset=$offset is not valid");
        }

        if ($offset == 0) {
            $sql = preg_replace('/^SELECT\s/i', 'SELECT TOP ' . $count . ' ', $sql);
        } else {
            $orderby = stristr($sql, 'ORDER BY');

            if (!$orderby) {
                $over = 'ORDER BY (SELECT 0)';
            } else {
                $over = preg_replace('/\"[^,]*\".\"([^,]*)\"/i', '"inner_tbl"."$1"', $orderby);
            }

            // Remove ORDER BY clause from $sql
            $sql = preg_replace('/\s+ORDER BY(.*)/', '', $sql);

            // Add ORDER BY clause as an argument for ROW_NUMBER()
            $sql = "SELECT ROW_NUMBER() OVER ($over) AS \"ZEND_DB_ROWNUM\", * FROM ($sql) AS inner_tbl";

            $start = $offset + 1;
            $end = $offset + $count;

            $sql = "WITH outer_tbl AS ($sql) SELECT * FROM outer_tbl WHERE \"ZEND_DB_ROWNUM\" BETWEEN $start AND $end";
        }

        return $sql;
    }
 */

    //查询，如果需要排序，order by也需要写在$where里
    //查询得到的结果下标子串全部是使用大写！
    //offset为-1表示只查最开始一个.$num=-1表示查所有.
    protected function _query($tableName, $queryFiled='*', $where=CONDITION_TRUE, $wArr=array(), $offset=-1, $num=1) 
    {
        if ( NULL != $where )
        {
            $where = "WHERE $where";
        }
        if ( (-1 == $offset) || ( -1 == $num )  )
        {
            //只查一个最后用fetch获得结果.和先设置limit性能有区别没？
            $sql = "select $queryFiled from $tableName $where"; 
        }
        else //sql server的limit要如何写，单单那top，还是很难搞
        {
            $sql = "select $queryFiled from $tableName $where";

            switch ( $this->dbDriver )
            {
            case 'pgsql':
            case 'sqlite':
                $sql .= ' LIMIT '.$num;
                if ($offset > 0)
                {
                    $sql .= ' OFFSET '.$offset;
                }					
                break;
            case 'sqlsrv'://这个要参考下本文件中注释掉的sqlsrvlimit再重新修正。现在是先自己简单处理
                $i = $num + $offset;
                $sql = preg_replace('/(^\SELECT (DISTINCT)?)/i','\\1 TOP '.$i.' ', $sql);
                break;
            case 'mysql':
                if ($offset == 0){
                    $offset = '';
                }
                else{
                    $offset .= ', ';
                }			
                $sql = $sql.' LIMIT '.$offset.$num;
                break;
            default:
                break;
            }
        }

        $record = NULL;

        try
        {
            //echo $sql;  
            $this->stmt = $this->dbh->prepare($sql);
            $this->stmt->execute($wArr);
        }
        catch(PDOException $e)
        {
            $this->stmt = NULL;
            $res = $this->exceptHandle($e, $sql,$wArr);
            if (false == $res) {
                return NULL;
            }
        }

        switch ($offset) 
        {
        case -1:
            // 返回第一个记录
            $record = $this->stmt->fetch( PDO::FETCH_ASSOC);
            break;
        default:
            //返回所有记录
            $record = $this->stmt->fetchAll( PDO::FETCH_ASSOC);
            if( ( 0 != $offset)  && ('sqlsrv' == $this->dbDriver) )
            {
                $record = array_slice($record, $offset);//sqlsrv先用一个top获得前面的数据再自己对结果进行处理
            }
            break;
        }
        $this->stmt = NULL;

        return $record;
    }

    protected function _queryPrepare( $wArr=array(), $offset=-1, $num=1 )
    {
        //需要判断这个stmt是不是查询
        if ( NULL == $this->stmt )
        {
            return NULL;
        }
        $record = NULL;
        try
        {
            $this->stmt->execute($wArr);
            switch ($offset) 
            {
            case -1:
                // 返回第一个记录
                $record = $this->stmt->fetch( PDO::FETCH_ASSOC);
                break;
            default:
                //返回所有记录
                $record = $this->stmt->fetchAll( PDO::FETCH_ASSOC);
                if( ( 0 != $offset)  && ('sqlsrv' == $this->dbDriver) )
                {
                    $record = array_slice($record, $offset);//sqlsrv先用一个top获得前面的数据再自己对结果进行处理
                }
                break;
            }
            $this->stmt = NULL;
        }
        catch(PDOException $e)
        {
            $this->exceptHandle($e, $sql, $wArr);
            return NULL;
        }

        return $record;    	
    }

    //这个也可以改为_query实现
    //对指定字段实现指定函数的运算  
    //支持MAX,MIN,AVG,SUM,VARIANCE,STDDEV
    protected function _getFunValue($tableName,$fun,$field, $where=CONDITION_TRUE, $wArr=array()) 
    {
        if ( NULL != $where )
        {
            $where = "WHERE $where";
        }
        $sql = "select $fun($field)  from $tableName $where ";
        try
        {
            $this->stmt = $this->dbh->prepare($sql);
            $this->stmt->execute($wArr);
        }
        catch(PDOException $e)
        {
            $this->stmt = NULL;
            $res = $this->exceptHandle($e, $sql, $wArr);
            if (false == $res) {
                return false;
            }
        }
        while($f=$this->stmt->fetch())
        {
            $this->stmt = NULL;
            return $f[0];
        }
        return false;

        //$rs = $this->_query($tableName, "$fun($field) as FUN_VAR", $where, $wArr) 
        //if ( NULL == $rs )
        //{
        //	return false;
        //}
        //return $rs['FUN_VAR'];
    }

    //检测指定表名是否存在
    function existTable($tableName)
    {
        $sql = "select count(*) from $tableName";
        try
        {
            $this->stmt = $this->dbh->prepare($sql);
            $this->stmt->execute();
            $this->stmt = NULL;
        }
        catch(PDOException $e)
        {
            $this->stmt = NULL;
            return false;
        }
        return true;
    }
}

class TableSql extends SQL
{
    //主键名
    public  $primKey = NULL;
    //要操作的数据库表名
    public  $tableName = NULL; //在云环境中加前缀的表名
    private $isJoin    = false;//是否已经设置过join
    private $joinArr   = array();
    private $otb       = NULL; //传入参数的原始表名
    public $isCloud   = false;//是否是云管理表。如果不是，需要强制添加cloudid条件
    //数据库结构表。定义了数据库各成员的类型，名称，以及与其它表的关联关系。
    //暂时没用。注掉
    //var $metaTable = NULL;

    /* 
    更新时自动填充的字段
    $updateAutoComplete = array{
        'FIELDNAME' => 'VALUE',  //字段名=>自动设置的值
        ......
    }
     */
    //var $updateAutoComplete=NULL;

    /* 
    创建时自动填充的字段
    $createAutoComplete = array{
        'FIELDNAME' => 'VALUE';  //字段名=>自动设置的值
        ......
    }
     */
    //var $createAutoComplete=NULL;


    //构造函数。
    //dbHandle:数据库连接句柄
    //tableName：该类要操作的数据表名，如果表名为空，则使用类名
    //primKey:主键名。如果主键名为空，则使用默认设置。否则主键名使用该值
    //metaTable:数据库结构表，见deDesign定义
    function __construct($tableName=NULL,$primKey=NULL)  
    {
        parent::__construct();
        if ( NULL == $primKey )
        {
            $primKey = $this->primKey;
        }
        $this->setTable($tableName,$primKey);
    }

    function __destruct() 
    {
        parent::__destruct();
    }

    function setTable($tableName=NULL,$primKey=NULL)
    {
        if ( NULL == $tableName )
        {
            $this->tableName = strtolower( get_class($this) );
        }
        else
        {
            $this->tableName = trim(strtolower( $tableName ));
        }
        $this->otb       = $this->tableName; 
        $this->tableName = $this->getCloudTB($this->tableName);
        $this->isJoin    = false;
        $this->joinArr   = array();

        $this->isCloud   = false;
        if ( $this->otb == $this->tableName )
        {
            //本地环境的，默认全都是
            $this->isCloud   = true;
        }

        if( $this->isCloud )
        {
            $this->setDBH( getCloudDBH() );
        }
        else
        {
            $this->setDBH( getAppDBH() );
        }

        $this->primKey = $primKey;

        return $this;
    }

    //默认内连接
    function join($tb, $joinWhere = NULL, $jt=NULL)
    {
        $tb = trim(strtolower($tb));
        if ( in_array($tb,$this->joinArr) )
        {
            return $this;
        }
        //test1 INNER JOIN test2 ON test1.id = test2.tid 
        $ctb = $this->getCloudTB($tb);

        //$joinWhere中如果有数据表名就需要替换为云环境中的真实表名
        //因为不好替换，就把表设置别名来处理
        if(!$this->isCloud)
        {
            $this->tableName = "$this->tableName $this->otb $jt JOIN $ctb  $tb ";
        }
        else
        {
            $this->tableName = "$this->tableName $jt JOIN $ctb ";
        }

        if ( NULL != $joinWhere )
        {
            $this->tableName .= " ON $joinWhere ";
        }

        $this->joinArr[] = $tb;
        $this->isJoin    = true;

        return $this;
    }
    function isJoin()
    {
        return $this->isJoin; 
    }
    function isCloud()
    {
        return $this->isCloud; 
    }    
    //云环境强制添加查询附加条件
    private function addCloudW( &$where, &$wArr )
    {
        //云管理表和本地环境都无需再添加强制条件
        if ( $this->isCloud )
        {
            return;
        }
        $sysuid = getSysUid();
        if ( NULL === $sysuid  ) 
        {
            die('lose the system id'); //云环境下，该值不能不存在。
        }
        //操作维护程序特别设置
        if ( 0 === $sysuid )
        {
            return;
        }

        if ( $this->isJoin )
        {
            $nw     = "$this->otb.CLOUDID=?";
        }
        else
        {
            $nw     = 'CLOUDID=?';
        }

        //为了避免order by等语句干扰，cloudid条件放最前面
        //$wArr[] = $sysuid;
        array_unshift($wArr, $sysuid);

        if ( NULL == $where )
        {
            $where = $nw;
        }
        else
        {
            $where = "$nw AND $where";
        }

        return $this;	
    }

    function del($where = CONDITION_TRUE,$wArr=array()) 
	{
        if ('b' == HIC_LOCAL )
        {
            //hic中，大量的写设备状态，实际是无需同步保存到flash中的。
            //为了减少flash的读写次数，除非必要，不做保存标记
            file_put_contents('/tmp/dbchange','');
        }
        return $this->del1($where,$wArr); 
		
	}
    //根据条件删除记录
    function del1($where = CONDITION_TRUE,$wArr=array()) 
    {
        //云环境下强制条件
        $this->addCloudW( $where, $wArr );
        return $this->_del($this->tableName,$where, $wArr);
    }

    //查询，如果需要排序，order by也需要写在$where里
    //！！查询得到的结果下标子串全部是要使用大写！
    //因为sql类也要调用query函数，所以该函数只好改名
    function query($queryFiled='*', $where=CONDITION_TRUE, $wArr=array()) 
    {
        //云环境下强制条件
        $this->addCloudW( $where, $wArr );
        return $this->_query($this->tableName, $queryFiled, $where, $wArr);
    }

    //根据条件更新指定记录。如果没条件，则根据主键信息进行更新
    function update($infoArray, $ignoreFiled=NULL,$where = NULL, $wArr=array()) 
    {
        if ('b' == HIC_LOCAL )
        {
            //hic中，大量的写设备状态，实际是无需同步保存到flash中的。
            //为了减少flash的读写次数，除非必要，不做保存标记
            file_put_contents('/tmp/dbchange','');
        }
        return $this->update1($infoArray, $ignoreFiled,$where, $wArr); 
    }
    function update1($infoArray, $ignoreFiled=NULL,$where = NULL, $wArr=array(),$rch=true) 
    {
        if ( NULL != $this->primKey )
        {
            if ( NULL == $where )//根据主键来更新
            {
                if ( !isset($infoArray[$this->primKey])) 
                {
                    return true;//没有主键也没有查询条件，直接返回
                }
                $primValue = intval($infoArray[$this->primKey]);
                $where = "$this->primKey = $primValue";
            }
            if( isset($infoArray[$this->primKey]) )
            {
                unset($infoArray[$this->primKey]);
            }
        }
        if ( DSTP_CLU != CLU_CLOUD )
        {
            if ( NULL == $ignoreFiled )
            {
                $ignoreFiled = array();
            }
            $ignoreFiled[] = 'CLOUDID';
        }

        //获得更新可能影响的主键，进行权限检查

        //云环境下强制条件
        $this->addCloudW( $where, $wArr );

        return $this->_update($this->tableName, $infoArray, $ignoreFiled,$where, $wArr) ;
    }
	
	function add($infoArray,$ignoreFiled=NULL,$isReturnID=false) 
	{
        if ('b' == HIC_LOCAL )
        {
            //hic中，大量的写设备状态，实际是无需同步保存到flash中的。
            //为了减少flash的读写次数，除非必要，不做保存标记
            file_put_contents('/tmp/dbchange','');
        }
        return $this->add1($infoArray,$ignoreFiled,$isReturnID); 
		
	}

    function add1($infoArray,$ignoreFiled=NULL,$isReturnID=false) 
    {
        //判断是否有创建权限

        if ( NULL != $this->primKey )
        {
            if( isset($infoArray[$this->primKey]) )
            {
                unset($infoArray[$this->primKey]);
            }
            $isReturnID = true; //如果有主键，默认就是递增的ID，需要返回
        }

        if ( DSTP_CLU == CLU_CLOUD )
        {
            $sysuid = getSysUid();
            if ( ( NULL != $sysuid ) && !$this->isCloud) 
            {
                $infoArray['CLOUDID'] = $sysuid;
            }
        }
        else
        {
            if ( NULL == $ignoreFiled )
            {
                $ignoreFiled = array();
            }
            $ignoreFiled[] = 'CLOUDID';
        }

        return $this->_add($this->tableName,$infoArray,$ignoreFiled,$isReturnID);
    }


    //查询符合指定条件的全部记录
    function queryAll($queryFiled='*', $where=CONDITION_TRUE, $wArr=array(), $offset=0, $num=-1) 
    {
        //获得查询可能获得的数据的主键，进行权限判断.没有权限的直接过滤.获得主键时不能直接调用queryAllList，该函数会调用本函数

        //云环境下强制条件
        $this->addCloudW( $where, $wArr );
        return $this->_queryAll($this->tableName, $queryFiled, $where, $wArr, $offset, $num);
    }

    function getRecordNum($where=CONDITION_TRUE, $wArr=array()) 
    {
        //云环境下强制条件
        $this->addCloudW( $where, $wArr );
        $rs = $this->_getFunValue($this->tableName,'count','*', $where, $wArr);
        if( false === $rs )
        {
            return 0;
        }
        return $rs;
    }

    function getFunValue($fun,$field, $where=CONDITION_TRUE, $wArr=array()) 
    {
        //云环境下强制条件
        $this->addCloudW( $where, $wArr );
        return $this->_getFunValue($this->tableName,$fun,$field, $where, $wArr);
    }


    //查某个数据表成员，并把该成员值组成一个数组返回
    function queryAllList($queryFiled=NULL, $where=CONDITION_TRUE, $wArr=array(), $offset=0, $num=-1) 
    {
        //要先判断queryFiled是否只有一个成员

        $list = array();
        if ( NULL == $queryFiled )
        {
            $queryFiled = $this->primKey;
        }
        $result = $this->queryAll($queryFiled, $where, $wArr, $offset, $num);
        if ( NULL == $result )
        {
            return $list;
        }
        foreach ($result as $item)
        {
            $list[] = $item[$queryFiled];
        }
        return $list;
    }

    //查某个数据表成员，并把查到的第一个值返回
    function queryValue($queryFiled=NULL, $where=CONDITION_TRUE, $wArr=array()) 
    {
        //要先判断queryFiled是否只有一个成员

        if ( NULL == $queryFiled )
        {
            $queryFiled = $this->primKey;
        }
        $result = $this->query($queryFiled, $where, $wArr);
        if ( NULL == $result )
        {
            return NULL;
        }
        return $result[$queryFiled];
    }


    ////////////////////////////////////////////////////////////////

    //设置查询条件.$conditions可能是个数组也可能是个字串。
    //如果是个字串，直接返回。如果是个数组，则把数组的
    private function _getWhere($conditions)
    {
    }


    //删除指定主键记录
    function delByID( $ID ) 
    {
        if ( NULL == $this->primKey )
        {
            return false;
        }
        $ID = intval( $ID );
        $where = "$this->primKey = ?";
        return $this->del($where,array($ID));
    }

    //根据主键进行查找对应记录的信息
    function queryByID($ID,$queryFiled='*') 
    {
        if ( NULL == $this->primKey )
        {
            return NULL;
        }
        $ID = intval( $ID );
        $where = "$this->primKey = ?";
        return $this->query( $queryFiled, $where,array($ID));
    }

    //重置表中记录。list是要保存的记录。existField是对比的记录
    //重置后，list中的所有记录保存到数据表中，exist中存在但list中不存在的记录会被删除
    //如果exist为false，则表示重置整张表
    function resetRecord(&$list, $existField=false) 
    {
        if ( NULL == $this->primKey )
        {
            return false;
        }
        if ( !is_array($list) )
        {
            return false;
        }
        if ( false === $existField )//这儿要用===强判断，否则有可能传一个空数组进来就会被误当作处理整张表
        {
            $existField = $this->queryAllList();
        }

        $find = false;
        foreach ( (array)$existField as $exist )
        {
            $find = false;
            foreach ( $list as &$a )
            {
                if ( !isset( $a[$this->primKey] ) )
                {
                    continue;
                }
                if ( $exist == $a[$this->primKey] )
                {
                    $find = true;
                    break;
                }
            }
            if ( true == $find )
            {
                continue;
            }
            $this->delByID($exist);
        }

        //如果ID不为INVALID,更新.如果ID为INVALID,添加
        foreach ( $list as &$a )
        {
            if ( !isset( $a[$this->primKey] ) || INVALID_ID == $a[$this->primKey] || 0 >= $a[$this->primKey])
            {
                $a[$this->primKey] = $this->add($a);
            }
            else
            {
                $this->update($a);
            }
        }

        return true;

    }
}


?>
