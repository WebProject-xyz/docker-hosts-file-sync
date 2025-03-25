<?php
declare(strict_types=1);

namespace WebProject\DockerApiClient\Tests\Unit\Util;

use PHPUnit\Framework\Attributes\CoversClass;
use WebProject\DockerApiClient\Dto\DockerContainerDto;
use WebProject\DockerApiClient\Tests\Support\UnitTester;
use WebProject\DockerApiClient\Util\ContainerToHostsFileLinesUtil;

#[CoversClass(DockerContainerDto::class)]
#[CoversClass(ContainerToHostsFileLinesUtil::class)]
final class ContainerToHostsFileLineUtilTest extends \Codeception\Test\Unit
{
    protected UnitTester $tester;

    protected function _before(): void
    {
    }

    public function testGetExpectedHostFileWithoutIp(): void
    {
        // Arrange
        $expected = [];

        $container = new DockerContainerDto(
            id: 'id',
            name: 'name',
            image: 'image',
            running: true,
            envVariables: [],
            ipAddresses: [],
            networks: [],
            ports: [],
        );

        $util = new ContainerToHostsFileLinesUtil();
        // Act

        $actual = $util($container, '.tld', []);
        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testGetExpectedHostFileWithIp(): void
    {
        // Arrange
        $expected = [
            // IP => LINE
            '192.12.12.12' => '192.12.12.12 docker-name.network1',
        ];

        $container = new DockerContainerDto(
            id: 'id',
            name: '/docker-name',
            image: 'image',
            running: true,
            envVariables: [],
            ipAddresses: [],
            networks: ['network1' => ['ip' => '192.12.12.12', 'aliases' => []]],
            ports: [],
        );

        $util = new ContainerToHostsFileLinesUtil();
        // Act
        $actual = $util($container, '.tld', []);
        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testGetExpectedHostFileWithIpAndNetworkAliases(): void
    {
        // Arrange
        $expected = [
            // IP => LINE
            '192.12.12.12' => '192.12.12.12 docker-name.tld alias1.network1 alias2.network1 docker-name.network1',
        ];

        $container = new DockerContainerDto(
            id: 'id',
            name: '/docker-name',
            image: 'image',
            running: true,
            envVariables: [],
            ipAddresses: ['192.12.12.12'],
            networks: [
                'network1' => [
                    'ip'      => '192.12.12.12',
                    'aliases' => ['alias1', 'alias2'],
                ],
            ],
            ports: [],
        );

        $util = new ContainerToHostsFileLinesUtil();
        // Act
        $actual = $util($container, '.tld', []);
        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testGetExpectedHostFileWithIpWithTldFromAlias(): void
    {
        // Arrange
        $expected = [
            // IP => LINE
            '192.12.12.12' => '192.12.12.12 docker-name.tld alias1.network1 docker-name.network1',
        ];

        $container = new DockerContainerDto(
            id: 'id',
            name: '/docker-name',
            image: 'image',
            running: true,
            envVariables: [],
            ipAddresses: ['192.12.12.12'],
            networks: [
                'network1' => [
                    'ip'      => '192.12.12.12',
                    'aliases' => ['alias1', 'dev-url.myservice.web'],
                ],
            ],
            ports: [],
        );

        $util = new ContainerToHostsFileLinesUtil();
        // Act
        $actual = $util($container, '.tld', []);
        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testGetExpectedHostFileWithIpAndNetworkAliasesAndMultipleNetworks(): void
    {
        // Arrange
        $expected = [
            // IP => LINE
            '192.12.12.12' => '192.12.12.12 docker-name.tld alias1.network1 alias2.network1 docker-name.network1',
            '22.12.12.12'  => '22.12.12.12 alias1-in-2.network-2 docker-name.network-2',
        ];

        $container = new DockerContainerDto(
            id: 'id',
            name: '/docker-name',
            image: 'image',
            running: true,
            envVariables: [],
            ipAddresses: ['192.12.12.12', '22.12.12.12'],
            networks: [
                'network1' => [
                    'ip'      => '192.12.12.12',
                    'aliases' => ['alias1', 'alias2'],
                ],
                'network-2' => [
                    'ip'      => '22.12.12.12',
                    'aliases' => ['alias1-in-2'],
                ],
            ],
            ports: [],
        );

        $util = new ContainerToHostsFileLinesUtil();
        // Act
        $actual = $util($container, '.tld', []);
        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testGetExpectedHostFileWithIpAndNetworkAliasesAndMultipleNetworksAndEnvVars(): void
    {
        // Arrange
        $expected = [
            // IP => LINE
            '192.12.12.12' => '192.12.12.12 docker-name.tld alias1.network1 alias2.network1 docker-name.network1',
            '22.12.12.12'  => '22.12.12.12 alias1-in-2.network-2 docker-name.network-2',
        ];

        $container = new DockerContainerDto(
            id: 'id',
            name: '/docker-name',
            image: 'image',
            running: true,
            envVariables: [
                'DOMAIN_NAME' => 'docker-name-domainname.env.var',
            ],
            ipAddresses: ['192.12.12.12', '22.12.12.12'],
            networks: [
                'network1' => [
                    'ip'      => '192.12.12.12',
                    'aliases' => ['alias1', 'alias2'],
                ],
                'network-2' => [
                    'ip'      => '22.12.12.12',
                    'aliases' => ['alias1-in-2'],
                ],
            ],
            ports: [],
        );

        $util = new ContainerToHostsFileLinesUtil();
        // Act
        $actual = $util($container, '.tld', ['DOMAIN_NAME']);
        // Assert
        $this->assertSame($expected, $actual);
    }

    public function testGetExpectedHostFileWithIpAndNetworkAliasesAndMultipleNetworksAndEnvVarsReverseProxyIp(): void
    {
        // Arrange
        $expected = [
            // IP => LINE
            '192.12.12.12' => '192.12.12.12 docker-name.tld alias1.network1 alias2.network1 docker-name.network1',
            '22.12.12.12'  => '22.12.12.12 alias1-in-2.network-2 docker-name.network-2',
            // reverse proxy ip with hostname from env var
            '192.12.12.100' => '192.12.12.100 alias2-is-url.format docker-name-domainname.env.var',
        ];

        $container = new DockerContainerDto(
            id: 'id',
            name: '/docker-name',
            image: 'image',
            running: true,
            envVariables: [
                'DOMAIN_NAME' => 'docker-name-domainname.env.var',
            ],
            ipAddresses: ['192.12.12.12', '22.12.12.12'],
            networks: [
                'network1' => [
                    'ip'      => '192.12.12.12',
                    'aliases' => ['alias1', 'alias2'],
                ],
                'network-2' => [
                    'ip'      => '22.12.12.12',
                    'aliases' => ['alias1-in-2', 'alias2-is-url.format'],
                ],
            ],
            ports: [],
        );

        $util = new ContainerToHostsFileLinesUtil();
        // Act
        $actual = $util($container, '.tld', ['DOMAIN_NAME'], '192.12.12.100');
        // Assert
        $this->assertSame($expected, $actual);
    }
}
