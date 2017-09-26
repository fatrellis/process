<?php
namespace MQK\Process;


interface Master
{
    /**
     * 启动主进程
     *
     * @return void
     */
    function run();
}