<?php

namespace V1\Api\Examples;

use PhalApi\Api;

/**
 * 进程 进程间通信
 *
 * @author: kewin.cheng <kewin.cheng@yeah.net> 2018/5/9
 */
class Process extends Api{

    const SHARE_KEY = 1;

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

        // 3个写进程
        for ($i = 0; $i < 3; $i ++ ) {
            $pid = $this->createProgress('msgProducer');
            $childList[$pid] = 1;
            echo "create producer child progress: {$pid} \n";
        }
        // 2个写进程
        for ($i = 0; $i < 2; $i ++ ) {
            $pid = $this->createProgress('msgConsumer');
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

    // 生产者
    private function msgProducer(){
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
    private function msgConsumer(){
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

    /**
     * 信号量与共享内存
     * @desc 进程间通信方式——信号量与共享内存。<br>信号量是系统提供的一种原子操作，一个信号量同时只有一个进程能操作。一个进程获得了某个信号量，就必须被该进程释放掉
        <br>共享内存是系统内存中开辟的一块公共内存区域，任何一个进程都可以访问，在同一时刻，可以有多个进程访问该区域，为了保存数据的一致性，需要对该内存区域加锁或信号量
     */
    public function sem(){

        $parentPid = posix_getpid();
        echo "parent progress pid:{$parentPid}\n";

        $childList = array();

        //创建共享内存 创建信号量，定义共享key
        $shm_id = ftok(__FILE__, 'm');
        $sem_id = ftok(__FILE__, 's');
        $shareMemory = shm_attach($shm_id);
        $signal = sem_get($sem_id);

        // 3个写进程
        for ($i = 0; $i < 3; $i ++ ) {
            $pid = $this->createProgress('semProducer');
            $childList[$pid] = 1;
            echo "create producer child progress: {$pid} \n";
        }
        // 等待所有子进程结束
        while(!empty($childList)){
            $childPid = pcntl_wait($status);
            if ($childPid > 0){
                unset($childList[$childPid]);
            }
        }
        // 释放共享内存与信号量
        shm_remove($shareMemory);
        sem_remove($signal);
        echo "({$parentPid})main progress end!\n";

    }

    /**
     * 信号
     * @desc 进程间通信方式——信号。<br>信号是一种系统调用
     */
    public function signal() {

    }

    /**
     * 管道
     * @desc 进程间通信方式——管道。<br>管道分为无名管道和有名管道，无名管道只能用于具有亲缘关系的进行恒建通信，而有名管道可以用于同一主机上的任意进程
     */
    public function pipe() {

    }

    /**
     * 套接字
     * @desc 进程间通信方式——套接字socket
     */
    public function socket() {

    }

    // 生产者
    private function semProducer(){
        global $shareMemory;
        global $signal;
        $pid = posix_getpid();
        $repeatNum = 5;
        for ( $i = 1; $i <= $repeatNum; $i++) {
            // 获得信号量
            sem_acquire($signal);

            if (shm_has_var($shareMemory,self::SHARE_KEY)){
                // 有值,加一
                $count = shm_get_var($shareMemory,self::SHARE_KEY);
                $count ++;
                shm_put_var($shareMemory,self::SHARE_KEY,$count);
                echo "({$pid}) count: {$count}\n";
            }else{
                // 无值,初始化
                shm_put_var($shareMemory,self::SHARE_KEY,0);
                echo "({$pid}) count: 0\n";
            }
            // 用完释放
            sem_release($signal);

            $rand = rand(1,3);
            sleep($rand);
        }
    }

    private function createProgress($callback){
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