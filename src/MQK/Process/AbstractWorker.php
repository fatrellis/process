<?php
namespace MQK\Process;

abstract class AbstractWorker implements Worker
{
    /**
     * 进程ID
     * @var int
     */
    protected $id;

    /**
     * 是否存活，子类应该处理这个标记。
     * @var bool
     */
    protected $alive = true;

    /**
     * @var int
     */
    protected $createdAt = 0;

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
    }

    /**
     * 非平滑退出进程。
     *
     * @return void
     */
    protected function signalQuitHandle()
    {
        echo "Process {$this->id} got quit signal\n";
        $this->alive = false;
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
        $pid = getmypid();
        echo "Process {$pid} got terminal signal\n";
        $this->alive = false;

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