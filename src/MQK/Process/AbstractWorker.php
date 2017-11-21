<?php
declare(ticks=1);
namespace MQK\Process;

use Monolog\Logger;

abstract class AbstractWorker implements Worker
{
    /**
     * 进程ID
     * @var int
     */
    protected $id;

    /**
     * @var int
     */
    protected $createdAt = 0;

    /**
     * @var Logger
     */
    protected $logger;

    public function __construct($logger=null)
    {
        if (null == $logger)
            $this->logger = new Logger(__CLASS__);
    }

    /**
     * 启动进程
     * @return int pid
     */
    public function start()
    {
        $pid = pcntl_fork();

        if (-1 == $pid) {
            exit(1);
        } else if ($pid) {
            $this->id = $pid;
            return $pid;
        }
        pcntl_signal(SIGINT, function ($signalNumber) {
            $this->logger->debug("process {$this->id} got int signal");
            $this->quit();
            exit(0);
        });
        pcntl_signal(SIGQUIT, function ($signalNumber) {
            $this->logger->debug("process {$this->id} got quit signal");
            $this->graceFullQuit();
        });
        pcntl_signal(SIGTERM, function ($signalNumber) {
            $this->logger->debug("process {$this->id} got terminal signal");
            $this->quit();
            exit(0);
        });
        pcntl_signal(SIGUSR1, function ($sigalNumber) {
            $this->logger->debug("reopen logs");
            $this->reopenLogs();
        });
        $this->id = posix_getpid();
        $this->createdAt = time();

        // TODO: 进程退出后通知
        $this->run();
        exit();
    }

    /**
     * 抽象方法，进程启动成功后调用run方法。
     *
     * @return void
     */
    public function run()
    {
        $this->logger->debug("Worker process on {$this->id}");
    }

    protected function graceFullQuit()
    {
    }

    protected function reopenLogs()
    {

    }

    protected function quit()
    {

    }

    /**
     * @return void
     */
    protected function willTerminalProcess()
    {
    }

    protected function willQuitProcess()
    {

    }

    public function id()
    {
        return $this->id;
    }

    /**
     * 阻塞直到子进程退出
     */
    public function join()
    {
        $status = null;
        pcntl_waitpid($this->id, $status);
    }

}