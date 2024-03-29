<?php
namespace yii\crontab;

/**
 * 解析Interval配置
 *
 */
class ParseInterval
{

    /**
     * 1420387200代表2015-1-5 00:00:00号星期一作为偏移值的始点
     *
     * @var int
     */
    const TIME_OFFSET = 1420387200;

    /**
     * 解析自动任务
     * 返回下次执行的时间戳
     *
     * @param array $conf
     * @param int $timestamp
     * @return int
     */
    public static function parse(array $conf, $timestamp = null)
    {
        $offsetTime = self::TIME_OFFSET;
        isset($conf['offset']) && $offsetTime += $conf['offset'];
        $time = $timestamp - $offsetTime;
        $time = $offsetTime + $time - ($time % $conf['interval']) + $conf['interval'];
        return $time;
    }
}