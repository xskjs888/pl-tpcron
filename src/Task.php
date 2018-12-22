<?php

namespace pl125\cron;

use Closure;
use Cron\CronExpression;
use Jenssegers\Date\Date;
use pl125\cron\traits\ManagesFrequencies;
use pl125\cron\library\FileCache;

/**
 * 任务抽象类
 *
 * Class Task
 * @package app\task
 */
abstract class Task
{
    /**
     * 引入trait类
     */
    use ManagesFrequencies;

    /**
     * 时区
     * @var
     */
    public $timezone;

    /**
     * 任务周期
     *
     * @var string
     */
    public $expression = '* * * * * *';

    /**
     * 任务不可以重叠执行
     *
     * @var bool
     */
    public $withoutOverlapping = false;

    /**
     * 最大执行时间(重叠执行检查用)
     *
     * @var int
     */
    public $expiresAt = 1440;

    /**
     * 分布式部署 是否仅在一台服务器上运行
     */
    public $onOneServer = false;

    /**
     * 过滤方法数组
     * @var array
     */
    protected $filters = [];

    /**
     * 拒绝方法数组
     * @var array
     */
    protected $rejects = [];

    /**
     * 缓存实例
     *
     * @var null
     */
    protected $cache = null;

    /**
     * 架构方法
     *
     * Task constructor.
     */
    public function __construct()
    {
        $this->cache = FileCache::getInstance();
        $this->configure();
    }

    /**
     * 是否到期执行
     * @return bool
     */
    public function isDue()
    {
        $date = Date::now($this->timezone);

        return CronExpression::factory($this->expression)->isDue($date->toDateTimeString());
    }

    /**
     * 配置任务
     */
    protected function configure()
    {
    }

    /**
     * 执行任务
     * @return mixed
     */
    abstract protected function execute();

    /**
     * 执行
     */
    final public function run()
    {
        if ($this->withoutOverlapping &&
            !$this->createMutex()) {
            return;
        }

        register_shutdown_function(function () {
            $this->removeMutex();
        });

        try {
            $this->execute();
        } finally {
            $this->removeMutex();
        }

    }

    /**
     * 过滤
     * @return bool
     */
    public function filtersPass()
    {
        foreach ($this->filters as $callback) {
            if (!call_user_func($callback)) {
                return false;
            }
        }

        foreach ($this->rejects as $callback) {
            if (call_user_func($callback)) {
                return false;
            }
        }

        return true;
    }

    /**
     * 任务标识
     */
    public function mutexName()
    {
        return 'task-' . sha1(static::class);
    }

    /**
     * 移除任务标识
     *
     * @return mixed
     */
    protected function removeMutex()
    {
        return $this->cache->rm($this->mutexName());
    }

    /**
     * 创建任务标识
     *
     * @return bool
     */
    protected function createMutex()
    {
        $name = $this->mutexName();
        if (!$this->cache->has($name)) {
            $this->cache->set($name, true, $this->expiresAt);
            return true;
        }
        return false;
    }

    /**
     * 检查任务标识是否存在
     *
     * @return mixed
     */
    protected function existsMutex()
    {
        return $this->cache->has($this->mutexName());
    }

    /**
     * 执行过滤数组中方法
     *
     * @param Closure $callback
     * @return $this
     */
    public function when(Closure $callback)
    {
        $this->filters[] = $callback;

        return $this;
    }

    /**
     * 执行拒绝数组中的方法
     *
     * @param Closure $callback
     * @return $this
     */
    public function skip(Closure $callback)
    {
        $this->rejects[] = $callback;

        return $this;
    }

    /**
     * 设置任务不可重叠执行
     *
     * @param int $expiresAt
     * @return Task
     */
    public function withoutOverlapping($expiresAt = 1440)
    {
        $this->withoutOverlapping = true;

        $this->expiresAt = $expiresAt;

        return $this->skip(function () {
            return $this->existsMutex();
        });
    }

    /**
     * 分布式时，设置在一个服务器执行定时任务
     *
     * @return $this
     */
    public function onOneServer()
    {
        $this->onOneServer = true;

        return $this;
    }
}