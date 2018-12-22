<?php

namespace pl125\cron\command;

use Jenssegers\Date\Date;
use think\console\Command;
use think\console\Input;
use think\console\Output;
use pl125\cron\Task;
use pl125\cron\library\FileCache;

/**
 * 执行类
 *
 * Class Run
 * @package pl125\cron\command
 */
class Run extends Command
{
    /**
     * 开始时间
     *
     * @var
     */
    protected $startedAt;

    /**
     * 初始化配置
     */
    protected function configure()
    {
        $this->startedAt = Date::now();
        $this->setName('cron:run');
    }

    /**
     * 执行
     * @param Input $input
     * @param Output $output
     * @return int|null|void
     */
    public function execute(Input $input, Output $output)
    {
        // 获取配置任务列表
        $tasks = config('cron.tasks');

        foreach ($tasks as $taskClass) {

            if (is_subclass_of($taskClass, Task::class)) {

                /**
                 * 实例化任务类
                 */
                $task = new $taskClass();

                if ($task->isDue()) {

                    if (!$task->filtersPass()) {
                        continue;
                    }

                    if ($task->onOneServer) {
                        $this->runSingleServerTask($task);
                    } else {
                        $this->runTask($task);
                    }

                    $output->writeln("Task {$taskClass} run at " . Date::now());
                }

            }
        }
    }

    /**
     * 服务运行
     *
     * @param $task
     * @return bool
     */
    protected function serverShouldRun($task)
    {
        $key   = $task->mutexName() . $this->startedAt->format('Hi');
        $cache = FileCache::getInstance();
        if ($cache->has($key)) {
            return false;
        }
        $cache->set($key, true, 60);
        return true;
    }

    /**
     * 单服务器执行任务
     *
     * @param $task
     */
    protected function runSingleServerTask($task)
    {
        if ($this->serverShouldRun($task)) {
            $this->runTask($task);
        } else {
            $this->output->writeln('<info>Skipping task (has already run on another server):</info> ' . get_class($task));
        }
    }

    /**
     * 执行任务
     *
     * @param $task
     */
    protected function runTask($task)
    {
        $task->run();
    }
}