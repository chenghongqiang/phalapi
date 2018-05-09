<?php

namespace V1\Api\Examples;

use PhalApi\Api;

/**
 * 进程 进程间通信
 *
 * @author: kewin.cheng <kewin.cheng@yeah.net> 2018/5/9
 */
class Process extends Api{

    /**
     * @desc 父子进程实例
     * @return int 父进程号
     */
    public function index(){
        register_shutdown_function(array($this, "catch_error"));

        $parentPid = posix_getpid();
        echo "parent progress pid:{$parentPid}\n";
        $childList = array();
        $pid = pcntl_fork();
        if ( $pid == -1) {
            // 创建失败
            exit("fork progress error!\n");
        } else if ($pid == 0) {
            // 子进程执行程序
            $pid = posix_getpid();
            $repeatNum = 5;
            for ( $i = 1; $i <= $repeatNum; $i++) {
                echo "({$pid})child progress is running! {$i} \n";
                $rand = rand(1,3);
                sleep($rand);
            }
            exit("({$pid})child progress end!\n");
        } else {
            // 父进程执行程序
            $childList[$pid] = 1;
        }
        // 等待子进程结束
        pcntl_wait($status);
        echo "({$parentPid})main progress end!";
    }

    public function msgQueue() {
        $parentPid = posix_getpid();
        echo "parent progress pid:{$parentPid}\n";

        $childList = array();
        $id = ftok(__FILE__, 'm');
        $msgQueue = msg_get_queue($id);

        // 生产者
        function producer(){
            global $msgQueue;
            $pid = posix_getpid();
            $repeatNum = 5;
            for ( $i = 1; $i <= $repeatNum; $i++) {
                $str = "({$pid})progress create! {$i}";
                msg_send($msgQueue,1,$str);
                $rand = rand(1,3);
                sleep($rand);
            }
        }

        // 消费者
        function consumer(){
            global $msgQueue;
            $pid = posix_getpid();
            $repeatNum = 6;
            for ( $i = 1; $i <= $repeatNum; $i++) {
                $rel = msg_receive($msgQueue,1,$msgType,1024,$message);
                echo "{$message} | consumer({$pid}) destroy \n";
                $rand = rand(1,3);
                sleep($rand);
            }
        }

        function createProgress($callback){
            $pid = pcntl_fork();
            if ( $pid == -1) {
                // 创建失败
                exit("fork progress error!\n");
            } else if ($pid == 0) {
                // 子进程执行程序
                $pid = posix_getpid();
                $callback();
                exit("({$pid})child progress end!\n");
            }else{
                // 父进程执行程序
                return $pid;
            }
        }

        // 3个写进程
        for ($i = 0; $i < 3; $i ++ ) {
            $pid = createProgress('producer');
            $childList[$pid] = 1;
            echo "create producer child progress: {$pid} \n";
        }
        // 2个写进程
        for ($i = 0; $i < 2; $i ++ ) {
            $pid = createProgress('consumer');
            $childList[$pid] = 1;
            echo "create consumer child progress: {$pid} \n";
        }
        // 等待所有子进程结束
        while(!empty($childList)){
            $childPid = pcntl_wait($status);
            if ($childPid > 0){
                unset($childList[$childPid]);
            }
        }
        echo "({$parentPid})main progress end!\n";

    }

    public function catch_error(){
        global $is_end;
        $time = date('Y-m-d H:i:s');
        $error = error_get_last();
        $msg = "$time [error]";
        if($is_end){
            $msg .= "is_end[yes]";
        }else{
            $msg .= "is_end[no]";
        }
        if($error){
            $msg .= var_export($error,1);
        }
        echo $msg."\r\n";
    }

    public function sig_handler($signo){
        $time = date('Y-m-d H:i:s');
        echo $time." exit  signo[{$signo}]\r\n";
        exit("");
    }

}