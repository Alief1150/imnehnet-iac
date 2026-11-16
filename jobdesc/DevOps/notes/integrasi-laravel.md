iaas@iaas-mgmt:~/imnehnet-iac/ansible$ cd /home/iaas/projects/webportal-iaas

echo "========== AUTOMATION SERVICES =========="

grep -nE \
  'function |public function |private function ' \
  app/Services/OpenTofuService.php \
  app/Services/AnsibleService.php

echo
echo "========== PROVISIONING JOB =========="

grep -nE \
  'apply|configure|bootstrap|markConfiguring|settle|markReady|permit' \
  app/Jobs/ProvisionEnvironment.php \
  app/Jobs/ProvisionLabSession.php
========== AUTOMATION SERVICES ==========
app/Services/OpenTofuService.php:37:    public function __construct(
app/Services/OpenTofuService.php:53:    public static function fromConfig(): self
app/Services/OpenTofuService.php:68:    public function reconcilePairedWorkspace(LabEnvironment $environment): void
app/Services/OpenTofuService.php:70:        $this->withDirectoryLock(function () use ($environment): void {
app/Services/OpenTofuService.php:128:    public function workspaceFor(LabEnvironment $environment): string
app/Services/OpenTofuService.php:143:    public function withWorkspace(LabEnvironment $environment, callable $sequence): mixed
app/Services/OpenTofuService.php:160:    public function selectOrCreateWorkspace(string $workspace): ProcessResult
app/Services/OpenTofuService.php:177:    public function plan(array $variables = []): ProcessResult
app/Services/OpenTofuService.php:185:    public function apply(array $variables = []): ProcessResult
app/Services/OpenTofuService.php:190:    public function destroy(): ProcessResult
app/Services/OpenTofuService.php:199:    public function outputs(): ProcessResult
app/Services/OpenTofuService.php:207:    public function readOutputs(): array
app/Services/OpenTofuService.php:241:    public static function flattenOutputs(array $decoded): array
app/Services/OpenTofuService.php:284:    public function deleteWorkspace(string $workspace): ProcessResult
app/Services/OpenTofuService.php:303:    private function fallbackWorkspace(): string
app/Services/OpenTofuService.php:322:    public function destroyWorkspaceResourcesIfPresent(
app/Services/OpenTofuService.php:336:        $this->withDirectoryLock(function () use ($workspace): void {
app/Services/OpenTofuService.php:373:    public function cleanupWorkspaceIfPresent(LabEnvironment $environment): void
app/Services/OpenTofuService.php:386:        $this->withDirectoryLock(function () use ($workspace): void {
app/Services/OpenTofuService.php:435:    public function workspaceExists(string $workspace): bool
app/Services/OpenTofuService.php:442:        return $this->withDirectoryLock(function () use ($workspace): bool {
app/Services/OpenTofuService.php:459:    public function listWorkspaces(): ProcessResult
app/Services/OpenTofuService.php:467:    public function parseWorkspaceList(string $output): array
app/Services/OpenTofuService.php:489:    public function run(array $arguments): ProcessResult
app/Services/OpenTofuService.php:537:    public function withDirectoryLock(callable $callback): mixed
app/Services/OpenTofuService.php:584:    public function holdsLock(): bool
app/Services/OpenTofuService.php:593:    private function variableArgs(array $variables): array
app/Services/AnsibleService.php:26:    public function __construct(
app/Services/AnsibleService.php:39:    public static function fromConfig(): self
app/Services/AnsibleService.php:51:    public function bootstrap(LabEnvironment $environment): ProcessResult
app/Services/AnsibleService.php:79:    public function ping(): ProcessResult
app/Services/AnsibleService.php:109:    public function version(): ProcessResult
app/Services/AnsibleService.php:117:    public function run(array $arguments, ?int $timeoutSeconds = null): ProcessResult

========== PROVISIONING JOB ==========
app/Jobs/ProvisionEnvironment.php:62:        // Read here, before the bootstrap runs. The address and the runtime the
app/Jobs/ProvisionEnvironment.php:63:        // record settles with both come from the apply; the Ansible phase reports
app/Jobs/ProvisionEnvironment.php:76:        $lifecycle->markConfiguring($actor, $environment->refresh());
app/Jobs/ProvisionEnvironment.php:79:            $configured = $driver->configure($environment);
app/Jobs/ProvisionEnvironment.php:93:        // The container exists and its address is known, so a bootstrap failure
app/Jobs/ProvisionEnvironment.php:98:        if (! $configured->successful()) {
app/Jobs/ProvisionEnvironment.php:100:                command: $configured->command,
app/Jobs/ProvisionEnvironment.php:101:                exitCode: $configured->exitCode,
app/Jobs/ProvisionEnvironment.php:103:                stderr: $configured->stderr !== '' ? $configured->stderr : $configured->stdout,
app/Jobs/ProvisionEnvironment.php:104:                durationMs: $applied->durationMs + $configured->durationMs,
app/Jobs/ProvisionEnvironment.php:105:                errorCode: $configured->errorCode ?? 'configure_failed',
app/Jobs/ProvisionEnvironment.php:111:        // settle() is where a result becomes state, so an exception thrown inside
app/Jobs/ProvisionEnvironment.php:117:            $lifecycle->settle(
app/Jobs/ProvisionEnvironment.php:121:                $this->combine($applied, $configured),
app/Jobs/ProvisionEnvironment.php:130:                durationMs: $applied->durationMs + $configured->durationMs,
app/Jobs/ProvisionEnvironment.php:131:                errorCode: 'settle_failed',
app/Jobs/ProvisionEnvironment.php:137:     * The record of a two phase operation, for the log entry that settles it.
app/Jobs/ProvisionLabSession.php:26: * Builds both halves of a lab, permits the pair, and applies the scenario.
app/Jobs/ProvisionLabSession.php:33: *   3. permit the pair         — until this exists the attacker cannot reach the
app/Jobs/ProvisionLabSession.php:35: *   4. configure the scenario  — until this exists the victim serves nothing worth
app/Jobs/ProvisionLabSession.php:125:                $this->applyInstance(
app/Jobs/ProvisionLabSession.php:141:                $this->applyInstance(
app/Jobs/ProvisionLabSession.php:163:                $bootstrap = new Process([
app/Jobs/ProvisionLabSession.php:178:                $bootstrap->setEnv(['INCUS_CONF' => (string) env('INCUS_CONF', '/var/lib/imnehnet-incus')]);
app/Jobs/ProvisionLabSession.php:179:                $bootstrap->setTimeout(60);
app/Jobs/ProvisionLabSession.php:180:                $bootstrap->run();
app/Jobs/ProvisionLabSession.php:182:                if (! $bootstrap->isSuccessful()) {
app/Jobs/ProvisionLabSession.php:184:                        'Student terminal bootstrap failed: '.
app/Jobs/ProvisionLabSession.php:185:                        trim($bootstrap->getErrorOutput())
app/Jobs/ProvisionLabSession.php:201:                // --- 3. permit the pair --------------------------------------------
app/Jobs/ProvisionLabSession.php:202:                $firewall->permit($session->refresh());
app/Jobs/ProvisionLabSession.php:209:                $scenario->apply($session->refresh());
app/Jobs/ProvisionLabSession.php:211:                $sessions->markReady($session->refresh());
app/Jobs/ProvisionLabSession.php:272:            // Recorded and moved past. A pair that stays permitted is bad, but a
app/Jobs/ProvisionLabSession.php:296:    private function applyInstance(
app/Jobs/ProvisionLabSession.php:324:        $applied = $tofu->apply($variables);
