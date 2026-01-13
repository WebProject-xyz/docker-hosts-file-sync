<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Tests\Unit\Service;

use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use ReflectionClass;
use WebProject\DockerApiClient\Dto\DockerContainerDto;
use WebProject\DockerApiClient\Service\DockerService;
use WebProject\DockerHostsFileSync\Service\SynchronizeHostsFileService;
use WebProject\DockerHostsFileSync\Tests\Support\UnitTester;

#[CoversClass(SynchronizeHostsFileService::class)]
final class SynchronizeHostsFileServiceTest extends Unit
{
    protected UnitTester $tester;

    private string $tempHostsFile;

    protected function _before(): void
    {
        $this->tempHostsFile = sys_get_temp_dir() . '/test-hosts-' . uniqid('', true);
        file_put_contents($this->tempHostsFile, "127.0.0.1 localhost\n");
    }

    protected function _after(): void
    {
        if (file_exists($this->tempHostsFile)) {
            unlink($this->tempHostsFile);
        }
    }

    public function testConstantsAreCorrect(): void
    {
        $this->assertSame('## docker-hostsfile-sync', SynchronizeHostsFileService::START_TAG);
        $this->assertSame('## docker-hostsfile-sync-end', SynchronizeHostsFileService::END_TAG);
        $this->assertSame(['DOMAIN_NAME', 'VIRTUAL_HOST'], SynchronizeHostsFileService::ENV_VARS_WITH_HOSTNAMES);
    }

    public function testRegenerateHostsFileWithNoContainers(): void
    {
        // Arrange
        $service = $this->createServiceForTesting($this->tempHostsFile, '.docker', []);

        // Act
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('127.0.0.1 localhost', $content);
        $this->assertStringContainsString(SynchronizeHostsFileService::START_TAG, $content);
        $this->assertStringContainsString(SynchronizeHostsFileService::END_TAG, $content);
    }

    public function testRegenerateHostsFileWithContainers(): void
    {
        // Arrange
        $container = new DockerContainerDto(
            id: 'container-id-123',
            name: '/webapp',
            image: 'nginx:alpine',
            running: true,
            envVariables: [],
            ipAddresses: ['172.17.0.2'],
            networks: [
                'bridge' => [
                    'ip'      => '172.17.0.2',
                    'aliases' => [],
                ],
            ],
            ports: ['80/tcp' => []],
        );

        $service = $this->createServiceForTesting($this->tempHostsFile, '.docker', [$container]);

        // Act
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('127.0.0.1 localhost', $content);
        $this->assertStringContainsString(SynchronizeHostsFileService::START_TAG, $content);
        $this->assertStringContainsString(SynchronizeHostsFileService::END_TAG, $content);
        $this->assertStringContainsString('172.17.0.2', $content);
        $this->assertStringContainsString('webapp.docker', $content);
        $this->assertStringContainsString('webapp.bridge', $content);
    }

    public function testRegenerateHostsFileWithReverseProxyIp(): void
    {
        // Arrange
        $container = new DockerContainerDto(
            id: 'container-id-456',
            name: '/api',
            image: 'node:alpine',
            running: true,
            envVariables: [
                'DOMAIN_NAME' => 'api.local',
            ],
            ipAddresses: ['172.17.0.3'],
            networks: [
                'proxy' => [
                    'ip'      => '172.17.0.3',
                    'aliases' => ['api.example.com'],
                ],
            ],
            ports: ['3000/tcp' => []],
        );

        $service = $this->createServiceForTesting($this->tempHostsFile, '.docker', [$container], '172.16.238.100');

        // Act
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString(SynchronizeHostsFileService::START_TAG, $content);
        $this->assertStringContainsString(SynchronizeHostsFileService::END_TAG, $content);
        // Container direct IP
        $this->assertStringContainsString('172.17.0.3', $content);
        // Reverse proxy entries for URL-like aliases and DOMAIN_NAME
        $this->assertStringContainsString('172.16.238.100', $content);
        $this->assertStringContainsString('api.example.com', $content);
        $this->assertStringContainsString('api.local', $content);
    }

    public function testRegenerateHostsFilePreservesExistingContent(): void
    {
        // Arrange
        $initialContent = "127.0.0.1 localhost\n192.168.1.1 myserver\n# Custom comment\n";
        file_put_contents($this->tempHostsFile, $initialContent);

        $service = $this->createServiceForTesting($this->tempHostsFile, '.docker', []);

        // Act
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('127.0.0.1 localhost', $content);
        $this->assertStringContainsString('192.168.1.1 myserver', $content);
        $this->assertStringContainsString('# Custom comment', $content);
    }

