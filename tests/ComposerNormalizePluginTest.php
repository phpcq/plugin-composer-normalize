<?php

declare(strict_types=1);

namespace Phpcq\ComposerNormalizePluginTest;

use Phpcq\PluginApi\Version10\Configuration\PluginConfigurationBuilderInterface;
use Phpcq\PluginApi\Version10\Configuration\PluginConfigurationInterface;
use Phpcq\PluginApi\Version10\DiagnosticsPluginInterface;
use Phpcq\PluginApi\Version10\EnvironmentInterface;
use Phpcq\PluginApi\Version10\Output\OutputInterface;
use Phpcq\PluginApi\Version10\Output\OutputTransformerFactoryInterface;
use Phpcq\PluginApi\Version10\Output\OutputTransformerInterface;
use Phpcq\PluginApi\Version10\Report\TaskReportInterface;
use Phpcq\PluginApi\Version10\Task\PhpTaskBuilderInterface;
use Phpcq\PluginApi\Version10\Task\TaskBuilderInterface;
use Phpcq\PluginApi\Version10\Task\TaskFactoryInterface;
use Phpcq\PluginApi\Version10\Task\TaskInterface;
use PHPUnit\Framework\TestCase;

use function assert;
use function iterator_to_array;

/**
 * @coversNothing
 */
final class ComposerNormalizePluginTest extends TestCase
{
    private function instantiate(): DiagnosticsPluginInterface
    {
        return include dirname(__DIR__) . '/src/composer-normalize.php';
    }

    public function testPluginName(): void
    {
        self::assertSame('composer-normalize', $this->instantiate()->getName());
    }

    public function testPluginDescribesConfig(): void
    {
        $configOptionsBuilder = $this->getMockForAbstractClass(PluginConfigurationBuilderInterface::class);

        $this->instantiate()->describeConfiguration($configOptionsBuilder);

        // We assume it worked out as the plugin did execute correctly.
        $this->addToAssertionCount(1);
    }

    public function testPluginCreatesDiagnosticTasks(): void
    {
        $config = $this->getMockForAbstractClass(PluginConfigurationInterface::class);
        $environment = $this->getMockForAbstractClass(EnvironmentInterface::class);

        $this->instantiate()->createDiagnosticTasks($config, $environment);

        // We assume it worked out as the plugin did execute correctly.
        $this->addToAssertionCount(1);
    }

    public function testIgnoresConfiguredOutput(): void
    {
        $config      = $this->getMockForAbstractClass(PluginConfigurationInterface::class);
        $environment = $this->getMockForAbstractClass(EnvironmentInterface::class);
        $taskFactory = $this->getMockForAbstractClass(TaskFactoryInterface::class);
        $taskBuilder = $this->getMockForAbstractClass(PhpTaskBuilderInterface::class);
        $task        = $this->getMockForAbstractClass(TaskInterface::class);

        $environment
            ->expects($this->once())
            ->method('getTaskFactory')
            ->willReturn($taskFactory);

        $taskFactory->method('buildRunPhar')->willReturn($taskBuilder);

        $taskBuilder->method('withWorkingDirectory')->willReturn($taskBuilder);
        $taskBuilder->method('withOutputTransformer')->willReturnCallback(
            function (OutputTransformerFactoryInterface $factory) use ($taskBuilder): TaskBuilderInterface {
                $report = $this->getMockForAbstractClass(TaskReportInterface::class);
                $report
                    ->expects($this->once())
                    ->method('addDiagnostic')
                    ->withConsecutive(
                        [
                            TaskReportInterface::SEVERITY_MINOR,
                            'Did not understand the following tool output: ' . "\n" . 'Foo bar'
                        ]
                    );

                $transformer = $factory->createFor($report);
                $transformer->write('Foo bar' . "\n", OutputInterface::CHANNEL_STDERR);
                $transformer->write('Some unknown output' . "\n", OutputInterface::CHANNEL_STDERR);
                $transformer->finish(0);

                return $taskBuilder;
            }
        );
        $taskBuilder->method('build')->willReturn($task);

        $config
            ->expects($this->once())
            ->method('getStringList')
            ->with('ignore_output')
            ->willReturn(['#Some unknown output#']);

        $tasks = $this->instantiate()->createDiagnosticTasks($config, $environment);
        $tasks = iterator_to_array($tasks);

        self::assertCount(1, $tasks);
        self::assertSame($task, $tasks[0]);
    }
}
