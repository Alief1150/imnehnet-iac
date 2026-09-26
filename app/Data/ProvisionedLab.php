<?php

namespace App\Data;

final readonly class ProvisionedLab
{
    public function __construct(
        public bool $successful,
        public ?string $containerName = null,
        public ?string $ipAddress = null,
        public ?int $sshPort = null,
        public ?int $webPort = null,
        public ?string $status = null,
        public ?string $accessUsername = null,
        public ?string $errorCode = null,
        public ?string $message = null,
        /** @var array<string, mixed> */
        public array $detail = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'successful' => $this->successful,
            'container_name' => $this->containerName,
            'ip_address' => $this->ipAddress,
            'ssh_port' => $this->sshPort,
            'web_port' => $this->webPort,
            'status' => $this->status,
            'access_username' => $this->accessUsername,
            'error_code' => $this->errorCode,
            'message' => $this->message,
            'detail' => $this->detail,
        ];
    }
}
