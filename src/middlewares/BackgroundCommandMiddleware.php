<?php

namespace trntv\bus\middlewares;

use Symfony\Component\Process\Process;
use Symfony\Component\Process\PhpExecutableFinder;
use trntv\bus\interfaces\BackgroundCommand;
use trntv\bus\interfaces\Middleware;
use Yii;
use yii\base\BaseObject;

/**
 * Class BackgroundCommandMiddleware
 * @package trntv\bus\middlewares
 * @author Eugene Terentev <eugene@terentev.net>
 */
class BackgroundCommandMiddleware extends BaseObject implements Middleware
{
    /**
     * @var string Path to php executable
     */
    public $backgroundHandlerBinary;
    /**
     * @var string Path to cli script
     */
    public $backgroundHandlerPath;
    /**
     * @var string console route
     */
    public $backgroundHandlerRoute;
    /**
     * @var int|float|null process timeout
     */
    public $backgroundProcessTimeout = 60;
    /**
     * @var int|float|null process idle timeout
     */
    public $backgroundProcessIdleTimeout;
    /**
     * @var array Arguments that will be passed to script
     */
    public $backgroundHandlerArguments = [];
    /**
     * @var array Arguments that will be passed to binary
     *
     * ```php
     *      'backgroundHandlerPath' => '/path/to/script.php',
     *      'backgroundHandlerArguments' => ['--foo bar']
     *      'backgroundHandlerBinaryArguments' => ['--define memory_limit=1G']
     * ```
     * will generate
     * ```
     * php --define memory_limit=1G /path/to/script.php --foo bar
     * ```
     */
    public $backgroundHandlerBinaryArguments = [];

    public function execute($command, callable $next)
    {

        if ($command instanceof BackgroundCommand && !$command->isRunningInBackground()) {
            return $this->runProcess($command);
        }

        return $next($command);
    }

    protected function runProcess(BackgroundCommand $command)
    {
        $binary = $this->getBackgroundHandlerBinary();
        $path = $this->getBackgroundHandlerPath();
        $route = $this->getBackgroundHandlerRoute();
        $arguments = implode(' ', $this->getBackgroundHandlerArguments($command));
        $binaryArguments = implode(' ', $this->backgroundHandlerBinaryArguments);

        $process_command = array_filter([$binary, $binaryArguments, $path, $route, $arguments],
            function ($v) {
                return $v !== "";
            }
        );

        $process = new Process($process_command);
        $process->disableOutput();
        $process->setOptions(['create_new_console' => true]);
        $process->setTimeout($this->backgroundProcessTimeout);
        $process->setIdleTimeout($this->backgroundProcessIdleTimeout);
        if ($command->isAsync()) {
            $process->start();
        } else {
            $process->run();
        }

        return $process;
    }

    /**
     * @return bool|string
     */
    public function getBackgroundHandlerBinary()
    {
        $binary = $this->backgroundHandlerBinary ?: (new PhpExecutableFinder())->find();
        return Yii::getAlias($binary);
    }

    /**
     * @return bool|string
     */
    public function getBackgroundHandlerPath()
    {
        return Yii::getAlias($this->backgroundHandlerPath);
    }

    public function getBackgroundHandlerRoute()
    {
        return $this->backgroundHandlerRoute;
    }

    /**
     * @param $command
     * @return array
     */
    private function getBackgroundHandlerArguments($command)
    {
        $arguments = $this->backgroundHandlerArguments;
        $command = base64_encode(serialize($command));
        array_unshift($arguments, $command);
        return $arguments;
    }
}
