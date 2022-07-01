<?php

namespace trntv\bus\console;

use Exception;
use trntv\bus\CommandBus;
use yii\console\Controller;
use yii\di\Instance;
use yii\helpers\Console;

/**
 * Class BackgroundBusController
 * @package trntv\bus\console
 * @author Eugene Terentev <eugene@terentev.net>
 */
class BackgroundBusController extends Controller
{
    /**
     * @var mixed|CommandBus
     */
    public $commandBus = 'commandBus';

    public function beforeAction($action)
    {
        $this->commandBus = Instance::ensure($this->commandBus, CommandBus::class);
        return parent::beforeAction($action);
    }

    public function actionHandle($command)
    {
        spl_autoload_register(function ($className) {
            $className = str_replace("app\\", "", $className);
            $className = str_replace("\\", DIRECTORY_SEPARATOR, $className);
            include_once dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . $className . '.php';
        });
        try {
            $command = unserialize(base64_decode($command));
            $command->setRunningInBackground(true);
            $this->commandBus->handle($command);
        } catch (Exception $e) {
            Console::error($e->getMessage());
        }
    }
}
