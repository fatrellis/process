<?php
declare(ticks=1)
include __DIR__ . "/../vendor/autoload.php";

class Worker extends \MQK\Process\AbstractWorker
{
    public function run()
    {
        parent::run();
        sleep(1000);
    }

    public function quit()
    {
        $this->logger->info("worker quitting.");
    }
}

$master = new \MQK\Process\MasterProcess(Worker::class);
$master->run();