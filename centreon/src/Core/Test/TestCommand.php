<?php

namespace Core\Test;

use Security\Interfaces\EncryptionInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('test:decrypt')]
final class TestCommand extends Command
{
    public function __construct(private readonly EncryptionInterface $encryption)
    {
        $this->encryption->setSecondKey("to5j6khxDPQqIGRn\/IV4IZn3NvoyucJU9C6R2h0blG6FWdtEdlqQqMI8JvuDuqna");
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('encrypted', null, 'The key to use for decryption');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = $this
            ->encryption
            ->decrypt($input->getArgument('encrypted'));
        dd($result);
        $output->writeln($result);
        return Command::SUCCESS;
    }
}
