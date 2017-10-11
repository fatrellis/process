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
        pcntl_signal(SIGINT, array(&$this, 'signalIntHandle'));
        pcntl_signal(SIGQUIT, array(&$this, "signalQuitHandle"));
        pcntl_signal(SIGTERM, array(&$this, "signalTerminalHandle"));
//        pcntl_signal(SIGUSR1, array(&$this, "signalUsr1Handler"));
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
        $this->logger->info("Worker process on {$this->id}");
    }

    /**
     * 非平滑退出进程。
     *
     * @return void
     */
    protected function signalQuitHandle()
    {
        $this->logger->info("Process {$this->id} got quit signal");
        $this->quit();
        usleep(1000);
        exit(0);
    }

    /**
     * 平滑退出进程
     *
     * @param int $signo
     * @return void
     */
    protected function signalTerminalHandle($signo)
    {
        $this->logger->info("Process {$this->id} got terminal signal");
        $this->quit();
    }

    protected function signalIntHandle($signo)
    {
        $this->logger->info("Process {$this->id} got int signal");
        $this->quit();
        usleep(1000);
        exit(0);
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

    protected abstract function quit();
}