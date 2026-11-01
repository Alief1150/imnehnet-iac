<?php

namespace App\Contracts;

use App\Data\ProcessResult;
use App\Models\LabEnvironment;

/**
 * The boundary between the portal and whatever actually runs the lab.
 *
 * The lifecycle services only ever speak this interface, so the orchestration
 * can be exercised without a hypervisor, a queue worker or a network. The
 * live implementation talks to OpenTofu and Ansible; a test can supply a
 * double that records what it was asked to do.
 *
 * Every method returns a ProcessResult rather than throwing for an
 * infrastructure failure: an operator wants to see that OpenTofu exited with
 * code 1, not a stack trace.
 */
interface LifecycleDriver
{
    /**
     * Create the container and return its real address and runtime state as
     * reported by the infrastructure, never as the operator typed it.
     */
    public function provision(LabEnvironment $environment): ProcessResult;

    /**
     * Converge the environment onto running. Declarative: the desired state is
     * set and the infrastructure is asked to converge onto it, rather than
     * toggling something and hoping.
     */
    public function start(LabEnvironment $environment): ProcessResult;

    public function stop(LabEnvironment $environment): ProcessResult;

    /**
     * Destroy and recreate. The container comes back with a new identity, so
     * the address is expected to change and is re-read afterwards.
     */
    public function reset(LabEnvironment $environment): ProcessResult;

    /**
     * Destroy the container and remove the workspace it owned.
     */
    public function destroy(LabEnvironment $environment): ProcessResult;

    /**
     * Read-only. Reports what the infrastructure currently believes, without
     * changing anything. Used for reconciliation and for the refresh action.
     */
    public function observe(LabEnvironment $environment): ProcessResult;

    /**
     * Run the configuration step for an environment that already exists.
     */
    public function configure(LabEnvironment $environment): ProcessResult;
}
