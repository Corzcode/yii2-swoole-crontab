<?php
namespace yii\crontab;

use yii\console\Controller;

class Command extends Controller
{

    public $pidfile = "";

    public function options($actionID)
    {
        return [
            'pidfile'
        ];
    }

    /**
     * [服务化启动]
     */
    public function actionStart()
    {
        if (! empty($this->pidfile)) {
            Cli::setOpt("pid-file", $this->pidfile);
        }
        Cli::setOpt("d", true);
        $serv = new Init();
        $serv->exec();
    }

    /**
     * [普通启动输出到控制台]
     */
    public function actionRun()
    {
        $serv = new Init();
        $serv->exec();
    }
}