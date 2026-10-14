<?php

namespace Tests\Feature\Deployment;

use Symfony\Component\Yaml\Yaml;
use Tests\TestCase;

class StorageVolumeTest extends TestCase
{
    public function test_shared_storage_is_initialized_before_the_app_and_workers_start(): void
    {
        $services = Yaml::parseFile(base_path('docker-compose.yml'))['services'];

        $this->assertSame('0:0', $services['storage-init']['user']);
        $this->assertSame(['init-storage'], $services['storage-init']['command']);
        $this->assertSame('service_completed_successfully', $services['app']['depends_on']['storage-init']['condition']);
        $this->assertSame('no', $services['storage-init']['restart']);

        foreach (['storage-init', 'app', 'horizon', 'scheduler'] as $service) {
            $this->assertSame($services['app']['image'], $services[$service]['image']);
            $this->assertContains('storage:/app/storage', $services[$service]['volumes']);
        }

        foreach (['horizon', 'scheduler'] as $service) {
            $this->assertSame('service_healthy', $services[$service]['depends_on']['app']['condition']);
        }

        $this->assertStringContainsString("\nUSER www-data\n", file_get_contents(base_path('Dockerfile')));
    }

    public function test_local_compose_uses_the_built_image_to_initialize_storage(): void
    {
        $services = Yaml::parseFile(base_path('docker-compose.dev.yml'))['services'];

        $this->assertSame($services['app']['image'], $services['storage-init']['image']);
    }
}
