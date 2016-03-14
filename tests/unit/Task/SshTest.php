<?php

use AspectMock\Test as test;
use Robo\Runner;
use Robo\Container\RoboContainer;

class SshTest extends \Codeception\TestCase\Test
{
    protected $container;

    protected function _before()
    {
        $this->container = new RoboContainer();
        Runner::configureContainer($this->container);
        $this->container->addServiceProvider(\Robo\Task\Remote\ServiceProvider::class);
    }

    // tests
    public function testBasicCommand()
    {
        verify(
            $this->container->get('taskSshExec', ['remote.example.com', 'user'])
                ->exec('ls -la')
                ->exec('chmod g+x logs')
                ->getCommand()
        )->equals("ssh user@remote.example.com 'ls -la && chmod g+x logs'");
    }

    public function testStopOnFail()
    {
        verify(
            $this->container->get('taskSshExec', ['remote.example.com', 'user'])
                ->stopOnFail(false)
                ->exec('one')
                ->exec('two')
                ->getCommand()
        )->equals("ssh user@remote.example.com 'one ; two'");
    }

    /**
     * Sets static configuration, then runs task without working dir, with working dir and again without.
     */
    public function testWorkingDirectoryStaticConfiguration()
    {
        \Robo\Task\Remote\Ssh::configure('remoteDir', '/some-dir');
        verify(
            $this->container->get('taskSshExec', ['remote.example.com', 'user'])
                ->exec('echo test')
                ->getCommand()
        )->equals("ssh user@remote.example.com 'cd \"/some-dir\" && echo test'");
        verify(
            $this->container->get('taskSshExec', ['remote.example.com', 'user'])
                ->remoteDir('/other-dir')
                ->exec('echo test')
                ->getCommand()
        )->equals("ssh user@remote.example.com 'cd \"/other-dir\" && echo test'");
        verify(
            $this->container->get('taskSshExec', ['remote.example.com', 'user'])
                ->exec('echo test')
                ->getCommand()
        )->equals("ssh user@remote.example.com 'cd \"/some-dir\" && echo test'");
        \Robo\Task\Remote\Ssh::configure('remoteDir', null);
        verify(
            $this->container->get('taskSshExec', ['remote.example.com', 'user'])
                ->exec('echo test')
                ->getCommand()
        )->equals("ssh user@remote.example.com 'echo test'");
    }

}
