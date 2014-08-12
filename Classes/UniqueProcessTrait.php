<?php

namespace zis\DaemonBundle\Classes;

/**
 * Trait UniqueProcessTrait
 */
trait UniqueProcessTrait
{
    protected $pidFile;

    protected function setPidFile($file)
    {
        $this->pidFile = $file;
    }

    /**
     * @return bool
     */
    public function isDaemonActive()
    {
        if (!$this->pidFile) {
            throw new \Exception('Set pid file location');
        }
        if (is_file($this->pidFile)) {
            $pid = file_get_contents($this->pidFile);
            //проверяем на наличие процесса
            if (posix_kill($pid, 0)) {
                //демон уже запущен
                return true;
            } else {
                //pid-файл есть, но процесса нет
                if (!unlink($this->pidFile)) {
                    //не могу уничтожить pid-файл. ошибка
                    exit(-1);
                }
            }
        }

        return false;
    }

    /**
     *
     */
    public function putPidFile()
    {
        if (!$this->pidFile) {
            throw new \Exception('Set pid file location');
        }
        file_put_contents($this->pidFile, getmypid());
    }

    /**
     *
     */
    public function unlinkPidFile()
    {
        if (!$this->pidFile) {
            throw new \Exception('Set pid file location');
        }
        if (is_file($this->pidFile)) {
            unlink($this->pidFile);
        }
    }

}
