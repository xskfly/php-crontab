<?php

namespace CronTab;

class CronTab
{
    protected $start_time;
    protected $executor;

    protected $config = array();
    protected $tasks = array();

    /**
     * Constructor
     *
     * @param $config
     * @throws \Exception
     */
    public function __construct($config)
    {
        $this->config = $config;
        $this->start_time = time();
    }

    /**
     * Register executor for execute
     *
     * @param $executor
     */
    public function registerExecutor($executor)
    {
        $this->executor = $executor;
    }

    /**
     * Start to run
     */
    public function start()
    {
        while (true) {
            // Load tasks every time.
            $this->tasks = $this->getAdapter()->getTasks();

            // record current time
            $micro_time = floor(microtime(true) * 1000000);

            // commands to run
            $command_hits = array();

            foreach ($this->tasks as $task) {
                list($rule, $command) = $task;
                if (CronLib::isValid($rule, $this->start_time)) {
                    $command_hits[] = $command;
                }
            }

            foreach ($command_hits as $key => $command) {
                $command_hits[$key] = base64_encode($command);
                // $this->getLogger()->write("<{$command}> dispatch.");
            }

            if ($command_hits) $this->dispatch(join(' ', $command_hits));

            // check sleep time and do sleep
            $current_time = microtime(true);
            $sleep_time = 1000000 - floor((microtime(true) - floor($current_time)) * 1000000);
            if ($sleep_time > 0) {
                usleep($sleep_time);
            }

            unset($sleep_time, $micro_time, $tasks, $command_hits, $current_time);
        }
    }

    /**
     * Dispath command
     *
     * @param $command
     */
    public function dispatch($command)
    {
        CronLib::pipeShell($this->executor . ' ' . $command);
    }

    /**
     * Execute command
     *
     * @param array $commands
     * @return mixed
     */
    public function execute($command)
    {
        $command = base64_decode($command);
        if (!$command) $this->getLogger()->log('<0> <Invalid command!>');
        $stdout = $stderr = null;

        $start_time = microtime(true);
        $start_memory = memory_get_usage();

        $status = CronLib::shell($command, $stdout, $stderr, (int)$this->config['crontab']['timeout']);

        $process_time = number_format(microtime(true) - $start_time, 3);
        $process_memory = (memory_get_usage() - $start_memory) / 1000;

        $this->getLogger()->log("({$process_time}s) ({$process_memory}k) <{$command}> <{$status}> " . ($stderr ? 'error!' : ''));

        $this->getReporter()->report(array(
            'start_time'     => date('Y-m-d H:i:s', $start_time),
            'command'        => $command,
            'process_memory' => $process_memory,
            'process_time'   => $process_time,
            'status'         => $status,
            'stdout'         => $stdout,
            'stderr'         => $stderr
        ));
    }

    /**
     * Get logger
     *
     * @return Logger
     */
    public function getLogger()
    {
        static $instance = null;

        if ($instance === null) {
            $instance = new Logger($this->config['log']);
        }

        return $instance;
    }

    /**
     * Get Adapter
     *
     * @return Adapter
     * @throws \Exception
     */
    public function getAdapter()
    {
        static $instance = null;

        if ($instance === null) {
            if (!$conf = $this->config['crontab']['adapter']) {
                throw new \Exception("Unkown adapter mode: {$this->config['crontab']['mode']}.");
            }
            $class = '\\' . __NAMESPACE__ . '\\Adapter\\' . ucfirst($this->config[$conf]['mode']);
            $instance = new $class($this->config[$conf]);
        }

        return $instance;
    }

    /**
     * Get reporter
     *
     * @return Reporter
     * @throws \Exception
     */
    public function getReporter()
    {
        static $instance = null;

        if ($instance === null) {
            if (!$conf = $this->config['crontab']['reporter']) {
                throw new \Exception("Unkown reporter mode: {$this->config['crontab']['reporter']}.");
            }
            $class = '\\' . __NAMESPACE__ . '\\Reporter\\' . ucfirst($this->config[$conf]['mode']);
            $instance = new $class($this->config[$conf]);
        }

        return $instance;
    }
}