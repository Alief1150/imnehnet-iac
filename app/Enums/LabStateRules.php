<?php

namespace App\Enums;

/**
 * Central definition of which enum cases mean "an operation is in flight" and
 * "the infrastructure is not there any more".
 *
 * The two checks appear in the lifecycle service and in the UI. Declaring them
 * once here stops a case name from drifting between the two, which is how a
 * deleted case such as LabOperationState::Running reached a view.
 */
final class LabStateRules
{
    public const OPERATION_IN_FLIGHT = [
        LabOperationState::Queued,
        LabOperationState::Provisioning,
        LabOperationState::Starting,
        LabOperationState::Stopping,
        LabOperationState::Resetting,
        LabOperationState::Deleting,
        LabOperationState::Reconciling,
    ];

    public const CONTAINER_GONE = [
        LabActualState::Absent,
        LabActualState::Missing,
        LabActualState::Error,
    ];

    public static function isInFlight(LabOperationState $state): bool
    {
        return in_array($state, self::OPERATION_IN_FLIGHT, true);
    }

    public static function isGone(LabActualState $state): bool
    {
        return in_array($state, self::CONTAINER_GONE, true);
    }
}
