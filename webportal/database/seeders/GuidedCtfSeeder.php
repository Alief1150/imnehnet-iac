<?php

namespace Database\Seeders;

use App\Enums\EvidenceType;
use App\Enums\ValidationType;
use App\Models\Course;
use App\Models\Objective;
use Illuminate\Database\Seeder;

/**
 * The one course of the MVP, with its six objectives.
 *
 * ── On the hints ────────────────────────────────────────────────────────────────
 *
 * The hints carry the command that solves the objective. An earlier reading of the
 * specification held that a hint should name a category of approach rather than a
 * command, on the grounds that "run ping" makes the objective a tutorial. That is
 * sound for an unguided lab and wrong for this one: this is a guided CTF, the student
 * follows the guidance on the page, and the design shows each objective with the
 * command beside it.
 *
 * Naming the command does not make the objective free. Validation is still event-based:
 * a student who is told to run nmap and does not run it has not completed anything,
 * and the validator never checks what was typed — it checks whether the victim recorded
 * the traffic that running it would produce. Being shown how to look is not the same as
 * having one's work marked for one.
 *
 * ── On {target} ──────────────────────────────────────────────────────────────────
 *
 * A command that needs the target address cannot be stored on the objective, because
 * there is one course and every student has a different target. The objective holds the
 * shape; the page substitutes the address the student's own lab has. That is also why
 * the command shown is always one that would work right now.
 */
class GuidedCtfSeeder extends Seeder
{
    public const SLUG = 'imnehnet-web-server-breach';

    /** Substituted with the session's own target address when a hint is shown. */
    public const TARGET_PLACEHOLDER = '{target}';

    public function run(): void
    {
        $course = Course::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'title' => 'IMNEHNET Web Server Breach',
                'difficulty' => 'Beginner',
                'summary' => 'Learn network discovery, service enumeration, web reconnaissance '
                    .'and exploitation against a purpose-built vulnerable web server.',
            ],
        );

        foreach ($this->objectives() as $definition) {
            Objective::query()->updateOrCreate(
                ['course_id' => $course->getKey(), 'sequence' => $definition['sequence']],
                $definition,
            );
        }
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function objectives(): array
    {
        return [
            [
                'sequence' => 1,
                'title' => 'Host Discovery',
                'description' => 'Identify whether your assigned target is active and reachable '
                    .'from your attacker environment.',
                'hint' => 'nmap -sn '.self::TARGET_PLACEHOLDER,
                'validation_type' => ValidationType::Event,
                // Any recorded service traffic proves reachability. A ping does not,
                // because it opens no socket and leaves no record on the target, so the
                // objective is worded around establishing a connection for that reason.
                'evidence_type' => EvidenceType::VictimConnection,
                'evidence_value' => null,
            ],
            [
                'sequence' => 2,
                'title' => 'Port & Service Reconnaissance',
                'description' => 'Discover the open ports and running services on your target.',
                'hint' => 'nmap -n --unprivileged -sT -sV --version-light -Pn -p 22,80 '.self::TARGET_PLACEHOLDER,
                'validation_type' => ValidationType::Event,
                // Two distinct ports. One open service is a finding; two means the
                // student looked rather than guessed the obvious one.
                'evidence_type' => EvidenceType::VictimPortScan,
                'evidence_value' => '2',
            ],
            [
                'sequence' => 3,
                'title' => 'Web Reconnaissance',
                'description' => 'Investigate the web service discovered on the target and identify '
                    .'useful information.',
                'hint' => 'curl http://'.self::TARGET_PLACEHOLDER.'/robots.txt',
                'validation_type' => ValidationType::Event,
                'evidence_type' => EvidenceType::VictimHttpRequest,
                'evidence_value' => '/robots.txt',
            ],
            [
                'sequence' => 4,
                'title' => 'Hidden Directory Discovery',
                'description' => 'Find hidden directories and files on the web server.',
                'hint' => 'gobuster dir -u http://'.self::TARGET_PLACEHOLDER
                    .' -w /usr/share/wordlists/dirb/common.txt',
                'validation_type' => ValidationType::Event,
                // The hidden directory is named here because the validator needs to know
                // what to look for. It reaches the browser only once this objective is
                // the active one; see Objective::toPublicArray.
                'evidence_type' => EvidenceType::VictimHttpRequest,
                'evidence_value' => '/n0te-4-admin/',
            ],
            [
                'sequence' => 5,
                'title' => 'Vulnerability Discovery',
                'description' => 'Analyse and identify vulnerabilities in the target service.',
                'hint' => 'curl "http://'.self::TARGET_PLACEHOLDER.'/admin?file=../n0te-4-admin/flag.html"',
                'validation_type' => ValidationType::Event,
                // The reports viewer takes a file parameter and nothing confines it.
                // Matching "/admin?file=.." rather than "/admin?file=" is the difference
                // between proving the student exploited the weakness and proving only
                // that they typed into a text box: the request target has to carry the
                // traversal itself, and the value after it is theirs to choose.
                'evidence_type' => EvidenceType::VictimHttpRequest,
                'evidence_value' => '/admin?file=..',
            ],
            [
                'sequence' => 6,
                'title' => 'Exploit & Capture Flag',
                'description' => 'Use the information gathered from the previous objectives to '
                    .'complete the compromise and retrieve the final flag.',
                'hint' => 'Copy the flag you just retrieved and submit it below.',
                'validation_type' => ValidationType::Flag,
                // No evidence type: this one is settled by comparison, not by an event.
                // The flag is read from the victim at submission time and is never held
                // in this database.
                'evidence_type' => null,
                'evidence_value' => null,
            ],
        ];
    }
}
