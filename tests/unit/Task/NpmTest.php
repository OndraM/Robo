<?php
use AspectMock\Test as test;
use Robo\Runner;
use Robo\Container\RoboContainer;

class NpmTest extends \Codeception\TestCase\Test
{
    protected $container;

    /**
     * @var \AspectMock\Proxy\ClassProxy
     */
    protected $baseNpm;

    protected function _before()
    {
        $this->baseNpm = test::double('Robo\Task\Npm\Base', [
            'getOutput' => new \Symfony\Component\Console\Output\NullOutput()
        ]);
        $this->container = new RoboContainer();
        Runner::configureContainer($this->container);
        $this->container->addServiceProvider(\Robo\Task\Npm\ServiceProvider::class);
    }

    // tests
    public function testNpmInstall()
    {
        $npm = test::double('Robo\Task\Npm\Install', ['executeCommand' => null]);
        $this->container->get('taskNpmInstall', ['npm'])->run();
        $npm->verifyInvoked('executeCommand', ['npm install']);
    }

    public function testNpmUpdate()
    {
        $npm = test::double('Robo\Task\Npm\Update', ['executeCommand' => null]);
        $this->container->get('taskNpmUpdate', ['npm'])->run();
        $npm->verifyInvoked('executeCommand', ['npm update']);
    }

    public function testNpmInstallCommand()
    {
        verify(
            $this->container->get('taskNpmInstall', ['npm'])->getCommand()
        )->equals('npm install');

        verify(
            $this->container->get('taskNpmInstall', ['npm'])->getCommand()
        )->equals('npm install');

        verify(
            $this->container->get('taskNpmInstall', ['npm'])
                ->noDev()
                ->getCommand()
        )->equals('npm install --production');
    }

}
