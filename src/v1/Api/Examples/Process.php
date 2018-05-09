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
        register_shutdown_function("catch_error");
        pcntl_signal(SIGTERM, "sig_handler");
        pcntl_signal(SIGHUP, "sig_handler");
        pcntl_signal(SIGINT, "sig_handler");
        pcntl_signal(SIGQUIT, "sig_handler");
        pcntl_signal(SIGILL, "sig_handler");
        pcntl_signal(SIGPIPE, "sig_handler");
        pcntl_signal(SIGALRM, "sig_handler");

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

    private function catch_error(){
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

    private function sig_handler($signo){
        $time = date('Y-m-d H:i:s');
        echo $time." exit  signo[{$signo}]\r\n";
        exit("");
    }

}