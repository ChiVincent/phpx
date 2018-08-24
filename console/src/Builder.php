<?php

namespace phpx;

use Symfony\Component\Console\Exception\RuntimeException;

class Builder
{
    private $objects;
    private $files;

    protected $root;
    protected $cxxflags = '-g';
    protected $ldflags = '';
    protected $exts = array('.cc', '.cpp', '.c');
    protected $projectName;
    protected $target;
    protected $installTargetDir;

    const DIR_SRC = 'src';
    const DIR_INCLUDE = 'include';
    const DIR_LIBRARY = 'lib';
    const DIR_BIN = 'bin';
    const DIR_BUILD = '.build';

    const COMPILER = 'c++';

    protected $debug = false;

    function __construct($debug = false)
    {
        $this->debug = $debug;
        $this->root = getcwd() . '/';
        if (!is_dir($this->root . self::DIR_SRC)) {
            throw  new RuntimeException("no src dir\n");
        }
        if (!is_file($this->root . 'config.ini')) {
            throw  new RuntimeException("no config.ini\n");
        }
        $config = parse_ini_file($this->root . 'config.ini', true);
        if (empty($config['project']['name'])) {
            throw  new RuntimeException("no project.name option in config.ini\n");
        }
        $this->projectName = $config['project']['name'];
        $this->cxxflags .= ' ' . $config['build']['cxxflags'];
        $this->ldflags .= ' ' . $config['build']['ldflags'];
        $this->target = $config['build']['target'];
        $this->installTargetDir = $config['install']['target'];
    }

    function make()
    {
        $this->compile();
        $this->link();
    }

    function getFileList($dir)
    {
        $files = scandir($dir);
        foreach ($files as $file) {
            if ($file == '.' or $file == '..') {
                continue;
            }
            if (is_dir($dir . '/' . $file)) {
                $this->getFileList($dir . '/' . $file);
            } else {
                //扩展名必须为规定的
                if (!in_array(strstr($file, '.'), $this->exts)) {
                    continue;
                }
                $this->files[] = $dir . '/' . $file;
            }
        }
    }

    function compile()
    {
        $this->getFileList($this->root . self::DIR_SRC);

        if (empty($this->files)) {
            throw  new RuntimeException("no src files \n");
        }

        foreach ($this->files as $file) {
            $_file = str_replace($this->root, '', $file);
            $objectFile = $this->root . self::DIR_BUILD . substr($_file, strlen(self::DIR_SRC)) . '.o';
            if (!is_dir(dirname($objectFile))) {
                mkdir(dirname($objectFile), 0777, true);
            }
            $this->objects[] = $objectFile;
            //源文件的修改时间晚于目标文件，无需编译
            if (is_file($objectFile) and filemtime($objectFile) >= filemtime($file)) {
                continue;
            }
            $this->exec(self::COMPILER . " {$this->cxxflags} -fPIC -I./include -c $file -std=c++11 -o " . $objectFile);
        }
    }

    public function exec($cmd)
    {
        if ($this->debug) {
            echo $cmd."\n";
        }
        shell_exec($cmd);
    }

    public function link()
    {
        $objects = implode(' ', $this->objects);
        if (!is_dir(dirname($this->target))) {
            mkdir(dirname($this->target));
        }
        $this->exec(self::COMPILER . " $objects {$this->ldflags} -L./lib -o {$this->target}");
    }

    function install()
    {
        if (!is_file($this->target)) {
            $this->make();
        }
        $this->exec("cp $this->target " .$this->installTargetDir.'/'.basename($this->target));
    }
}