    public function testRegenerateHostsFileUpdatesExistingSection(): void
    {
        // Arrange
        $initialContent = <<<'HOSTS'
            127.0.0.1 localhost
            ## docker-hostsfile-sync
            172.17.0.99 old-container.docker
            ## docker-hostsfile-sync-end
            192.168.1.1 myserver
            HOSTS;
        file_put_contents($this->tempHostsFile, $initialContent);

        $container = new DockerContainerDto(
            id: 'new-container-id',
            name: '/new-webapp',
            image: 'nginx:alpine',
            running: true,
            envVariables: [],
            ipAddresses: ['172.17.0.5'],
            networks: [
                'bridge' => [
                    'ip'      => '172.17.0.5',
                    'aliases' => [],
                ],
            ],
            ports: ['80/tcp' => []],
        );

        $service = $this->createServiceForTesting($this->tempHostsFile, '.docker', [$container]);

        // Act
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('127.0.0.1 localhost', $content);
        $this->assertStringContainsString('192.168.1.1 myserver', $content);
        // Old container should be gone
        $this->assertStringNotContainsString('172.17.0.99', $content);
        $this->assertStringNotContainsString('old-container', $content);
        // New container should be present
        $this->assertStringContainsString('172.17.0.5', $content);
        $this->assertStringContainsString('new-webapp', $content);
    }

    public function testRegenerateHostsFileWithMultipleContainers(): void
    {
        // Arrange
        $container1 = new DockerContainerDto(
            id: 'container-1',
            name: '/webapp',
            image: 'nginx:alpine',
            running: true,
            envVariables: [],
            ipAddresses: ['172.17.0.2'],
            networks: [
                'frontend' => [
                    'ip'      => '172.17.0.2',
                    'aliases' => [],
                ],
            ],
            ports: ['80/tcp' => []],
        );

        $container2 = new DockerContainerDto(
            id: 'container-2',
            name: '/database',
            image: 'postgres:16',
            running: true,
            envVariables: [],
            ipAddresses: ['172.17.0.3'],
            networks: [
                'backend' => [
                    'ip'      => '172.17.0.3',
                    'aliases' => ['db'],
                ],
            ],
            ports: ['5432/tcp' => []],
        );

        $service = $this->createServiceForTesting($this->tempHostsFile, '.docker', [$container1, $container2]);

        // Act
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('172.17.0.2', $content);
        $this->assertStringContainsString('webapp.frontend', $content);
        $this->assertStringContainsString('172.17.0.3', $content);
        $this->assertStringContainsString('database.backend', $content);
        $this->assertStringContainsString('db.backend', $content);
    }

    public function testRegenerateHostsFileWithContainerOnMultipleNetworks(): void
    {
        // Arrange
        $container = new DockerContainerDto(
            id: 'multi-network-container',
            name: '/webapp',
            image: 'nginx:alpine',
            running: true,
            envVariables: [],
            ipAddresses: ['172.17.0.2', '172.18.0.2'],
            networks: [
                'frontend' => [
                    'ip'      => '172.17.0.2',
                    'aliases' => [],
                ],
                'backend' => [
                    'ip'      => '172.18.0.2',
                    'aliases' => ['app'],
                ],
            ],
            ports: ['80/tcp' => []],
        );

        $service = $this->createServiceForTesting($this->tempHostsFile, '.docker', [$container]);

        // Act
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('172.17.0.2', $content);
        $this->assertStringContainsString('webapp.frontend', $content);
        $this->assertStringContainsString('172.18.0.2', $content);
        $this->assertStringContainsString('webapp.backend', $content);
        $this->assertStringContainsString('app.backend', $content);
    }

    /**
     * Creates a SynchronizeHostsFileService for testing with pre-set containers.
     *
     * @param array<DockerContainerDto> $containers
     */
    private function createServiceForTesting(
        string $hostsFile,
        string $tld,
        array $containers,
        ?string $reverseProxyIp = null,
    ): SynchronizeHostsFileService {
        // Create DockerService without constructor
        $dockerServiceReflection = new ReflectionClass(DockerService::class);
        /** @var DockerService $dockerServiceMock */
        $dockerServiceMock = $dockerServiceReflection->newInstanceWithoutConstructor();

        // Create the service
        $service = new SynchronizeHostsFileService(
            dockerService: $dockerServiceMock,
            hostsFile: $hostsFile,
            tld: $tld,
            reverseProxyIp: $reverseProxyIp,
        );

        // Set active containers directly via reflection
        $serviceReflection        = new ReflectionClass(SynchronizeHostsFileService::class);
        $activeContainersProperty = $serviceReflection->getProperty('activeContainers');

        // Filter only exposed containers (mimicking init() behavior)
        $exposedContainers = [];
        foreach ($containers as $container) {
            if ($container->isExposed()) {
                $exposedContainers[$container->id] = $container;
            }
        }
        $activeContainersProperty->setValue($service, $exposedContainers);

        return $service;
    }

    /**
     * Invokes the private regenerateHostsFile method via reflection.
     */
    private function invokeRegenerateHostsFile(SynchronizeHostsFileService $service): void
    {
        $reflection = new ReflectionClass(SynchronizeHostsFileService::class);
        $method     = $reflection->getMethod('regenerateHostsFile');
        $method->invoke($service);
    }
}
