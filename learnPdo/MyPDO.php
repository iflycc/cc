<?php
/**
 * Created by PhpStorm.
 * User: changchao
 * Date: 2018/3/21
 * Time: 16:59
 * Class: MyPDO
 * @tip 封装PDO操作类
 */

class MyPDO
{
    // 当前数据库的操作对象
    protected $db = null;
    // 表前缀
    protected $tablePrefix = "";
    // 表名 （不包含表前缀）
    protected $tableName = "";
    // 真实表名 （包含表前缀）
    protected $trueTableName = "";
    // 数据库名称
    protected $dbName = "";
    // 数据库的连接信息，接收一个一维数组
    protected $connect = array();
    // 允许写入数据库的数组，接收一个一维数组索引数组
    protected $allowFields = array();
    // 排除写入数据库的数组，接收一个一维数组索引数组
    protected $exceptFields = array();
    // 数据库编码
    protected $charset = "utf8";
    // 默认的pdo连接options数据选项
    protected $options = [];
    # PDO连接
    private static $pdoCh = null;

    # table数据表
    private $table = "";
    # 最后执行的sql语句
    private $lastSql = "";
    # where条件
    private $where = "";
    # group条件
    private $group = "";
    # limit条件
    private $limit = "";
    # field条件
    private $fields = "";
    # order条件
    private $order = "";
    # having条件
    private $having = "";

