<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Service;

use Exception;
use RuntimeException;
use Symfony\Component\Console\Style\SymfonyStyle;
use WebProject\DockerApiClient\Dto\DockerContainerDto;
use WebProject\DockerApiClient\Event\ContainerEvent;
use WebProject\DockerApiClient\Service\DockerService;
use WebProject\DockerHostsFileSync\Dto\HostsFileEntryDto;
use WebProject\DockerHostsFileSync\Util\ContainerToHostsFileLinesUtil;

use function count;
use function in_array;
use function sprintf;

final class SynchronizeHostsFileService
{
    public const string START_TAG = '## docker-hostsfile-sync';
    public const string END_TAG   = '## docker-hostsfile-sync-end';

    public const array ENV_VARS_WITH_HOSTNAMES = ['DOMAIN_NAME', 'VIRTUAL_HOST'];
    private const array LISTEN_TO_ACTION       = [
        'start',
        'restart',
        'stop',
        'die',
    ];

    /** @var array<string, DockerContainerDto> */
    private array $activeContainers = [];

    public function __construct(
        private readonly DockerService $dockerService,
        private readonly string $hostsFile,
        private readonly string $tld,
        private readonly ?string $reverseProxyIp = null,
        private readonly ?SymfonyStyle $consoleOutput = null,
    ) {
    }

    public function run(): bool
    {
        if (!is_writable($this->hostsFile)) {
            throw new RuntimeException(sprintf('File "%s" is not writable.', $this->hostsFile));
        }

        if ($this->reverseProxyIp && !filter_var($this->reverseProxyIp, FILTER_VALIDATE_IP)) {
            throw new RuntimeException(sprintf('ReverseProxyIp "%s" is not a valid ip.', $this->reverseProxyIp));
        }

        $this->init();

        return true === $this->listen();
    }

    private function init(): void
    {
        foreach ($this->dockerService->findAllContainer() as $container) {
            if (!$container->isExposed()) {
                continue;
            }

            $this->activeContainers[$container->id] = $container;
            if ($this->consoleOutput?->isVerbose()) {
                $this->consoleOutput->writeln(sprintf('[+] Init: Running container "%s"', $container->getName()));
            }
        }

        $this->regenerateHostsFile();
    }

    private function listen(): true
    {
        $this->dockerService->listenForEvents(function (ContainerEvent $event) {
            if (!$event->Actor->ID) {
                return;
            }

            if (!in_array($event->Action, self::LISTEN_TO_ACTION, true)) {
                if ($this->consoleOutput?->isVeryVerbose()) {
                    $this->consoleOutput->writeln('[+] Action "' . $event->Action . '" from "' . $event->Actor->ID . '" - skipped.');
                }

                return;
            }

            try {
                $container = $this->dockerService->findContainer($event->Actor->ID);
            } catch (Exception $e) {
                return;
            }

            if (null === $container) {
                unset($this->activeContainers[$event->Actor->ID]);

                return;
            }

            if ($container->isExposed()) {
                $this->activeContainers[$container->id] = $container;
                if ($this->consoleOutput?->isVerbose()) {
                    $this->consoleOutput->writeln('[+] Action "' . $event->Action . '" received for: ' . $container->getName());
                }
            } else {
                unset($this->activeContainers[$container->id]);
            }

            $this->regenerateHostsFile();
        });

        return true;
    }

    private function regenerateHostsFile(): void
    {
        $containerToHostsFileLineUtil = new ContainerToHostsFileLinesUtil();

        $content = array_map('trim', file($this->hostsFile));
        $res     = preg_grep('/^' . self::START_TAG . '/', $content);
        $start   = count($res) ? key($res) : count($content) + 1;
        $res     = preg_grep('/^' . self::END_TAG . '/', $content);
        $end     = count($res) ? key($res) : count($content) + 1;

        $convertContainerToLine = function (DockerContainerDto $container) use ($containerToHostsFileLineUtil): string {
            return implode(
                "\n",
                array_map(
                    static fn (HostsFileEntryDto $line) => (string) $line,
                    $containerToHostsFileLineUtil(
                        container: $container,
                        tld: $this->tld,
                        extractFromEnvVars: self::ENV_VARS_WITH_HOSTNAMES,
                        reverseProxyIp: $this->reverseProxyIp,
                    )
                )
            );
        };

        $hostsFileLines = array_map($convertContainerToLine, $this->activeContainers);

        $hosts = array_merge(
            [self::START_TAG],
            array_map($convertContainerToLine, $this->activeContainers),
            [self::END_TAG]
        );

        array_splice($content, $start, $end - $start + 1, $hosts);
        file_put_contents($this->hostsFile, implode("\n", $content));
        if ($this->consoleOutput?->isVerbose()) {
            $this->consoleOutput->writeln('[+] Updated hosts file');

            $table = $this->consoleOutput->createTable();
            foreach ($hostsFileLines as $hostsFileLine) {
                $table->addRow([$hostsFileLine]);
            }
            $table->render();
        }
    }
}
