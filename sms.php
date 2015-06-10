<?php
class CALLWorker extends Worker {

	protected  static $client;
    
	public function __construct() {

	}
	public function run(){
      require(ROOTDIR.'/config/webservice.config.php');
         $soap['location'] = 'http://api.hx9999.com/backend/Sms_queue';
         self::$client = new SoapClient(null, $soap);

	}
    
	protected function getConnection(){ 
            return self::$client;
    }
    

}

/* the collectable class implements machinery for Pool::collect */
class Sendsms extends Stackable {
    
	public function __construct($row) {
		$this->data = $row;
        
	}

	public function run() 
    {
        try{
            $client  = $this->worker->getConnection();
            #暂时不开通
            $result = $client->send($this->data['id']);
            $str = sprintf("%s,%s,%s\n", date('Y-m-d H:i:s'), $this->data['id'],'flag:'.$result['flag'].',msg:'.$result['msg'].',line:'.$result['line']);

        }catch(Exception $e){
            $msg = "Caught exception:{$e->getMessage()}";
            $str = sprintf("%s,【%s】,%s\n", date('Y-m-d H:i:s'), $this->data['id'],$msg);
           
        }
        
		file_put_contents('/www/hx9999.com/log.hx9999.com/sms.' . date("Y-m-d") . '.log', $str, FILE_APPEND);
	}
}

class Client {

    public static function main() {
        $str = sprintf("%s,%s\n", date('Y-m-d H:i:s'), '自动发送短信多线程开始' );
        file_put_contents('/www/hx9999.com/log.hx9999.com/sms.' . date("Y-m-d") . '.log', $str, FILE_APPEND);
        require(DBCONFIG);
        $pool = new Pool(MAX_CONCURRENCY_JOB, \CALLWorker::class, []);
        try {
            $mysqli = new mysqli($dbhost,$dbuser,$dbpw,$dbname);
            
            if ($mysqli->connect_errno) {
                $str = sprintf("%s,%s\n", date('Y-m-d H:i:s'), '数据库连接出错' );
               file_put_contents('/www/hx9999.com/log.hx9999.com/sms.' . date("Y-m-d") . '.log', $str, FILE_APPEND);
            }else{
                $sql = "select id from sms_queue where `status`='New' order by id desc limit 500";
                if ($query = $mysqli->query($sql)){
                    while($row=$query->fetch_assoc())
                    {
                        $pool->submit(new Sendsms($row));
                    }
                    $pool->shutdown();
                    $query->free();
                }

                $mysqli->close();

                $str = sprintf("%s,%s\n", date('Y-m-d H:i:s'), '自动发送短信多线程结束,准备进入睡眠5秒中' );
                file_put_contents('/www/hx9999.com/log.hx9999.com/sms.' . date("Y-m-d") . '.log', $str, FILE_APPEND);
                
            }
        } catch (Exception $e) {
            $str = sprintf("%s,%s\n", date('Y-m-d H:i:s'), ',【系统错误,数据库操作出现故障,自动发送短信多线程结束,准备进入睡眠5秒中】' . $e->getMessage() );
            file_put_contents('/www/hx9999.com/log.hx9999.com/sms.' . date("Y-m-d") . '.log', $str, FILE_APPEND);
			
		}
        //睡眠
        sleep(5);
    }
}



class Daemon {
	/* config */
	const LISTEN = "0.0.0.0";
	const MAXCONN = 100;
	const pidfile = 'sms';
	const uid	= 80;
	const gid	= 80;
	
	protected $pool = NULL;
	protected $zmq = NULL;
	public function __construct() {
		$this->pidfile = '/var/run/'.self::pidfile.'.pid';
	}
	private function daemon(){
		if (file_exists($this->pidfile)) {
			echo "The file $this->pidfile exists.\n";
			exit();
		}
		
		$pid = pcntl_fork();
		if ($pid == -1) {
			 die('could not fork');
		} else if ($pid) {
			 // we are the parent
			 //pcntl_wait($status); //Protect against Zombie children
			exit($pid);
		} else {
			// we are the child
			file_put_contents($this->pidfile, getmypid());
			posix_setuid(self::uid);
			posix_setgid(self::gid);
			return(getmypid());
		}
	}
	private function start(){
		$pid = $this->daemon();
        while( true ){
            Client::main();
        }
		
	}
	private function stop(){

		if (file_exists($this->pidfile)) {
			$pid = file_get_contents($this->pidfile);
			posix_kill($pid, 9); 
			unlink($this->pidfile);
		}
	}
	private function help($proc){
		printf("%s start | stop | help \n", $proc);
	}
	public function main($argv){
		if(count($argv) < 2){
			printf("please input help parameter\n");
			exit();
		}
		if($argv[1] === 'stop'){
			$this->stop();
		}else if($argv[1] === 'start'){
			$this->start();
		}else{
			$this->help($argv[0]);
		}
	}
}
//项目根目录
define('ROOTDIR',realpath(dirname(dirname(__FILE__))));
//数据库配置文件
define('DBCONFIG',ROOTDIR.'/config/db.config.php');
// 定义可以同时执行的进程数量
define('MAX_CONCURRENCY_JOB', 20);
define('SMS_LOG','/www/hx9999.com/log.hx9999.com/sms.' . date("Y-m-d") . '.log');

$Daemon = new Daemon();
$Daemon->main($argv);

?>
