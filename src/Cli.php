<?php
namespace yii\crontab;
use Yii;
/**
 * CLI入口控制器 包括 -d 守护
 *
 * @author 林志刚<linzg@jingxuansugou.com>
 * @since 2015年12月17日
 * @version 1.0
 */
class Cli
{

    public static $opt = [];

    /**
     * 初始化
     */
    public static function init()
    {
        $pidFile = self::getOpt('pid-file');
        if (self::getOpt('d')) {
            self::checkIsRun($pidFile);
            $logFile = self::getOpt('log-file');
            if(!file_exists($logFile)){
                file_put_contents($logFile, ""); 
            }
            $fp = fopen($logFile, 'a');
            \Swoole\Process::daemon(1, 1, [null, $fp, $fp]);
            $pid = posix_getpid();
            if (! empty($pidFile)) {
                echo "write pid $pidFile , $pid \n";
                file_put_contents($pidFile, $pid);
            }
        } /*
           * elseif (! empty($pidFile)) {
           * file_put_contents($pidFile, posix_getpid());
           * }
           */
        $group = self::getOpt('group');
        if (! empty($group)) {
            $groupinfo = posix_getgrnam($group);
            posix_setgid($groupinfo['gid']);
        }
        $user = self::getOpt('user');
        if (! empty($user)) {
            $userinfo = posix_getpwnam($user);
            posix_setuid($userinfo['uid']);
        }
    }

    /**
     * 获取opt
     *
     * @param string $key
     * @return mixed
     */
    public static function getOpt($name = '')
    {
        if (empty(self::$opt)) {
            $shortopts = 'dt:';
            $longopts = array(
                'user:',
                'group:',
                'pid-file:'
            );
            $opt = getopt($shortopts, $longopts);
            self::$opt['d'] = $opt['d'] = isset($opt['d']);
            $conf = Yii::$app->cron->config;
            if (! empty($conf)) {
                foreach ($conf as $key => $value) {
                    self::$opt[$key] = isset($opt[$key]) ? $opt[$key] : Yii::getAlias($value);
                }
            }
        }
        return isset(self::$opt[$name]) ? self::$opt[$name] : null;
    }

    /**
     * 设置选项
     *
     * @param string $key
     * @param string $var
     */
    public static function setOpt($key, $var)
    {
        if (self::getOpt($key) === null) {
            return;
        }
        self::$opt[$key] = $var;
    }

    /**
     * 检查进程是否存在
     *
     * @param string $pidFile
     */
    public static function checkIsRun($pidFile)
    {
        if (empty($pidFile)) {
            return;
        }
        if (! file_exists($pidFile)) {
            return;
        }
        $pid = file_get_contents($pidFile);
        $pid = intval($pid);
        if (empty($pid)) {
            return;
        }
        $isrun = exec("ps ax | grep $pid | grep php | grep -v grep | awk '{print$1}'");
        if (! empty($isrun)) {
            exit("$isrun is run!\n");
        }
    }
}
