<?php
use AspectMock\Test as test;
use Robo\Runner;
use Robo\Container\RoboContainer;

class BowerTest extends \Codeception\TestCase\Test
{
    protected $container;

    /**
     * @var \AspectMock\Proxy\ClassProxy
     */
    protected $baseBower;

    protected function _before()
    {
        $this->baseBower = test::double('Robo\Task\Bower\Base', [
            'getOutput' => new \Symfony\Component\Console\Output\NullOutput()
        ]);
        $this->container = new RoboContainer();
        Runner::configureContainer($this->container);
        $this->container->addServiceProvider(Robo\Task\Bower\ServiceProvider::class);
    }
    // tests
    public function testBowerInstall()
    {
        $bower = test::double('Robo\Task\Bower\Install', ['executeCommand' => null]);
        $this->container->get('taskBowerInstall', ['bower'])->run();
        $bower->verifyInvoked('executeCommand', ['bower install']);
    }

    public function testBowerUpdate()
    {
        $bower = test::double('Robo\Task\Bower\Update', ['executeCommand' => null]);
        $this->container->get('taskBowerUpdate', ['bower'])->run();
        $bower->verifyInvoked('executeCommand', ['bower update']);
    }

    public function testBowerInstallCommand()
    {
        verify(
            $this->container->get('taskBowerInstall', ['bower'])->getCommand()
        )->equals('bower install');

        verify(
            $this->container->get('taskBowerInstall', ['bower'])->getCommand()
        )->equals('bower install');

        verify(
            $this->container->get('taskBowerInstall', ['bower'])
                ->allowRoot()
                ->forceLatest()
                ->offline()
                ->noDev()
                ->getCommand()
        )->equals('bower install --allow-root --force-latest --offline --production');
    }

}