    /**
     * 初始化，连接数据库
     * MyPDO constructor.
     * @param string $host     数据库ip地址
     * @param string $username 用户名
     * @param string $password 密码
     * @param string $dbName   数据库名
     * @param array  $options  pdo选项
     * @param mixed  $port     数据库连接端口
     * @return mixed
     */
    public function __construct($host,$username,$password,$dbName,array $options =[],$port = 3306)
    {
        # 默认赋值给PDO连接的pdo选项
        $pdoOptions = $options ? $options :  array(
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset};", //默认数据库编码：utf8
            PDO::ATTR_PERSISTENT => false, //默认长连接关闭
        );
        # PDO连接源
        $pdoDsn="mysql:host={$host}:{$port};dbname={$dbName}";
        try
        {
            //单例模式实例化pdo句柄 （需要静态成员属性存储PDO实例）
            if(!(self::$pdoCh instanceof PDO)){
                # 实例化pdo类并赋值给私有podCh句柄
                self::$pdoCh = new PDO($pdoDsn,$username,$password,$pdoOptions);
                $this->db = new self($host,$username,$password,$dbName,$options,$port);
            }
            $err = error_get_last();
            if(!is_null($err))
                throw new Exception(json_encode($err));
        }
        catch (Exception $exception)
        {
            $errArr = array(
                "msg"  => json_encode($exception->getMessage(),true),
                "code" => $exception->getCode(),
                "file" => $exception->getFile(),
                "line" => $exception->getLine(),
            );
            print_r($errArr);
            //遇到连接报错，则数据提示json
            $this->_jsonPrint($errArr);

        }
        return $this->db;
    }

    /**
     * 执行Select查询语句并返回执行结果
     * @param $sql
     * @return array
     */
    public function query($sql)
    {
        $pdoObj = self::$pdoCh->query($sql,PDO::FETCH_ASSOC);
        if($pdoObj === false)  die(print_r(self::$pdoCh->errorInfo())); //若执行出错或者无效的执行，则中止程序并打印错误信息
        $result = $pdoObj->fetchAll();
        $this->lastSql = $sql;
        return $result;
    }

    /**
     * 执行 Insert/Update/Delete语句并返回受影响的记录条数
     * @param $sql
     * @return int|bool sql语句报错则返回false | 返回受影响的记录条数（注：执行insert语句时，也是返回受影响的记录条数，不会返回自增长的主键ID）
     */
    public function execute($sql)
    {
        //die()函数 ： 接收一个程序返回的数值或是一个字符串
        $execRs = self::$pdoCh->exec($sql) ;
        $this->lastSql = $sql;
        if($execRs === false) die(print_r(self::$pdoCh->errorInfo(),true));  //若执行出错或者无效的执行，则中止程序并打印错误信息
        return $execRs;
    }


    /**
     * @param  string|array $where 接收SQL语句的where条件，支持两种格式：
     *          1.字符串格式（不解析直接执行），如：where `name` = 'James' and `age` >= 18;
     *          2.数组条件：（表达式不区分大小写，解析的时候全部转换成小写识别）
     *                  ①`=`（等于）： array("id" => 5) | array('EQ',5) //表示id = 5
     *                  ②`<>`（不等于）： array("id" => 5) | array('NEQ',5) //表示id = 5
     *                  ③`NOT IN`/`IN`（[不在]在范围内）： array("id" => array('IN',"1,2,3,4,5") ) | array('id' => array('IN',array(1,2,3,4,5)))   //表示id in (1,2,3,4,5)
     *                  ④`>`（大于）：array('id' => array('GT',5))  //表示 id > 5
     *                  ⑤`>=`（大于等于）：array('id' => array('EGT',5))  //表示 id >= 5
     *                  ⑥`<`（大于）：array('id' => array('LT',5))  //表示 id < 5
     *                  ⑦`<=`（大于等于）：array('id' => array('ELT',5))  //表示 id <= 5
     *                  ⑧`like`（模糊查询）：array('name',array('LIKE','%'.$name.'%')) //表示 %$name%
     *                  ⑨`NOT BETWEEN` / `BETWEEN`（[不]在区间内）：array('id'=>array('BETWEEN',array($min,$max))) //表示 $min <= id <= $max
     * @return  $this 返回自己的实例对象
     */
    public function where($where)
    {
        $this->where = $where;
        return $this;
    }

    /**
     * @param string|array $fields 操作的字段
     *          ①字符串形式（不解析直接执行）,如：field('id,name,age')；
     *          ②数组形式，数组索引数组，如：field(array('id','name','age'))；
     * @return  $this 返回自己的实例对象
     */
    public function field($fields)
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * 排序如果没有指定desc或者asc排序规则的话，默认为asc
     * @param  string|array $order
     *         ① 字符串格式，如：order("id desc") / order("id desc,age (asc)")
     *         ② 数组格式，如：array('id'=>'desc','age') //如果是key=>val形式，则使用val进行排序，索引数组则默认为：asc，多个字段拼接排序
     * @return  $this 返回自己的实例对象
     */
    public function order($order)
    {
        $this->order = $order;
        return $this;
    }


    /**
     * 指定查询数量
     * @access public
     * @param mixed $offset 起始位置
     * @param mixed $length 查询数量
     * @return  $this 返回自己的实例对象
     */
    public function limit($offset,$length=null){
        if(empty($offset))
            $this->limit = "";
        else
            $this->limit = is_null($length) ? $offset : $offset.','.$length; //判断第二个参数是否存在，若不存在，则直接使用第一个参数作为limit；若存在，则使用二个参数拼接作为limit
        return $this;
    }

    /**
     * @param string|array $group 按字段分组
     *        ① 字符串格式，如：group('name,age')；
     *        ② 数组格式，如：group(array('name','age','gender'...))； //使用implode连接
     * @return $this
     */
    public function group($group)
    {
        $this->group = $group;
        return $this;
    }

    /**
     * SELECT操作
     * @return array
     */
    public function select()
    {
        //获取where的字符串
        $whereString = $this->_parseWhere($this->where);
        //获取操作的table表名
        $tableString = $this->table;
        //获取查询的fields字段
        $fieldsString = $this->_parseFields($this->fields) ? $this->_parseFields($this->fields) : "*";
        //获取查询的order
        $orderString = $this->_parseOrder($this->order);
        //获取group
        $groupString = $this->_parseGroup($this->group);
        //获取limit
        $limitString = $this->_parseLimit($this->limit);

        $sql = "select {$fieldsString} from {$tableString} {$whereString} {$groupString} {$orderString} {$limitString}";

        # 调试模式
        if(isset($_REQUEST["debug"]) && $_REQUEST["debug"] == 1){
            echo "<hr />";
            echo $sql,"<br />";
            echo "<hr />";
            exit;
        }

        $this->lastSql = $sql;
        $pdoSmt = self::$pdoCh->prepare($sql);
        $pdoSmt->execute() or die(print_r($pdoSmt->errorInfo(),true));
        $result = $pdoSmt->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function find()
    {


    }

    public function add()
    {

    }

    public function update()
    {

    }

    public function delete()
    {

    }

    /**
     * 具体操作的表名
     * @param $table
     * @return $this
     */
    public function table($table)
    {
        $table = trim($table,'`');
        $this->table = "`{$table}`";
        return $this;
    }
    /**
     * 开启PDO事务，成功返回true，失败返回false
     * @return bool
     */
    public function beginTransaction()
    {
        return self::$pdoCh->beginTransaction();
    }

    /**
     * 判断当前执行程序是否处于某个事务之中，是则返回true，否则返回false
     * @return bool
     */
    public function inTransaction()
    {
        return self::$pdoCh->inTransaction();
    }

    /**
     * PDO事务提交，成功返回true，是被返回false
     * @return bool
     */
    public function commitTransaction()
    {
        return self::$pdoCh->commit();
    }

    /**
     * PDO事务回滚，成功返回true，是被返回false
     * @return bool
     */
    public function rollbackTransaction()
    {
        return self::$pdoCh->rollBack();
    }

    /**
     * 返回最后一条执行的sql
     * @return string
     */
    public function getLastSql()
    {
        return $this->lastSql;
    }

    /**
     * 返回最后一条执行的sql （getLastSql方法的简写）
     *  @return string
     */
    public function _sql()
    {
        return $this->getLastSql();
    }

#————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————
#—————————————————————————————————————————————————————————————————————华丽的分隔线 以下为私有方法———————————————————————————————————————————————————————————————————————————————————————————————————
#————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————————
    /**
     * @param  string|array $where 接收SQL语句的where条件，支持两种格式：
     *          1.字符串格式（不解析直接执行），如：where `name` = 'James' and `age` >= 18;
     *          2.数组条件：（表达式不区分大小写，解析的时候全部转换成小写识别）
     *                  ①`=`（等于）： array("id" => 5,name => Andy...) | array('id'=>array('EQ',5))     //表示id =  5
     *                  ②`<>`（不等于）：array('id'=>array('NEQ',5)) //表示id != 5
     *                  ③`NOT IN`/`IN`（[不在]在范围内）： array("id" => array('IN',"1,2,3,4,5") ) | array('id' => array('IN',array(1,2,3,4,5)))   //表示id in (1,2,3,4,5)
     *                  ④`>`（大于）：array('id' => array('GT',5))  //表示 id > 5
     *                  ⑤`>=`（大于等于）：array('id' => array('EGT',5))  //表示 id >= 5
     *                  ⑥`<`（大于）：array('id' => array('LT',5))  //表示 id < 5
     *                  ⑦`<=`（大于等于）：array('id' => array('ELT',5))  //表示 id <= 5
     *                  ⑧`not like` / `like`（模糊查询）：array('name',array('LIKE','%'.$name.'%')) | array('name',array('NOT LIKE','%'.$name.'%')) //表示 %$name%
     *                  ⑨`NOT BETWEEN` / `BETWEEN`（[不]在区间内）：array('id'=>array('BETWEEN',array($min,$max))) //表示 $min <= id <= $max
     *
     *             >>>>>组合查询<<<<<
     *                  10.`OR`
     * @return string $where 执行SQL的where条件
     */
    private function _parseWhere($where)
    {
        $whereString = "WHERE ";
        //字符串直接返回，不解析
        if(is_string($where)) return $where;
        //--------------------------------------------------- 解析数组 --------------------------------------------------
        $whereCount = count($where) - 1;//获取数组的长度，用于判断`AND`条件的个数
        foreach ($where as $_field => $_item){
            # 当where条件数组是：array("id" => 5,name => Andy...)这种形式时
            if(!is_array($_item)) {
                $whereString .= "`{$_field}` = '{$_item}'" . ($whereCount > 0 ? " AND " : "");
            }
            # 当是多维数组时
            else{
                $_exp = strtoupper(str_replace(" ","",$_item[0]));//获取需要处理的表达式：如 EQ/IN/LT......
                switch ($_exp)
                {
                    case "EQ": // array('id'=>array('EQ',5))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` = '{$exeVal}'" . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "NEQ": // array('id'=>array('NEQ',5))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` = '{$exeVal}'" . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "IN": // array("id" => array('IN',"1,2,3,4,5") ) | array('id' => array('IN',array(1,2,3,4,5)))
                        $inVars = is_array($_item[1]) ?  implode(',',$_item[1]) : $_item[1]; //当array(IN,array(...))时，转换为字符串
                        $whereString .= "`{$_field}` IN ({$inVars}) " . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "NOTIN":// array("id" => array('NOT IN',"1,2,3,4,5") ) | array('id' => array('NOT IN',array(1,2,3,4,5)))
                        $inVars = is_array($_item[1]) ?  implode(',',$_item[1]) : $_item[1]; //当array(IN,array(...))时，转换为字符串
                        $whereString .= "`{$_field}` NOT IN ({$inVars}) " . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "GT"://array('id' => array('GT',5))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` > '{$exeVal}'" . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "EGT"://array('id' => array('EGT',5))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` >= '{$exeVal}'" . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "LT": //array('id' => array('LT',5))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` < '{$exeVal}'" . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "ELT"://array('id' => array('ELT',5))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` <= '{$exeVal}'" . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "LIKE": //array('name',array('LIKE',$name))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` LIKE '%{$exeVal}%' " . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "NOTLIKE": // array('name',array('NOT LIKE',$name))
                        $exeVal = strval($_item[1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` NOT LIKE '%{$exeVal}%' " . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "BETWEEN"://array('id'=>array('BETWEEN',array($min,$max)))
                        $exeVal0 = strval($_item[1][0]); //将执行数值都转换成字符串
                        $exeVal1 = strval($_item[1][1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` BETWEEN '{$exeVal0}' AND '{$exeVal1}' " . ($whereCount > 0 ? " AND " : "");
                        break;
                    case "NOTBETWEEN":
                        $exeVal0 = strval($_item[1][0]); //将执行数值都转换成字符串
                        $exeVal1 = strval($_item[1][1]); //将执行数值都转换成字符串
                        $whereString .= "`{$_field}` NOT BETWEEN '{$exeVal0}' AND '{$exeVal1}' " . ($whereCount > 0 ? " AND " : "");
                        break;
                    default:
                        die("where条件 `{$_exp}` 解析出错，请核对条件格式！");
                }
            }
            $whereCount --;
        }

        return $whereString;
    }

    /**
     * @param string|array $fields 操作的字段
     *          ①字符串形式（不解析直接执行）,如：field('id,name,age')；
     *          ②数组形式，数组索引数组，如：field(array('id','name','age'))；
     * @return  string
     */
    private function _parseFields($fields)
    {
        if(is_string($fields) or is_null($fields)) return $fields;
        # 解析数组形式
        $fieldsString = '`'.implode('`,`',$fields).'`'; //转换成`id`,`name`,`age`...这种格式
        return $fieldsString;
    }

    /**
     * 排序如果没有指定desc或者asc排序规则的话，默认为asc
     * @param  string|array $order
     *         ① 字符串格式，如：order("id desc") / order("id desc,age (asc)")
     *         ② 数组格式，如：array('id'=>'desc','age') //如果是key=>val形式，则使用val进行排序，索引数组则默认为：asc，多个字段拼接排序
     * @return  string
     */
    private function _parseOrder($order)
    {
        if(empty($order)) return "";
        $orderString = "ORDER BY ";
        if(is_string($order)) return $orderString.$order;
        # 解析数组格式的参数：array('id'=>'desc','age')
        //也就是说：1、键值对，key是字段名，val是排序值； 2、索引数组，key是数字（可忽略），val是字段名
        foreach ($order as $_field => $_sort){
            $_field = trim($_field,'`');
            $orderString .= !is_numeric($_field) ? "`{$_field}` {$_sort}," : "`{$_field}`,";
        }

        return trim($orderString,',');
    }

    /**
     * 此方法在limit()方法中已经解析过了
     * @param $limit
     * @return string
     */
    private function _parseLimit($limit)
    {
        if(empty($limit)) return "";
        $limitString = "LIMIT ".$limit;
        return $limitString;
    }
    /**
     * @param string|array $group 按字段分组
     *        ① 字符串格式，如：group('name,age')；
     *        ② 数组格式，如：group(array('name','age','gender'...))； //使用implode连接
     * @return string
     */
    private function _parseGroup($group)
    {
        if(empty($group)) return "";
        $groupString = "GROUP BY ";
        #字符串直接返回
        if(is_string($group)) return $groupString.trim($group);
        # 解析数组
        foreach ($group as $_field){
            $groupString .=  "`".trim($_field)."`,";
        }
        return trim($groupString,",");
    }

    private function _parseHaving($having)
    {

    }

    /**
     * 获取数组的维度
     * @param array $array
     * @return int|mixed
     */
    private function _getArrayDepth(array $array)
    {
        $depth = 1; //存储数组的维度,默认为：1
        foreach ($array as $item){
            if(!is_array($item)) continue;//不是数组直接过果过滤
            $nowDepth = $this->_getArrayDepth($item) + 1; //递归执行
            $depth = max($depth,$nowDepth); //保存递归到的最大维度的数组元素维度
        }
        return $depth;
    }

    /**
     * 传入数组，以json格式打印
     * @param array $array
     */
    private function _jsonPrint(array $array)
    {
        header("content-type:application/json;charset=utf8;");
        echo json_encode($array,256);
        exit;
    }
}