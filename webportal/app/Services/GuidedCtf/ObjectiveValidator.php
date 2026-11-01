<?php

namespace App\Services\GuidedCtf;

use App\Enums\EvidenceType;
use App\Enums\ValidationType;
use App\Models\LabSession;
use App\Models\Objective;

final class ObjectiveValidator
{
    public function __construct(private readonly CtfEvidence $evidence) {}

    public function verify(LabSession $session, Objective $objective): ObjectiveVerdict
    {
        if ($objective->validation_type === ValidationType::Flag) {
            return ObjectiveVerdict::unproven('Submit flag secara manual.');
        }
        $state = $this->evidence->read($session);
        $proven = match ($objective->evidence_type) {
            EvidenceType::VictimConnection => ($state['host'] ?? 0) > 0,
            EvidenceType::VictimPortScan => count($state['ports'] ?? []) >= max(2, (int) $objective->evidence_value),
            EvidenceType::VictimHttpRequest => match ((string) $objective->evidence_value) {
                '/robots.txt' => ($state['http']['robots'] ?? 0) > 0,
                '/n0te-4-admin/' => ($state['http']['directory'] ?? 0) > 0,
                '/admin?file=..' => ($state['http']['traversal'] ?? 0) > 0,
                default => false,
            },
            default => false,
        };

        return $proven ? ObjectiveVerdict::proven([
            'source' => 'root-owned CTF UID network observer',
            'detail' => 'Objective '.$objective->sequence.': respons target untuk aktivitas akun CTF terverifikasi.',
            'epoch' => $session->ctf_epoch,
        ]) : ObjectiveVerdict::unproven('Belum ada bukti aktivitas CTF yang berhasil pada target lab ini.');
    }
}
