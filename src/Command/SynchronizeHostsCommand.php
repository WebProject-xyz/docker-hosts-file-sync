<?php
declare(strict_types=1);

namespace WebProject\DockerApiClient\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use WebProject\DockerApiClient\Factory\SynchronizeHostsFileServiceFactory;

#[AsCommand(
    name: 'synchronize-hosts',
    description: 'Synchronize hosts file from docker api events and add ip and names',
)]
final class SynchronizeHostsCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'hosts_file',
                shortcut: 'f',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The host file to update (defaults to "/tmp/hosts")',
                default: getenv('HOSTS_FILE') ?: './tmp-hosts'
            )
            ->addOption(
                name: 'tld',
                shortcut: 't',
                mode: InputOption::VALUE_REQUIRED,
                description: 'The TLD to use',
                default: getenv('TLD') ?: '.docker'
            )
            ->addOption(
                name: 'reverse-proxy-host-ip',
                shortcut: 'r',
                mode: InputOption::VALUE_OPTIONAL,
                description: 'IP address of your reverse proxy will be used to generate hosts file lines by docker container env DOMAIN_NAME or network alias (with dot like dev.myapp)',
            )
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Synchronize hosts');

        $io->writeln('[+] Start Listening for docker api events on docker socket');

        $success = true === SynchronizeHostsFileServiceFactory::create(
            hostsFile: $input->getOption('hosts_file'),
            tld: $input->getOption('tld'),
            reverseProxyIp: $input->getOption('reverse-proxy-host-ip'),
            io: $io,
        )->run();

        $success
            ? $io->success('Synchronize hosts succeeded')
            : $io->error('Synchronize hosts failed');

        return $success ? self::SUCCESS : self::FAILURE;
    }
}
