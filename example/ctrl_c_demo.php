<?php
declare(ticks=1);

pcntl_signal(SIGINT, function($no) {
    $pid = getmypid();
    echo "SIGINT $pid\n";
});

$pid = pcntl_fork();

if ($pid) {
    sleep(60);
} else {
    pcntl_signal(SIGINT, function($no) {
        $pid = getmypid();
        echo "SIGINT $pid\n";
        exit();
    });
    sleep(60);
}