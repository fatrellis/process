<?php
namespace MQK\Process;


/**
 * Worker的工厂类
 *
 * @package MQK\Process
 */
interface WorkerFactory
{
    /**
     * @return AbstractWorker
     */
    function create();
}