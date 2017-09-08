<?php
namespace MQK\Process;


class MasterProcess
{
    /**
     * self pipe
     *
     * @var int[]
     */
    private $pipe;

    /**
     * Signal queue
     *
     * @var array
     */
    private $signals = [];

    /**
     * 当前状态为退出
     *
     * @var boolean
     */
    private $quiting = false;

    /**
     * Worker list
     *
     * @var AbstractWorker[]
     */
    protected $workers = [];

    /**
     * 强制退出
     *
     * @var boolean
     */
    protected $forceQuit = false;

    /**
     * 开启的进程数量
     *
     * @var integer
     */
    protected $processes = 5;

    /**
     * @var string
     */
    protected $workerClass;

    /**
     * 信号和信号执行方法的映射
     *
     * @var array
     */
    private $signalMappingToMethod = [
        SIGCHLD => "signalChildHandle",
        SIGINT => "signalTerminalHandle",
        SIGTTIN => "signalIncreaseHandle",
        SIGTTOU => 'signalDecreaseHandle',
        SIGHUP => 'signalReloadHandle',
        SIGQUIT => 'signalQuitHandle'
    ];

    public function __construct($workerClass, $processes = 5)
    {
        $this->workerClass = $workerClass;
        $this->processes = $processes;
    }

    /**
     * 开始启动主进程
     *
     * @return void
     */
    public function run()
    {
        $pid = getmypid();
        echo "Master process {$pid}\n";
        $this->spawn();

        $this->pipe = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);

        stream_set_blocking($this->pipe[0], true);
        stream_set_blocking($this->pipe[1], true);

        foreach (array_keys($this->signalMappingToMethod) as $signal)
            pcntl_signal($signal, array(&$this, "signalHandle"));

