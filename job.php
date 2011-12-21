<?php
/**
 * CronJob主进程文件
 * 
 * @author	hfcorriez@gmail.com
 * @todo	crontab检查进程和重启
 * @todo	制作linux启动脚本
 */
error_reporting(E_ALL & ~E_NOTICE);
define('CRONJOB_START_TIME', time());
define('CRONJOB_START_MEMORY', memory_get_usage(1));
define('CRONJOB_DIR', dirname(__FILE__));
define('CRONJOB_USER', get_current_user());
require CRONJOB_DIR . '/inc/lib.php';

while (true)
{
    write_log('Memory: ' . (memory_get_usage(1) - CRONJOB_START_MEMORY), 'memory');
    
    // update or get files
	clearstatcache();
	$tasks = file(config('task_file'));
	
	// record current time
	$time = time();
	$microtime = floor(microtime(true) * 1000000);
	// commands to run
	$command_hits = array();
	
	foreach ($tasks as $task)
	{
		$task = trim($task);
		// ignore blank line and comment line
		if ($task == '' || $task{0} == '#')
		{
			continue;
		}
		
		// check format
		if (!preg_match('/^((\*|\d+|\d+\-\d+)(\/\d+)? ){6}(.*)$/', $task, $match))
		{
			write_log('Error: format ' . $task);
		}
		
		// get command and cycles
		$command = $match[4];
		$cycles = explode(' ', trim(substr($task, 0, -(strlen($command)))));
		
		// init cycle hits to record task cycle.
		$cycle_hits = array();
		foreach ($cycles as $index => $cycle)
		{
		    // pre chunk
			$is_time_pre = false;
			// sub chunck
			$is_time_sub = false;
			
			list($time_pre, $time_sub) = explode('/', $cycle);
			
			// if pre is *, pre is ok.
			if ($time_pre == '*') {
				$is_time_pre = true;
			}
			// if pre include "-" then star range mode.
			elseif (strpos($time_pre, '-') !== false)
			{
				list($min, $max) = explode('-', $time_pre);
				// min, max must be under rules.
				if(!is_time_range($index, $min) || !is_time_range($index, $max) || $max <= $min)
				{
				    write_log('Error: time pre range ' . $task);
				    break;
				}
				// check range
				if (date('s') >= $min && date('s') <= $max)
				{
					$is_time_pre = true;
				}
				unset($min, $max);
			}
			
			// not exist sub time.
			if (!$time_sub)
			{
				$is_time_sub = true;
				if (!$is_time_pre)
				{
				    // check time range
    				if(!is_time_range($index, $time_pre))
    				{
    				    write_log('Error: time pre range ' . $task);
    				    break;
    				}
    				// if time on then pre is ok.
					if (is_numeric($time_pre) && $time_pre == date('s'))
					{
						$is_time_pre = true;
					}
					else
					{
						break;
					}
				}
				
			}
			else
			{
				if (!$is_time_pre)
				{
					break;
				}
				// check sub range 
			    if(!is_time_range($index, $time_sub))
				{
				    write_log('Error: time sub range ' . $task);
				    break;
				}

				$time_sub = (int) $time_sub;
				// check every cycle
				switch ($index)
				{
					case 0:
					    // second check.
						if (($time - CRONJOB_START_TIME) % $time_sub == 0)
						{
							$is_time_sub = true;
						}
						break;
					case 1:
					    // minutes check
						if (floor(($time - CRONJOB_START_TIME)/60) % $time_sub == 0)
						{
							$is_time_sub = true;
						}
						break;
					case 2:
					    // hour check
						if (floor(($time - CRONJOB_START_TIME)/3600) % $time_sub == 0)
						{
							$is_time_sub = true;
						}
						break;
					case 3:
					    // day check
						if (floor(($time - CRONJOB_START_TIME)/86400) % $time_sub == 0)
						{
							$is_time_sub = true;
						}
						break;
					case 4:
					    // month check
						$date1 = explode('-', date('Y-m', CRONJOB_START_TIME));
						$date2 = explode('-', date('Y-m', $time));
						$month = abs($date1[0] - $date2[0]) * 12 + abs($date1[1] - $date2[1]);
						if ($month & $time_sub == 0)
						{
							$is_time_sub = true;
						}
						unset($date1, $date2, $month);
						break;
					case 5:
					    // week check
						if (floor(($time - CRONJOB_START_TIME)/86400/7) % $time_sub == 0)
						{
							$is_time_sub = true;
						}
						break;
				}
			}
			
			// pre and sub is ok, then hit one
			if($is_time_pre && $is_time_sub)
			{
				$cycle_hits[$index] = 1;
			}
			else 
			{
				break;
			}
			
			unset($time_pre, $time_sub, $is_time_pre, $is_time_sub);
		}
		
		// run command in pip mode when every cycle is hit.
		if(array_sum($cycle_hits) == 6)
		{
		    $command_hits[] = $command;
		}
		
		unset($cycles, $cycle_hits, $match, $command);
	}
	
	foreach ($command_hits as $key => $command)
	{
	    $command_hits[$key] = '"' . str_replace('"', '\\"', $command) . '"';
	    write_log("<" . CRONJOB_USER . "> <{$command}>", 'job');
	}
	
	pipe_shell(config('php_runtime') . ' ' . CRONJOB_DIR . '/work.php ' . join(' ', $command_hits));
		
	// check sleep time and do sleep
	$sleep_time = 1000000 - floor(microtime(true)*1000000) + $microtime;
	if($sleep_time > 0)
	{
	    usleep($sleep_time);
	}
	//echo $sleep_time . PHP_EOL;
	unset($sleep_time, $time, $microtime, $tasks, $command_hits);
}