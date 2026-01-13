<?php

declare(strict_types=1);

namespace WebProject\DockerHostsFileSync\Tests\Unit\Service;

use Codeception\Test\Unit;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use ReflectionClass;
use RuntimeException;
use WebProject\DockerApi\Library\Generated\Client;
use WebProject\DockerApi\Library\Generated\Model\ContainerConfig;
use WebProject\DockerApi\Library\Generated\Model\ContainerInspectResponse;
use WebProject\DockerApi\Library\Generated\Model\ContainerState;
use WebProject\DockerApi\Library\Generated\Model\ContainerSummary;
use WebProject\DockerApi\Library\Generated\Model\EndpointSettings;
use WebProject\DockerApi\Library\Generated\Model\NetworkSettings;
use WebProject\DockerApiClient\Client\DockerApiClientWrapper;
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

    // =========================================================================
    // Constants Tests
    // =========================================================================

    public function testConstantsAreCorrect(): void
    {
        $this->assertSame('## docker-hostsfile-sync', SynchronizeHostsFileService::START_TAG);
        $this->assertSame('## docker-hostsfile-sync-end', SynchronizeHostsFileService::END_TAG);
        $this->assertSame(['DOMAIN_NAME', 'VIRTUAL_HOST'], SynchronizeHostsFileService::ENV_VARS_WITH_HOSTNAMES);
    }

    // =========================================================================
    // Integration Tests with Mocked Client
    // =========================================================================

    public function testRunThrowsExceptionWhenHostsFileNotWritable(): void
    {
        // Arrange
        $clientMock    = $this->createDockerClientMock([]);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: '/non/existent/path/hosts',
            tld: '.docker',
        );

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('File "/non/existent/path/hosts" is not writable.');

        // Act
        $service->run();
    }

    public function testRunThrowsExceptionWhenReverseProxyIpIsInvalid(): void
    {
        // Arrange
        $clientMock    = $this->createDockerClientMock([]);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: $this->tempHostsFile,
            tld: '.docker',
            reverseProxyIp: 'invalid-ip',
        );

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('ReverseProxyIp "invalid-ip" is not a valid ip.');

        // Act
        $service->run();
    }

    public function testIntegrationWithNoContainers(): void
    {
        // Arrange
        $clientMock    = $this->createDockerClientMock([]);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: $this->tempHostsFile,
            tld: '.docker',
        );

        // Use reflection to call init() and regenerateHostsFile() without listenForEvents()
        $this->invokeInit($service);
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('127.0.0.1 localhost', $content);
        $this->assertStringContainsString(SynchronizeHostsFileService::START_TAG, $content);
        $this->assertStringContainsString(SynchronizeHostsFileService::END_TAG, $content);
    }

    public function testIntegrationWithSingleContainer(): void
    {
        // Arrange
        $containerData = [
            [
                'id'       => 'abc123def456',
                'name'     => '/webapp',
                'image'    => 'nginx:alpine',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'bridge' => ['ip' => '172.17.0.2', 'aliases' => []],
                ],
                'ports' => ['80/tcp' => []],
            ],
        ];

        $clientMock    = $this->createDockerClientMock($containerData);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: $this->tempHostsFile,
            tld: '.docker',
        );

        // Act
        $this->invokeInit($service);
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);
        $this->assertStringContainsString('172.17.0.2', $content);
        $this->assertStringContainsString('webapp.docker', $content);
        $this->assertStringContainsString('webapp.bridge', $content);
    }

    public function testIntegrationWithMultipleContainers(): void
    {
        // Arrange
        $containerData = [
            [
                'id'       => 'container-1-id',
                'name'     => '/frontend',
                'image'    => 'nginx:alpine',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'web' => ['ip' => '172.20.0.2', 'aliases' => ['www']],
                ],
                'ports' => ['80/tcp' => []],
            ],
            [
                'id'       => 'container-2-id',
                'name'     => '/backend',
                'image'    => 'php:8.3-fpm',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'web'      => ['ip' => '172.20.0.3', 'aliases' => ['api']],
                    'internal' => ['ip' => '172.21.0.2', 'aliases' => []],
                ],
                'ports' => ['9000/tcp' => []],
            ],
            [
                'id'       => 'container-3-id',
                'name'     => '/database',
                'image'    => 'postgres:16',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'internal' => ['ip' => '172.21.0.3', 'aliases' => ['db', 'postgres']],
                ],
                'ports' => ['5432/tcp' => []],
            ],
        ];

        $clientMock    = $this->createDockerClientMock($containerData);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: $this->tempHostsFile,
            tld: '.docker',
        );

        // Act
        $this->invokeInit($service);
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);

        // Frontend container
        $this->assertStringContainsString('172.20.0.2', $content);
        $this->assertStringContainsString('frontend.web', $content);
        $this->assertStringContainsString('www.web', $content);

        // Backend container
        $this->assertStringContainsString('172.20.0.3', $content);
        $this->assertStringContainsString('backend.web', $content);
        $this->assertStringContainsString('api.web', $content);
        $this->assertStringContainsString('172.21.0.2', $content);
        $this->assertStringContainsString('backend.internal', $content);

        // Database container
        $this->assertStringContainsString('172.21.0.3', $content);
        $this->assertStringContainsString('database.internal', $content);
        $this->assertStringContainsString('db.internal', $content);
        $this->assertStringContainsString('postgres.internal', $content);
    }

    public function testIntegrationWithReverseProxy(): void
    {
        // Arrange
        $containerData = [
            [
                'id'       => 'proxy-container',
                'name'     => '/nginx-proxy',
                'image'    => 'nginxproxy/nginx-proxy',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'proxy' => ['ip' => '172.16.238.100', 'aliases' => []],
                ],
                'ports' => ['80/tcp' => [], '443/tcp' => []],
            ],
            [
                'id'       => 'app-container',
                'name'     => '/myapp',
                'image'    => 'myapp:latest',
                'running'  => true,
                'env'      => ['VIRTUAL_HOST=myapp.local,www.myapp.local', 'DOMAIN_NAME=api.myapp.local'],
                'networks' => [
                    'proxy'   => ['ip' => '172.16.238.2', 'aliases' => ['app.dev.local']],
                    'default' => ['ip' => '172.17.0.5', 'aliases' => []],
                ],
                'ports' => ['8080/tcp' => []],
            ],
        ];

        $clientMock    = $this->createDockerClientMock($containerData);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: $this->tempHostsFile,
            tld: '.docker',
            reverseProxyIp: '172.16.238.100',
        );

        // Act
        $this->invokeInit($service);
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);

        // Proxy container direct access
        $this->assertStringContainsString('172.16.238.100', $content);
        $this->assertStringContainsString('nginx-proxy.proxy', $content);

        // App container direct access
        $this->assertStringContainsString('172.16.238.2', $content);
        $this->assertStringContainsString('myapp.proxy', $content);
        $this->assertStringContainsString('172.17.0.5', $content);
        $this->assertStringContainsString('myapp.default', $content);

        // Reverse proxy entries (URL-like aliases and env vars routed to proxy IP)
        $this->assertStringContainsString('app.dev.local', $content);
        $this->assertStringContainsString('myapp.local', $content);
        $this->assertStringContainsString('www.myapp.local', $content);
        $this->assertStringContainsString('api.myapp.local', $content);
    }

    public function testIntegrationSkipsNotRunningContainers(): void
    {
        // Arrange
        $containerData = [
            [
                'id'       => 'running-container',
                'name'     => '/running',
                'image'    => 'nginx:alpine',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'bridge' => ['ip' => '172.17.0.2', 'aliases' => []],
                ],
                'ports' => ['80/tcp' => []],
            ],
            [
                'id'       => 'stopped-container',
                'name'     => '/stopped',
                'image'    => 'nginx:alpine',
                'running'  => false,
                'env'      => [],
                'networks' => [
                    'bridge' => ['ip' => '172.17.0.3', 'aliases' => []],
                ],
                'ports' => ['80/tcp' => []],
            ],
        ];

        $clientMock    = $this->createDockerClientMock($containerData);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: $this->tempHostsFile,
            tld: '.docker',
        );

        // Act
        $this->invokeInit($service);
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);

        // Running container should be present
        $this->assertStringContainsString('172.17.0.2', $content);
        $this->assertStringContainsString('running.bridge', $content);

        // Stopped container should NOT be present (not exposed because not running)
        $this->assertStringNotContainsString('172.17.0.3', $content);
        $this->assertStringNotContainsString('stopped', $content);
    }

    public function testIntegrationSkipsContainersWithoutPorts(): void
    {
        // Arrange
        $containerData = [
            [
                'id'       => 'with-ports',
                'name'     => '/exposed',
                'image'    => 'nginx:alpine',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'bridge' => ['ip' => '172.17.0.2', 'aliases' => []],
                ],
                'ports' => ['80/tcp' => []],
            ],
            [
                'id'       => 'without-ports',
                'name'     => '/hidden',
                'image'    => 'redis:alpine',
                'running'  => true,
                'env'      => [],
                'networks' => [
                    'bridge' => ['ip' => '172.17.0.3', 'aliases' => []],
                ],
                'ports' => [],
            ],
        ];

        $clientMock    = $this->createDockerClientMock($containerData);
        $dockerService = $this->createDockerServiceWithMockedClient($clientMock);

        $service = new SynchronizeHostsFileService(
            dockerService: $dockerService,
            hostsFile: $this->tempHostsFile,
            tld: '.docker',
        );

        // Act
        $this->invokeInit($service);
        $this->invokeRegenerateHostsFile($service);

        // Assert
        $content = file_get_contents($this->tempHostsFile);

        // Container with ports should be present
        $this->assertStringContainsString('172.17.0.2', $content);
        $this->assertStringContainsString('exposed.bridge', $content);

        // Container without ports should NOT be present (not exposed)
        $this->assertStringNotContainsString('172.17.0.3', $content);
        $this->assertStringNotContainsString('hidden', $content);
    }

    // =========================================================================
    // Regenerate Hosts File Tests (using reflection with DockerContainerDto)
    // =========================================================================

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

    // =========================================================================
    // Helper Methods
    // =========================================================================

    /**
     * Creates a mock of the Docker API Client.
     *
     * @param array<array{id: string, name: string, image: string, running: bool, env: array<string>, networks: array<string, array{ip: string, aliases: array<string>}>, ports: array<string, mixed>}> $containerData
     *
     * @return Client&MockObject
     */
    private function createDockerClientMock(array $containerData): Client
    {
        $mock = $this->createMock(Client::class);

        // Create ContainerSummary objects for containerList()
        $containerSummaries = [];
        $containerInspects  = [];

        foreach ($containerData as $data) {
            $summary = new ContainerSummary();
            $summary->setId($data['id']);
            $summary->setNames([$data['name']]);
            $summary->setImage($data['image']);
            $containerSummaries[] = $summary;

            // Create ContainerInspectResponse
            $inspect                        = $this->createContainerInspectResponse($data);
            $containerInspects[$data['id']] = $inspect;
        }

        $mock->method('containerList')
            ->willReturn($containerSummaries);

        $mock->method('containerInspect')
            ->willReturnCallback(static fn (string $id) => $containerInspects[$id] ?? null);

        return $mock;
    }

    /**
     * Creates a ContainerInspectResponse from test data.
     *
     * @param array{id: string, name: string, image: string, running: bool, env: array<string>, networks: array<string, array{ip: string, aliases: array<string>}>, ports: array<string, mixed>} $data
     */
    private function createContainerInspectResponse(array $data): ContainerInspectResponse
    {
        $response = new ContainerInspectResponse();
        $response->setId($data['id']);
        $response->setName($data['name']);
        $response->setImage($data['image']);

        // State
        $state = new ContainerState();
        $state->setRunning($data['running']);
        $response->setState($state);

        // Config with environment variables
        $config = new ContainerConfig();
        $config->setEnv($data['env']);
        $response->setConfig($config);

        // Network settings
        $networkSettings = new NetworkSettings();
        $networks        = [];

        foreach ($data['networks'] as $networkName => $networkData) {
            $endpoint = new EndpointSettings();
            $endpoint->setIPAddress($networkData['ip']);
            $endpoint->setAliases($networkData['aliases']);
            $networks[$networkName] = $endpoint;
        }

        $networkSettings->setNetworks($networks);
        $networkSettings->setPorts($data['ports']);
        $response->setNetworkSettings($networkSettings);

        return $response;
    }

    /**
     * Creates a DockerService with a mocked Client injected through DockerApiClientWrapper.
     */
    private function createDockerServiceWithMockedClient(Client $clientMock): DockerService
    {
        $wrapper = new DockerApiClientWrapper(
            baseUri: 'http://localhost',
            socketPath: '/var/run/docker.sock',
            client: $clientMock,
        );

        return new DockerService(
            dockerApiClient: $wrapper,
        );
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
     * Invokes the private init method via reflection.
     */
    private function invokeInit(SynchronizeHostsFileService $service): void
    {
        $reflection = new ReflectionClass(SynchronizeHostsFileService::class);
        $method     = $reflection->getMethod('init');
        $method->invoke($service);
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
