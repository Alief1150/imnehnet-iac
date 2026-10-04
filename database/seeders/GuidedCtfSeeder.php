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
 * The wording follows the specification closely, including the part that is easy to
 * get wrong: the hints suggest a category of approach and never name a command.
 * "Try performing basic network reconnaissance" is a nudge. "Run ping" is a
 * tutorial, and a student who follows it learns nothing they could not do against
 * any host.
 *
 * Seeding is idempotent and updates in place. Objectives are matched on sequence,
 * so editing a description and re-seeding does not create a second row and orphan
 * the progress recorded against the first.
 */
class GuidedCtfSeeder extends Seeder
{
    public const SLUG = 'imnehnet-web-server-breach';

    public function run(): void
    {
        $course = Course::query()->updateOrCreate(
            ['slug' => self::SLUG],
            [
                'title' => 'IMNEHNET Web Server Breach',
                'difficulty' => 'Beginner',
                'summary' => 'Learn network discovery, enumeration, web reconnaissance and exploitation '
                    .'against a purpose-built vulnerable web server.',
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
                'hint' => 'Try performing basic network reconnaissance.',
                'validation_type' => ValidationType::Event,
                // Any completed connection proves reachability. A ping does not,
                // because it opens no socket and leaves no record on the target, so
                // the wording above says "reachable" rather than "answers ping".
                'evidence_type' => EvidenceType::VictimConnection,
                'evidence_value' => null,
            ],
            [
                'sequence' => 2,
                'title' => 'Port & Service Reconnaissance',
                'description' => 'Identify the network services exposed by the target system.',
                'hint' => 'A service is only worth investigating once you know it is there.',
                'validation_type' => ValidationType::Event,
                // Two distinct ports. One open service is a finding; two means the
                // student looked, rather than guessed the obvious one.
                'evidence_type' => EvidenceType::VictimPortScan,
                'evidence_value' => '2',
            ],
            [
                'sequence' => 3,
                'title' => 'Web Reconnaissance',
                'description' => 'Investigate the web service discovered on the target and identify '
                    .'useful information.',
                'hint' => 'Web servers often volunteer information they were not asked for.',
                'validation_type' => ValidationType::Event,
                'evidence_type' => EvidenceType::VictimHttpRequest,
                'evidence_value' => '/robots.txt',
            ],
            [
                'sequence' => 4,
                'title' => 'Hidden Directory Discovery',
                'description' => 'Discover a hidden resource exposed by the target web application.',
                'hint' => 'Whatever you just read may not be the whole of it.',
                'validation_type' => ValidationType::Event,
                // The hidden directory is named here because the validator needs to
                // know what to look for. It is not sent to the browser until this
                // objective is the active one; see ObjectiveProgress::reveals().
                'evidence_type' => EvidenceType::VictimHttpRequest,
                'evidence_value' => '/n0te-4-admin/',
            ],
            [
                'sequence' => 5,
                'title' => 'Vulnerability Discovery',
                'description' => 'Analyse the discovered web functionality and identify the weakness '
                    .'that can be exploited.',
                'hint' => 'A file that is read for someone is often also readable for anyone else.',
                'validation_type' => ValidationType::Event,
                // The vulnerable endpoint takes a file parameter. Matching the prefix
                // rather than the whole target means the student chooses the value
                // and is not scored on guessing ours.
                'evidence_type' => EvidenceType::VictimHttpRequest,
                'evidence_value' => '/admin?file=',
            ],
            [
                'sequence' => 6,
                'title' => 'Exploit & Capture Flag',
                'description' => 'Use the information gathered from the previous objectives to '
                    .'complete the compromise and retrieve the final flag.',
                'hint' => 'You already know where it is kept. The question is whether you can read it.',
                'validation_type' => ValidationType::Flag,
                // No evidence type: this one is settled by comparison, not by an event.
                // The flag is read from the victim at submission time and is never
                // held in this database.
                'evidence_type' => null,
                'evidence_value' => null,
            ],
        ];
    }
}