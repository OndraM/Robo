<?php
namespace Robo\Common;

use Robo\Config;
use Robo\TaskInfo;
use Consolidation\Log\ConsoleLogLevel;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Robo\Contract\ProgressIndicatorAwareInterface;

/**
 * Task input/output methods.  TaskIO is 'used' in BaseTask, so any
 * task that extends this class has access to all of the methods here.
 * printTaskInfo, printTaskSuccess, and printTaskError are the three
 * primary output methods that tasks are encouraged to use.  Tasks should
 * avoid using the IO trait output methods.
 */
trait TaskIO
{
    use LoggerAwareTrait;

    public function logger()
    {
        // $this->logger will always be set in Robo core tasks.
        // TODO: Remove call to Config::logger() once maintaining backwards
        // compatibility with legacy external Robo tasks is no longer desired.
        if (!$this->logger) {
            static $gaveDeprecationWarning = false;
            if (!$gaveDeprecationWarning) {
                trigger_error('No logger set for ' . get_class($this) . '. Use $this->task("Foo") rather than new Foo() in loadTasks to ensure the DI container can initialize tasks, or use $task->inflect($this) if creating one task from within another.', E_USER_DEPRECATED);
                $gaveDeprecationWarning = true;
            }
            return Config::logger();
        }
        return $this->logger;
    }

    /**
     * Print information about a task in progress.
     *
     * With the Symfony Console logger, NOTICE is displayed at VERBOSITY_VERBOSE
     * and INFO is displayed at VERBOSITY_VERY_VERBOSE.
     *
     * Robo overrides the default such that NOTICE is displayed at
     * VERBOSITY_NORMAL and INFO is displayed at VERBOSITY_VERBOSE.
     *
     * n.b. We should probably have printTaskNotice for our ordinary
     * output, and use printTaskInfo for less interesting messages.
     */
    protected function printTaskInfo($text, $context = null)
    {
        // The 'note' style is used for both 'notice' and 'info' log levels;
        // However, 'notice' is printed at VERBOSITY_NORMAL, whereas 'info'
        // is only printed at VERBOSITY_VERBOSE.
        $this->printTaskOutput(LogLevel::NOTICE, $text, $this->getTaskContext($context));
    }

    /**
     * Provide notification that some part of the task succeeded.
     *
     * With the Symfony Console logger, success messages are remapped to NOTICE,
     * and displayed in VERBOSITY_VERBOSE. When used with the Robo logger,
     * success messages are displayed at VERBOSITY_NORMAL.
     */
    protected function printTaskSuccess($text, $context = null)
    {
        // Not all loggers will recognize ConsoleLogLevel::SUCCESS.
        // We therefore log as LogLevel::NOTICE, and apply a '_level'
        // override in the context so that this message will be
        // logged as SUCCESS if that log level is recognized.
        $context['_level'] = ConsoleLogLevel::SUCCESS;
        $this->printTaskOutput(LogLevel::NOTICE, $text, $this->getTaskContext($context));
    }

    /**
     * Provide notification that there is something wrong, but
     * execution can continue.
     *
     * Warning messages are displayed at VERBOSITY_NORMAL.
     */
    protected function printTaskWarning($text, $context = null)
    {
        $this->printTaskOutput(LogLevel::WARNING, $text, $this->getTaskContext($context));
    }

    /**
     * Provide notification that some operation in the task failed,
     * and the task cannot continue.
     *
     * Error messages are displayed at VERBOSITY_NORMAL.
     */
    protected function printTaskError($text, $context = null)
    {
        $this->printTaskOutput(LogLevel::ERROR, $text, $this->getTaskContext($context));
    }

    /**
     * Provide debugging notification.  These messages are only
     * displayed if the log level is VERBOSITY_DEBUG.
     */
    protected function printTaskDebug($text, $context = null)
    {
        $this->printTaskOutput(LogLevel::DEBUG, $text, $this->getTaskContext($context));
    }

    protected function printTaskOutput($level, $text, $context)
    {
        // Hide the progress indicator, if it is visible.
        $inProgress = $this->hideTaskProgress();
        $this->logger()->log($level, $text, $this->getTaskContext($context));
        // After we have printed our log message, redraw the progress indicator.
        $this->showTaskProgress($inProgress);
    }

    protected function hideTaskProgress()
    {
        $inProgress = false;
        if ($this instanceof ProgressIndicatorAwareInterface) {
            $inProgress = $this->inProgress();
        }

        // If a progress indicator is running on this task, then we mush
        // hide it before we print anything, or its display will be overwritten.
        if ($inProgress) {
            $inProgress = $this->hideProgressIndicator();
        }
        return $inProgress;
    }

    protected function showTaskProgress($inProgress)
    {
        if ($inProgress) {
            $this->restoreProgressIndicator($inProgress);
        }
    }

    /**
     * Format a quantity of bytes.
     */
    protected function formatBytes($size, $precision = 2)
    {
        if ($size === 0) {
            return 0;
        }
        $base = log($size, 1024);
        $suffixes = array('', 'k', 'M', 'G', 'T');
        return round(pow(1024, $base - floor($base)), $precision) . $suffixes[floor($base)];
    }

    /**
     * Get the formatted task name for use in task output.
     * This is placed in the task context under 'name', and
     * used as the log label by Robo\Common\RoboLogStyle,
     * which is inserted at the head of log messages by
     * Robo\Common\CustomLogStyle::formatMessage().
     *
     * @return string
     */
    protected function getPrintedTaskName($task = null)
    {
        if (!$task) {
            $task = $this;
        }
        return TaskInfo::formatTaskName($task);
    }

    /**
     * @return array with context information
     */
    protected function getTaskContext($context = null)
    {
        if (!$context) {
            $context = [];
        }
        if (!is_array($context)) {
            $context = ['task' => $context];
        }
        if (!array_key_exists('task', $context)) {
            $context['task'] = $this;
        }

        return $context + TaskInfo::getTaskContext($context['task']);
    }
}
