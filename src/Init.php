<?php
namespace yii\crontab;

use yii\console\Request;
use yii\console\Response;
use Yii;

class Init
{

    /**
     * 自动任务配置
     *
     * @var array
     */
    protected $cron = array();



    /**
     * 是否reload
     *
     * @var bool
     */
    protected $isReload = true;

    /**
     * 是否结速
     *
     * @var bool
     */
    protected $isQuit = false;

    /**
     * 进程执行时间
     *
     * @var array
     */
    protected $runTime = [];

    /**
     * 进程计数
     *
     * @var array
     */
    protected $pids = [];

    /**
     * 进程状态
     *
     * @var array
     */
    protected $process = [];
    
    

    /**
     * Handles the specified request.
     *
     * @param Request $request
     *            the request to be handled
     * @return Response the resulting response
     */
    public function handleCronRequest($route, $params)
    {
        $this->requestedRoute = $route;
        $result = Yii::$app->runAction($route, $params);
        if ($result instanceof Response) {
            return $result;
        }

        $response = Yii::$app->getResponse();
        $response->exitStatus = $result;

        return $response;
    }

    /**
     * 执行应用程序
     *
     * @access public
     * @return void
     */
    public function exec()
    {
        Cli::init();
        swoole_async_set([
            'enable_coroutine' => false
        ]);
        $this->loadCronConfig();
        // 配置控制
        self::registerSignal();


        Yii::$app->db->close();

        Yii::$app->cache->redis && Yii::$app->cache->redis->close();
        echo "Time : " . date('H:i:s') . "\t======== COND PROCESS START ========\n";
        \swoole_timer_tick(1000, function () {
            // 每次reload清空下次执行时间
            if ($this->isReload) {
                $this->isReload = false;
                $this->runTime = array();
            }

            if ($this->isQuit) {
                return;
            }
            //echo "memory use " . memory_get_usage() . " times" . $this->i ++ . "\n";
            $time = time();

            // 循环配置，计算任务是否需要执行
            foreach ($this->cron as $cron => $conf) {
                // 进程数
                $conf['process'] = ! empty($conf['process']) ? $conf['process'] : 1;
                for ($i = 0; $i < $conf['process']; $i ++) {
                    // 初始时间到上次整点
                    $pidName = "{$cron}_{$i}";
                    ! isset($this->runTime[$pidName]) && $this->runTime[$pidName] = $this->getRunTime($conf);

                    // 执行
                    if ($time >= $this->runTime[$pidName]) {
                        $this->runTime[$pidName] = $this->getRunTime($conf);
                        if (! $this->fork($cron, $i, $conf)) {}
                    }
                }
            }
        });
        return;
    }

    /**
     * 检查时间是否运行
     *
     * @param array $time
     * @param
     *            int
     */
    protected function getRunTime(array $conf)
    {
        $timestamp = time();
        if (isset($conf['interval'])) {
            return ParseInterval::parse($conf, $timestamp);
        } elseif (isset($conf['crontab'])) {
            $time = ParseCrontab::parse($conf, $timestamp);
            return $time;
        }
        return 9999999999;
    }

    protected function fork($cron, $id, $conf)
    {
        // print_r($_SERVER['_'] . $_SERVER['SCRIPT_NAME']);
        $pidName = "{$cron}_{$id}";
        if (! empty($this->pids[$pidName])) {
            echo "Time : " . date('H:i:s') . "\t\tPID : 0\tCron : {$cron} - {$id} is run\n";
            return true;
        }
        $process = new \Swoole\Process(function (\Swoole\Process $process) use ($cron, $id, $conf, $pidName) {

            $header = "Time : " . date('H:i:s') . "\t\tPID : " . posix_getpid() . "\tCron - {$cron} - {$id}/{$conf['process']} START\n";
            echo $header;
            $log = "";
            if (! empty($conf['log'])) {
                $log = Yii::getAlias($conf['log']);
            }
            ob_start();

            $processName = "YiiCrontab - {$cron} : {$id}";
            // echo "----------$pidName-----------\n";
            // echo ">> in process >> $pidName \n";
            $process->name("$processName [" . date("d H:i:s") . "]");

            $this->handleCronRequest($cron, [
                $id,
                $conf['process']
            ]);

            $out = ob_get_contents();

            ob_end_clean();
            $footer = "Time : " . date('H:i:s') . "\t\tPID : " . posix_getpid() . "\tCron - {$cron} - {$id}/{$conf['process']} DONE\n";
            if (! empty($log)) {
                file_put_contents($log, $header . $footer . $out . "\n", FILE_APPEND);
                $out = "  Log save to $log\n";
            }
            echo $footer . $out . "\n";
            // });
        });
        // $process->id = $i;
        $pid = $process->start();
        $this->pids[$pidName] = $pid;
        $this->process[$pid] = $process;

        return $pid;
    }

    /**
     * 引入任务配置
     */
    protected function loadCronConfig()
    {
        // 引入定时任务配置
        // echo Yii::$app->basePath;
        $this->cron = include Yii::$app->basePath . '/config/cron.php';
        if (! empty($task = Cli::getOpt('t'))) {
            $cron = [];
            foreach (explode(',', $task) as $t) {
                isset($this->cron[$t]) && $cron[$t] = $this->$cron[$t];
            }
            $this->cron = $cron;
        }
        $this->isReload = true;
    }

    /**
     * 获取任务配置
     */
    public function getConfig($key = '')
    {
        if (! empty($key) && isset(self::$cron[$key])) {
            return self::$cron[$key];
        }
        return self::$cron;
    }

    /**
     * 回收进程
     */
    protected function registerSignal()
    {
        \Swoole\Process::signal(SIGCHLD, function ($signo) {
            // 表示子进程已关闭，回收它
            while (false !== ($status = \Swoole\Process::wait(false))) {
                $pid = $status['pid'];
                $key = array_search($pid, $this->pids);
                if (! empty($key)) {
                    unset($this->pids[$key]);
                }
                if (! empty($this->process[$pid])) {
                    $this->process[$pid]->close();
                    unset($this->process[$pid]);
                }
                // . count($this->pids) . count($this->process)
                // echo "SIGCHLD WORKER#:{$status['pid']} $key " . " EXIT\n";
            }
        });

        \Swoole\Process::signal(SIGUSR1, function ($signo) {
            echo "Time : " . date('H:i:s') . " CRON reload config\n";
            $this->loadCronConfig();
            echo json_encode($this->cron), "\n";
        });

        // kill
        \Swoole\Process::signal(SIGTERM, function ($signo) {
            echo "Time : " . date('H:i:s') . " CRON STOP GO  !!!\n";
            if (! empty($this->pids)) {
                $this->isQuit = true;
                var_dump($this->pids);
            }
            echo "Time : " . date('H:i:s') . " CRON STOP DONE !!!\n";

            exit();
        });
    }
}

