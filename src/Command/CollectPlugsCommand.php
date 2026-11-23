<?php

namespace App\Command;

use App\Service\PlugCollector;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Lock\LockFactory;

#[AsCommand(name: 'app:plugs:collect', description: 'Collect all plug readings every second, independently of website visitors.')]
final readonly class CollectPlugsCommand
{
    public function __construct(private PlugCollector $collector, private LockFactory $lockFactory, private ClockInterface $clock)
    {
    }

    public function __invoke(OutputInterface $output, #[Option(description: 'Collect a single batch and exit.')] bool $once = false): int
    {
        $lock = $this->lockFactory->createLock('homemon.plug-collector', 30);
        if (!$lock->acquire()) {
            $output->writeln('<error>A plug collector is already running.</error>');

            return Command::FAILURE;
        }

        $running = true;
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            foreach ([SIGTERM, SIGINT] as $signal) {
                pcntl_signal($signal, static function () use (&$running): void { $running = false; });
            }
        }

        $output->writeln('<info>Collecting plug readings every second. Press Ctrl+C to stop.</info>');
        try {
            do {
                $started = hrtime(true);
                $lock->refresh();
                $count = $this->collector->collect();
                $output->writeln(sprintf('Collected %d plug(s).', $count), OutputInterface::VERBOSITY_VERBOSE);
                if (!$once && $running) {
                    $this->clock->sleep(max(0, 1 - (hrtime(true) - $started) / 1e9));
                }
            } while (!$once && $running);
        } finally {
            $lock->release();
        }

        return Command::SUCCESS;
    }
}
