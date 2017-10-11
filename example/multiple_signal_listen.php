<?php
declare(ticks=1);

pcntl_signal(SIGINT, function($no) {
	echo "INT";
	sleep(2);
	exit();
});
pcntl_signal(SIGINT, function($no) {
	echo "INT 2";
	sleep(2);
	exit();
});
sleep(100);