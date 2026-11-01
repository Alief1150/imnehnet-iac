<?php

namespace App\Services\GuidedCtf;

use App\Enums\EvidenceType;
use App\Enums\ValidationType;
use App\Models\LabSession;
use App\Models\Objective;

/**
 * Decides whether one objective is proven, from what the victim recorded.
 *
 * The specification rules out command matching explicitly, and this is the class
 * that makes that ruling real. Nothing here is told what the student typed; every
 * verdict is reached from a record the victim wrote about itself.
 *
 * The consequence worth stating: a student who solves a problem by an unexpected
 * route is credited, and a student who types the expected command and gets nowhere
 * is not. That is the correct behaviour for a lab, and it is also the behaviour that
 * cannot be gamed by reading the validator.
 */
class ObjectiveValidator
{
    public function __construct(private readonly VictimEvidenceReader $evidence) {}

    /**
     * @return ObjectiveVerdict
     */
    public function verify(LabSession $session, Objective $objective): ObjectiveVerdict
    {
        // A flag objective has no event rule because it is not settled by one. It is
        // reached through FlagSubmission, which reads the flag out of the victim and
        // compares. Saying so is better than reporting a missing rule, which reads
        // as a fault in the course.
        if ($objective->validation_type === ValidationType::Flag) {
            return ObjectiveVerdict::unproven(
                'Submit the flag you retrieved to complete this objective.',
            );
        }

        return match ($objective->evidence_type) {
            EvidenceType::VictimConnection => $this->connectionReached($session),
            EvidenceType::VictimPortScan => $this->portsReached($session, $objective),
            EvidenceType::VictimHttpRequest => $this->requestMatched($session, $objective),
            EvidenceType::VictimAuthenticated => $this->authenticated($session),
            // A null evidence type on an event objective is a mistake in the course
            // definition, not a student failing. Reported as unproven with the
            // reason rather than passed, so it is visible.
            default => ObjectiveVerdict::unproven('This objective has no evidence rule defined.'),
        };
    }

    /**
     * Objective one: the victim recorded contact from the attacker path.
     *
     * Any recorded service traffic counts. A ping does not appear here, because ICMP
     * opens no socket and writes no service log; the objective is worded around
     * establishing a connection for that reason, and this is where that wording is
     * paid for.
     */
    private function connectionReached(LabSession $session): ObjectiveVerdict
    {
        $requests = $this->evidence->httpRequests($session);

        if ($requests !== []) {
            return ObjectiveVerdict::proven([
                'source' => 'victim web access log',
                'detail' => sprintf(
                    '%s %s returned %d',
                    $requests[array_key_last($requests)]['method'],
                    $requests[array_key_last($requests)]['target'],
                    $requests[array_key_last($requests)]['status'],
                ),
            ]);
        }

        $addresses = $this->evidence->sshConnectionAddresses($session);

        if ($addresses !== []) {
            return ObjectiveVerdict::proven([
                'source' => 'victim ssh log',
                'detail' => 'connection accepted from '.$addresses[0],
            ]);
        }

        return ObjectiveVerdict::unproven(
            'The victim has recorded no connection from your attacker environment.',
        );
    }

    /**
     * Objective two: more than one destination port reached.
     *
     * The threshold is data on the objective, not a constant here, so the course can
     * ask for a wider sweep without a code change.
     */
    private function portsReached(LabSession $session, Objective $objective): ObjectiveVerdict
    {
        $required = max(2, (int) $objective->evidence_value);

        $ports = $this->evidence->contactedServicePorts($session);

        if (count($ports) >= $required) {
            return ObjectiveVerdict::proven([
                'source' => 'victim service logs',
                'detail' => 'reached ports '.implode(', ', $ports),
            ]);
        }

        return ObjectiveVerdict::unproven(sprintf(
            'Your target has recorded contact on %d port%s so far. %d %s needed.',
            count($ports),
            count($ports) === 1 ? '' : 's',
            $required,
            $required === 1 ? 'is' : 'are',
        ));
    }

    /**
     * Objectives three, four and five: a request the victim served whose target
     * matched the value on the objective.
     *
     * Matching is containment on the request target, which means the objective can
     * require "/admin?file=.." without knowing what path the student chose after the
     * traversal. A response of any status counts: a 404 on a guessed path is still
     * evidence that the request was made, and demanding a 200 would reward knowing
     * the answer rather than looking for it.
     */
    private function requestMatched(LabSession $session, Objective $objective): ObjectiveVerdict
    {
        $needle = (string) $objective->evidence_value;

        if ($needle === '') {
            return ObjectiveVerdict::unproven('This objective has no evidence value defined.');
        }

        foreach ($this->evidence->httpRequests($session) as $request) {
            if (str_contains($request['target'], $needle)) {
                return ObjectiveVerdict::proven([
                    'source' => 'victim web access log',
                    'detail' => sprintf('%s %s returned %d', $request['method'], $request['target'], $request['status']),
                ]);
            }
        }

        return ObjectiveVerdict::unproven(
            'Your target has not recorded a request matching '.$needle.'.',
        );
    }

    /**
     * Reserved for objectives needing more than reachability.
     *
     * Honest about its own state rather than passing: no objective in the MVP course
     * is defined with this evidence type, so returning "unproven" is a statement about
     * the course, not about the student.
     */
    private function authenticated(LabSession $session): ObjectiveVerdict
    {
        return ObjectiveVerdict::unproven(
            'This objective is not yet defined for this course.',
        );
    }
}