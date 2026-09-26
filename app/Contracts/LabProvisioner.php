<?php

namespace App\Contracts;

use App\Data\ProvisionedLab;
use App\Models\Lab;

interface LabProvisioner
{
    /**
     * Create the compute resource for a lab and return what the infrastructure
     * actually reported, never what was requested.
     */
    public function provision(Lab $lab): ProvisionedLab;

    public function start(Lab $lab): ProvisionedLab;

    public function stop(Lab $lab): ProvisionedLab;

    /**
     * Return the lab to its baseline: recreate the container from the base
     * image, then reapply limits, network and isolation policy.
     */
    public function reset(Lab $lab): ProvisionedLab;

    /**
     * Remove the infrastructure resource. The database record is handled by
     * the caller so that audit history survives.
     */
    public function destroy(Lab $lab): ProvisionedLab;

    /**
     * Read the current state from the infrastructure without changing it.
     * Used to reconcile the portal against reality.
     */
    public function observe(Lab $lab): ProvisionedLab;
}
