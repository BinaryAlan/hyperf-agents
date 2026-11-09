<?php

declare(strict_types=1);

namespace HyperfAgents\Tests;

use HyperfAgents\ConfigProvider;
use PHPUnit\Framework\TestCase;

class ConfigProviderTest extends TestCase
{
    public function testConfigProviderStructure(): void
    {
        $provider = new ConfigProvider();
        $config = $provider();

        $this->assertIsArray($config);
        $this->assertArrayHasKey('dependencies', $config);
        $this->assertArrayHasKey('publish', $config);

        // Verify dependencies
        foreach ($config['dependencies'] as $interface => $class) {
            $this->assertTrue(
                interface_exists($interface) || class_exists($interface),
                "Interface or class {$interface} must exist."
            );
            $this->assertTrue(
                class_exists($class),
                "Concrete class {$class} must exist."
            );
            $this->assertTrue(
                is_subclass_of($class, $interface) || $class === $interface,
                "Class {$class} must implement or extend {$interface}."
            );
        }

        // Verify publish file exists
        foreach ($config['publish'] as $publishItem) {
            $this->assertFileExists(
                $publishItem['source'],
                "Publish source file {$publishItem['source']} must exist on disk."
            );
        }
    }
}
