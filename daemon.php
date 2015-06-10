<?php
/*
 *守护进程
 **/
 class Daemon
 {
    /* config */
	const LISTEN = "0.0.0.0";
	const MAXCONN = 100;
	const pidfile = 'daemon';
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
            test();
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
 
 function test()
 {
     $str = sprintf("%s,%s\n",date('Y-m-d H:i:s'),'正在测试... 进入睡眠5秒中');
     file_put_contents('/www/hx9999.com/log.hx9999.com/daemon.log',$str,FILE_APPEND);
     sleep(5);
  //   echo $str;
 }
 
 $Daemon = new Daemon();
 $Daemon->main($argv);