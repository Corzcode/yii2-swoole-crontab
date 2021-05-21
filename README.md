Installation
------------

The preferred way to install this extension is through [composer](http://getcomposer.org/download/):

```
php composer.phar require corzcode/yii2-swoole-crontab dev-master
```



Basic Usage
-----------
main.php add config

```php
return [
    'bootstrap' => [
        'cron', // 自动任务系统
    ],  
    'components' => [
        'cron' => [
            'class' => 'yii\crontab\Crontab',
            'config'=>[                
                'user' => 'www',
                'group' => 'www',
                'pid-file' => '@runtime/cron.pid',
                'log-file' => '@runtime/cron/run_cron.log'
            ],
            'cronfile' => __DIR__ . "/cron.php"
        ],
    ]
]
    
```

add cron.php file

```php
<?php
/**
 * 自动任务配置 以时间整点计算
 * 'cron名称'=> array('interval' => 执行时间秒整数, 'offset'=> 时间偏移值秒整数, 'process' => 进程数);
 *             时间起点为周一  每周二执行可以设'interval' => 86400*7, 'offset'=> 86400*2
 * 'cron名称'=> array('crontab' =>
 *     '0  1  2  3  4  5');
 *      *  *  *  *  *  *
 *      |  |  |  |  |  |
 *      |  |  |  |  |  +------ day of week (0 - 6) (Sunday=0)
 *      |  |  |  |  +--------- month (1 - 12)
 *      |  |  |  +------------ day of month (1 - 31)
 *      |  |  +--------------- hour (0 - 23)
 *      |  +------------------ min (0 - 59)
 *      +--------------------- sec (0-59)
 * 不支援week day同时设定
 */
return array(
    'test' => array('interval' =>5, 'process' =>2),
    'test/do' => array('crontab' => "* * * * * *", 'process' =>10, 'log'=>'@runtime/cron/run_test_do.log'),
);
```

Cron controller

```php
namespace console\controllers;

use yii\console\Controller;
use Yii;

class TestController extends Controller
{
	 //$num is process num  $id is process id
    public function actionIndex($id = 0, $num = 1)
    {}
    public function actionDo($id = 0, $num = 1)
    {}
}
```

how to run

```sh
yii cron/run
```

service.sh

```sh
#!/bin/sh

PHPBIN=/path/to/php
PHPCLICONF=/path/to/php.ini

script_dir=$( cd $(dirname $0); pwd)
pidpath="$script_dir/console/runtime"

helptxt="Usage: $0 cron {start|stop|reload|restart}"

pidfile="$pidpath/$1.pid"
cd $script_dir

cmd="yii cron/start"

case "$2" in
    start)
        echo "Starting CLI $1 server "
        $PHPBIN -c $PHPCLICONF $cmd --pidfile=${pidfile} 
        ;;
    stop)
        PID=`cat "${pidfile}"`
        echo "Stopping CLI $1 server"
        if [ ! -z "$PID" ]; then
            kill -15 $PID
               (( $? == 0 )) && echo -n '' > ${pidfile}
	       [ "$1" == "mq" ] && sleep 5
        fi
        ;;
    reload)
        echo "Reload CLI $1 server"
        PID=`cat "${pidfile}"`
        if [ ! -z "$PID" ]; then
           kill -10 $PID
        fi   
        ;;
    restart)
        $0 $1 stop
        $0 $1 start
        ;;
     *)
        echo $helptxt
        exit 1
esac
exit 0

```



