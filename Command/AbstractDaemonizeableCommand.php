<?php

namespace zis\DaemonBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Class AbstractDaemonizeableCommand
 *
 * @package zis\DaemonBundle\Command
 */
abstract class AbstractDaemonizeableCommand extends ContainerAwareCommand
{
    /**
     * Здесь список запущенных дочерних процессов
     *
     * @var array
     */
    protected $currentJobs = [];

    protected $stop = false;


    /**
     * демонизация команды
     */
    private function daemonize()
    {
        // Создаем дочерний процесс
        // весь код после pcntl_fork() будет выполняться двумя процессами: родительским и дочерним
        $child_pid = pcntl_fork();
        if ($child_pid) {
            // Выходим из родительского, привязанного к консоли, процесса
            exit();
        }
        // Делаем основным процессом дочерний.
        posix_setsid();

        $this->addSigHandler(SIGTERM, [$this, 'sigHandler']);
        $this->addSigHandler(SIGTERM, [$this, 'sigHandler']);
    }

    /**
     * убить дочерний процесс по пиду
     *
     * @param $pid
     */
    protected function killChild($pid)
    {
        if (isset($this->currentJobs[$pid])) {
            posix_kill($pid, SIGKILL);
        }
    }

    /**
     * убить все дочерние процессы
     */
    protected function killChildren()
    {
        $keys = array_keys($this->currentJobs);
        foreach ($keys as $pid) {
            $this->killChild($pid);
        }
    }

    protected function sigtermHandler()
    {
        $this->stop = true;
        $this->killChildren();
    }

    /**
     * @param $pid
     * @param $status
     */
    protected function sigchldHandler($pid, $status)
    {
        if (!$pid) {
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
        // Пока есть завершенные дочерние процессы
        while ($pid > 0) {
            if ($pid && isset($this->currentJobs[$pid])) {
                // Удаляем дочерние процессы из списка
                unset($this->currentJobs[$pid]);
            }
            $pid = pcntl_waitpid(-1, $status, WNOHANG);
        }
    }

    protected function preProcess() {}

    /**
     * @param      $signo
     * @param null $pid
     * @param null $status
     */
    final protected function sigHandler($signo, $pid = null, $status = null)
    {
        switch ($signo) {
            case SIGTERM:
                $this->sigtermHandler();
                break;
            case SIGCHLD:
                // При получении сигнала от дочернего процесса
                $this->sigchldHandler($pid, $status);
                break;
            default:
                // все остальные сигналы
        }
    }

    /**
     * @param InputInterface  $input
     * @param OutputInterface $output
     */
    final protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->daemonize();
        $this->preProcess();

        while (!$this->stop) {
            $this->process();
        }
    }

    /**
     * Запускает дочерний процесс
     *
     * @return bool
     */
    final protected function launchJob()
    {
        // Создаем дочерний процесс
        // весь код после pcntl_fork() будет выполняться
        // двумя процессами: родительским и дочерним
        $pid = pcntl_fork();
        if ($pid == -1) {
            // Не удалось создать дочерний процесс
            echo('Could not launch new job, exiting');

            return false;

        } elseif ($pid) {
            // Этот код выполнится родительским процессом
            $this->currentJobs[$pid] = $pid;
        } else {
            // А этот код выполнится дочерним процессом
            $this->processJob();
            exit();
        }

        return true;
    }

    final protected function addSigHandler($signal, callable $handler)
    {
        pcntl_signal($signal, $handler);
    }

    abstract protected function process();

    abstract protected function processJob();
}