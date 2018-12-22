<?php
/**
 * Created by PhpStorm.
 * User: Administrator
 * Date: 2018/12/22
 * Time: 18:08
 */

namespace pl125\task\library;

/**
 * 文件类型缓存类
 *
 * Class Cache
 */
class FileCache extends \think\cache\driver\File
{
    /**
     * 实例
     *
     * @var null
     */
    protected static $instance = null;

    /**
     * 架构函数
     *
     * @param array $options
     */
    public function __construct($options = [])
    {
        if (!empty($options)) {
            $this->options = array_merge($this->options, $options);
        }

        if (empty($this->options['path'])) {
            $path                  = config('cron.cachedir');
            $this->options['path'] = $path;
        } elseif (substr($this->options['path'], -1) != DIRECTORY_SEPARATOR) {
            $this->options['path'] .= DIRECTORY_SEPARATOR;
        }

    }

    /**
     * 获取实例
     *
     * @param array $option
     * @return FileCache|null
     */
    public static function getInstance($option = [])
    {
        if (is_null(self::$instance)) {
            self::$instance = new static($option);
        }
        return self::$instance;
    }
}