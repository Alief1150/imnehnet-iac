<?php

namespace App\Enums;

/**
 * Which record on the victim carries the evidence for an event objective.
 *
 * This is the concrete form of "event or state based validation". Each case names
 * a place the victim writes down what happened to it, and the validator reads that
 * place rather than watching the student's keystrokes.
 */
enum EvidenceType: string
{
    /**
     * The victim recorded a TCP connection from the paired attacker.
     *
     * Reachability leaves this. A ping does not, because ICMP reaches no
     * listening socket and never appears in a service log. That is honest rather
     * than a gap, and objective one is worded so that establishing a connection is
     * what is being demonstrated, not an echo reply.
     */
    case VictimConnection = 'victim_connection';

    /**
     * The victim recorded contact on at least a given number of distinct ports.
     *
     * Separated from VictimConnection because otherwise a scan and a single
     * connect are the same evidence, and objectives one and two would be the same
     * test twice. The threshold lives in the objective's evidence_value.
     *
     * Ports are counted across every source the victim keeps, because a port with
     * nothing listening on it writes no service log at all. A student who runs a
     * full port scan and finds two open services has contacted two ports, and that
     * is what this counts.
     */
    case VictimPortScan = 'victim_port_scan';

    /**
     * The victim's web server received a request whose target matched a value.
     *
     * The value is matched against the request target as the server saw it, so it
     * may include a query string. Matching is containment rather than equality, so
     * one objective can require `/admin?file=` without having to know the value
     * the student chose.
     *
     * A non-2xx response still counts. A 404 on a guessed path is evidence that
     * the request was made, and for a discovery objective that is exactly the
     * thing being demonstrated. Requiring a 200 would reward knowing the answer.
     */
    case VictimHttpRequest = 'victim_http_request';

    /**
     * The victim recorded an authenticated session for the paired attacker.
     *
     * Reserved for objectives that need proof of more than reachability.
     */
    case VictimAuthenticated = 'victim_authenticated';
}