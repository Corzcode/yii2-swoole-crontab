<?php
namespace yii\crontab;

use Yii;
use yii\base\Component;
use yii\helpers\Inflector;
use yii\console\Application as ConsoleApp;
use yii\base\BootstrapInterface;
use yii\base\InvalidConfigException;

class Crontab extends Component implements BootstrapInterface
{

    public $config = [
        'user' => 'www',
        'group' => 'www',
        'pid-file' => '@runtime/cron.pid',
        'log-file' => '@runtime/cron/run_cron.log'
    ];

    public $cronfile = "";

    /**
     *
     * @var string command class name
     */
    public $commandClass = Command::class;

    /**
     *
     * @return string command id
     * @throws
     */
    protected function getCommandId()
    {
        foreach (Yii::$app->getComponents(false) as $id => $component) {
            if ($component === $this) {
                return Inflector::camel2id($id);
            }
        }
        throw new InvalidConfigException('Queue must be an application component.');
    }

    public function bootstrap($app)
    {
        if ($app instanceof ConsoleApp) {
            $app->controllerMap[$this->getCommandId()] = [
                'class' => $this->commandClass
            ];
        }
    }
}