        while (true) {
            $r = [$this->pipe[1]];
            $w = [];
            $e = [];

            try {
                $this->select($r, $w, $e, 1.0);
            } catch (\Exception $e) {
                echo $e->getMessage() . "\n";
                $this->dispatch_signals();
            }
        }
    }

    /**
     * 默认信号处理器
     *
     * 该处理器将信号队列
     *
     * @param int $signo
     * @return void
     */
    function signalHandle($signo)
    {
        echo "Signal {$this->signalMappingToMethod[$signo]}\n";
        $this->signals[] = $signo;
//        fwrite($this->pipe[0], '.');
    }

    /**
     * SIGCHLD 信号处理器
     *
     * @return void
     */
    function signalChildHandle()
    {
        echo "Signal child handle\n";
        if ($this->quiting)
            echo "Application quiting in signal child\n";
        $this->reap();
        if (!$this->quiting) {
            echo "spawn worker\n";
            $this->spawn();
        }
    }

    /**
     * SIGTERM 信号处理器
     *
     * 平滑退出进程。两次按 Ctrl+C 则强制退出。
     *
     * @return void
     */
    function signalTerminalHandle()
    {
        echo "Signal terminal\n";
        if ($this->forceQuit) {
            echo "Force quit\n";
            $this->stop(false);
        } else {
            $this->forceQuit = true;
            $this->stop(true);
        }
    }

    /**
     * SIGQUIT 信号处理器
     *
     * 强制退出进程
     *
     * @return void
     */
    function signalQuitHandle()
    {
        $this->stop(false);
    }

    /**
     * 新增子进程
     *
     * @return void
     */
    function signalIncreaseHandle()
    {
        $this->processes += 1;
        $this->spawn();
    }

    /**
     * 减少子进程
     *
     * @return void
     */
    function signalDecreaseHandle()
    {
        if ($this->processes <= 1)
            return;
        $this->processes -= 1;
        $this->manageWorkers();
    }

    /**
     * 重新启动进程
     *
     * @return void
     */
    function signalReloadHandle()
    {

    }

    /**
     * 执行信号队列
     *
     * @return void
     */
    function dispatch_signals()
    {
        $signalsExported = join(" ", $this->signals);
        echo "dispatch signals $signalsExported\n";
        while ($signalNumber = array_shift($this->signals)) {
            $handleFunction = $this->signalMappingToMethod[$signalNumber];
            call_user_func([&$this, $handleFunction], [$signalNumber]);
        }
    }

    /**
     * REAP进程防治产生僵尸进程
     *
     * @return void
     */
    function reap()
    {
        for ($i = 0; $i < 100; $i++) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
            if (-1 == $pid) {
                break;
            } else if ($pid > 0) {
                echo "Reaped process {$pid}\n";
                $this->removeWorkerById($pid);
                continue;
            }
            echo "waitpid return pid is 0\n";
            break;
        }
    }

    /**
     * 启动新进程
     *
     * @return void
     */
    public function spawn()
    {
        $needToStart = $this->processes - count($this->workers);
        echo "will start {$needToStart} process\n";
        for ($i = 0; $i < $needToStart; $i++) {
            $this->spawnWorker();
        }
    }

    /**
     * 启动一个新进程
     *
     * @return void
     */
    public function spawnWorker()
    {
        $worker = new $this->workerClass();
        $worker->start();
        $this->workers[] = $worker;
    }

    /**
     * 杀掉多余的进程
     *
     * @return void
     */
    function manageWorkers()
    {
        $workers = $this->workers;
        while ($this->processes < count($workers)) {
            $worker = array_shift($workers);
            $this->kill($worker->id(), SIGTERM);
        }
    }

    public function select($r, $w, $e, $t)
    {
        set_error_handler(array(&$this, "error_handler"));

        try {
            $nums = stream_select($r, $w, $e, $t);
        } catch (\Exception $e) {
            pcntl_signal_dispatch();
            throw $e;
        } finally {
            restore_error_handler();
        }
    }

    function error_handler($errno, $errstr, $errfile, $errline, $errcontext = null)
    {
        $last_error = compact('errno', 'errstr', 'errfile', 'errline', 'errcontext');

        // fwrite notice that the stream isn't ready
        if (strstr($errstr, 'Resource temporarily unavailable')) {
            // it's allowed to retry
            return;
        }
        // stream_select warning that it has been interrupted by a signal
        if (strstr($errstr, 'Interrupted system call')) {
            throw new \Exception("Interrupted system call");
            // it's allowed while processing signals
            return;
        }
        // raise all other issues to exceptions
        throw new \Exception($errstr, 0, $errno, $errfile, $errline);
    }

    /**
     * 移除子进程
     *
     * @param int $id 进程ID
     * @return void
     */
    function removeWorkerById($id)
    {
        echo "Remove worker by id is $id\n";
        $found = -1;
        foreach ($this->workers as $i => $worker) {
            if ($worker->id() == $id) {
                $found = $i;
                break;
            }
        }

        if ($found > -1) {
            $worker = $this->workers[$found];
            echo "Removed worker by id is {$worker->id()}\n";

            unset($this->workers[$found]);
        }
    }

    /**
     * 停止所有进程并退出
     *
     * @param boolean $graceful
     * @return void
     */
    public function stop($graceful = false)
    {
        if ($graceful)
            echo "application graceful quit\n";
        else
            echo "application quit";
        $this->quiting = true;
        $signal = $graceful ? SIGTERM : SIGQUIT;
        $limit = time() + 10;

        $this->killall($signal);

        while (time() < $limit) {
            $this->dispatch_signals();
            if (count($this->workers) == 0) {
                break;
            }
            // 偶尔存在SIGCHLD 没有REAP到的进程
            $this->reap();
            usleep(100000);
        }

        $this->killall(SIGKILL);

//        $this->logger->info("MasterProcess process quit.");
        exit(0);
    }

    /**
     * 杀掉所有进程
     *
     * @param int $signal 使用的信号
     * @return void
     */
    protected function killall($signal)
    {
        $signalAction = $signal == SIGTERM ? "exit" : "quit";
        /**
         * @var $worker Worker
         */
        foreach ($this->workers as $worker) {

            $this->kill($worker->id(), $signal);
        }
    }

    /**
     * 杀掉单一进程
     *
     * @param int $pid
     * @param int $signal
     * @return void
     */
    protected function kill($pid, $signal)
    {
        echo "{$signal} process {$pid}\n";
        // $this->logger->info("{$signalAction} process {$worker->id()}");
        if (!posix_kill($pid, $signal)) {
            // $this->logger->error("{$signalAction} process failure {$worker->id()}");
        }
    }
}