                @php
                    $events = \App\Services\GuidedCtf\ProvisioningTimeline::events((int) $session->id);
                    $steps = [
                        ['session', 'Creating lab session'],
                        ['victim', 'Creating victim container (LAB-ENV)'],
                        ['attacker', 'Creating attacker container (LAB-ATK)'],
                        ['network', 'Applying security policy'],
                        ['scenario', 'Configuring environment'],
                        ['ready', 'Finalizing setup'],
                    ];
                    $done = [];
                    $started = [];
                    foreach ($events as $event) {
                        $phase = $event['stage'] ?? '';
                        if (in_array($phase, ['session','victim','attacker','network','scenario','ready'], true)) {
                            $started[$phase] = true;
                            if (($event['level'] ?? '') === 'success') $done[$phase] = true;
                        }
                        if ($phase === 'queued') $done['session'] = true;
                    }
                    // Scenario has no separate success event; READY proves it completed.
                    if (isset($done['ready'])) $done['scenario'] = true;
                    // Worker started is proof that the session was created.
                    if (isset($started['session'])) $done['session'] = true;
                    $failed = $session->status === \App\Enums\LabSessionStatus::Failed;
                @endphp
                <div class="im-create" aria-label="Create Lab provisioning progress">
                    <nav class="im-create-breadcrumb"><a href="{{ url('/student/my-labs') }}">My Labs</a><span>›</span><strong>Create Lab</strong></nav>
                    <header class="im-create-heading"><span class="im-create-cube">{!! $svg('box') !!}</span><div><h1>Membuat Lab Environment</h1><p>Course: {{ $course?->title ?? 'Web Server Breach' }}</p><small>Satu lab terdiri dari dua container: LAB-ENV (victim) dan LAB-ATK (attacker).</small></div></header>
                    @if ($failed)<div class="im-create-failure" role="alert"><strong>Provisioning gagal</strong><p>{{ $session->last_error ?: 'Periksa log provisioning atau hubungi administrator.' }}</p></div>@endif
                    <ol class="im-create-steps">
                        @foreach ($steps as $i => $step)
                            @php $phase = $step[0]; $isDone = isset($done[$phase]); $isCurrent = !$failed && !$isDone && isset($started[$phase]); @endphp
                            <li class="im-create-step {{ $isDone ? 'is-done' : ($isCurrent ? 'is-current' : '') }}"><span class="im-create-number">{{ $i+1 }}</span><span class="im-create-step-name">{{ $step[1] }}</span><span class="im-create-state">{{ $isDone ? '✓ Completed' : ($isCurrent ? '◌ In progress...' : ($failed ? 'Not completed' : 'Pending')) }}</span></li>
                        @endforeach
                    </ol>
                    <div class="im-create-terminal-heading"><h2>Terminal Output</h2><span>Session #{{ $session->id }} · WIB · Auto refresh 5s</span></div>
                    <section class="im-create-terminal" role="log" aria-label="Actual provisioning events">
                        <div class="im-create-terminal-top"><span>creating-lab · {{ $course?->title ?? 'Web Server Breach' }}</span><span>{{ $session->status->value }}</span></div>
                        @forelse ($events as $event)
                            <div class="im-create-log"><span class="im-create-log-time">[{{ $event['time'] }}]</span><span class="{{ ($event['level'] ?? '') === 'error' ? 'is-error' : (($event['level'] ?? '') === 'success' ? 'is-success' : 'is-info') }}">[{{ strtoupper($event['level'] ?? 'info') }}]</span><span>{{ $event['message'] }}</span></div>
                        @empty
                            <p>Menunggu event provisioning dari backend...</p>
                        @endforelse
                    </section>
                    <div class="im-create-actions"><span>Proses provisioning dijalankan oleh queue worker. Status berasal dari event backend.</span></div>
                </div>
