<?php

namespace Tests\Unit\GuidedCtf;

use App\Infrastructure\Health\CommandRunner;
use App\Services\GuidedCtf\ContainerAbsenceVerifier;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ContainerAbsenceVerifierTest extends TestCase
{
    private function verifier(array $response, string $remote = 'lab-atk'): ContainerAbsenceVerifier
    {
        $commands = Mockery::mock(CommandRunner::class);

        $commands->shouldReceive('run')
            ->once()
            ->with(['incus', 'list', $remote.':', '--format=json'], 20)
            ->andReturn($response);

        return new ContainerAbsenceVerifier($commands);
    }

    public function test_reports_absent_when_inventory_is_valid_and_empty(): void
    {
        $verifier = $this->verifier([
            'successful' => true,
            'stdout' => '[]',
            'stderr' => '',
        ]);

        $this->assertTrue($verifier->isAbsent('lab-atk', 'ctf-attacker-150'));
    }

    public function test_reports_present_for_exact_container_name(): void
    {
        $verifier = $this->verifier([
            'successful' => true,
            'stdout' => '[{"name":"ctf-attacker-150"}]',
            'stderr' => '',
        ]);

        $this->assertFalse($verifier->isAbsent('lab-atk', 'ctf-attacker-150'));
    }

    public function test_does_not_confuse_similar_container_names(): void
    {
        $verifier = $this->verifier([
            'successful' => true,
            'stdout' => '[{"name":"ctf-attacker-150-old"}]',
            'stderr' => '',
        ]);

        $this->assertTrue($verifier->isAbsent('lab-atk', 'ctf-attacker-150'));
    }

    public function test_rejects_unavailable_remote(): void
    {
        $verifier = $this->verifier([
            'successful' => false,
            'stdout' => '',
            'stderr' => 'Command unavailable or failed.',
        ]);

        $this->expectException(RuntimeException::class);

        $verifier->isAbsent('lab-atk', 'ctf-attacker-150');
    }

    public function test_rejects_invalid_inventory(): void
    {
        $verifier = $this->verifier([
            'successful' => true,
            'stdout' => 'not-json',
            'stderr' => '',
        ]);

        $this->expectException(RuntimeException::class);

        $verifier->isAbsent('lab-atk', 'ctf-attacker-150');
    }

    public function test_rejects_incomplete_inventory(): void
    {
        $verifier = $this->verifier([
            'successful' => true,
            'stdout' => '[{"status":"Running"}]',
            'stderr' => '',
        ]);

        $this->expectException(RuntimeException::class);

        $verifier->isAbsent('lab-atk', 'ctf-attacker-150');
    }

    public function test_rejects_invalid_identifiers_without_running_commands(): void
    {
        $commands = Mockery::mock(CommandRunner::class);
        $commands->shouldNotReceive('run');

        $verifier = new ContainerAbsenceVerifier($commands);

        $this->expectException(RuntimeException::class);

        $verifier->isAbsent('lab-atk', '../other-container');
    }

    public function test_supports_victim_remote(): void
    {
        $verifier = $this->verifier([
            'successful' => true,
            'stdout' => '[]',
            'stderr' => '',
        ], 'lab-env');

        $this->assertTrue($verifier->isAbsent('lab-env', 'ctf-victim-150'));
    }
}
