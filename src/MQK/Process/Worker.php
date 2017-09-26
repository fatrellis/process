<?php
namespace MQK\Process;


interface Worker
{
    /**
     * 启动工作者进程
     *
     * @return void
     */
    function start();

    /**
     * 工作者进程的运行方法，子类应该重写这个方法，而且方法本身是一个抽象方法。
     *
     * @return void
     */
    function run();
}