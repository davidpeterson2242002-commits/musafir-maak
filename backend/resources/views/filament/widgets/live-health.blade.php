@php
    $colors = ['green' => '#059669', 'amber' => '#d97706', 'red' => '#dc2626'];
    $labels = ['green' => 'السيرفر مرتاح', 'amber' => 'ضغط متوسط — راقب', 'red' => 'ضغط عالي — قريب من الحد'];
    $c = $colors[$m['status']] ?? '#6b7280';
    $loadPct = $m['load'] === null ? null : (int) round($m['load'] * 100);
    $card = 'border:1px solid rgba(127,127,127,.22);border-radius:12px;padding:14px 16px;';
    $big = 'font-size:26px;font-weight:700;line-height:1.2;';
    $small = 'font-size:12.5px;opacity:.7;';
    $hist = collect($m['history']);
    $maxReq = max(1, (int) $hist->max('requests'));
    $w = 600; $h = 70; $n = max(1, $hist->count() - 1);
    $points = $hist->values()->map(fn ($r, $i) => round($i * $w / $n, 1).','.round($h - ($r['requests'] / $maxReq) * ($h - 4) - 2, 1))->implode(' ');
    $tickAge = $m['last_tick'] ? now()->diffInSeconds(\Illuminate\Support\Carbon::parse($m['last_tick'])) : null;
@endphp

<x-filament-widgets::widget>
  <div wire:poll.5s>
    <x-filament::section>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:14px;">
            <span style="width:12px;height:12px;border-radius:50%;background:{{ $c }};box-shadow:0 0 0 4px {{ $c }}33;"></span>
            <span style="font-weight:700;font-size:16px;color:{{ $c }}">{{ $labels[$m['status']] ?? '' }}</span>
            <span style="{{ $small }}margin-inline-start:auto;">يتحدّث كل 5 ثواني · {{ $m['at'] }}</span>
        </div>

        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(170px,1fr));gap:12px;">
            <div style="{{ $card }}">
                <div style="{{ $small }}">مستخدمين هسي (آخر دقيقتين)</div>
                <div style="{{ $big }}">{{ number_format($m['online']) }}</div>
                <div style="{{ $small }}">آخر 5 دقايق {{ $m['online_5m'] }} · اليوم {{ $m['online_today'] }} · أعلى 24س {{ $m['peak_online_24h'] }}</div>
            </div>

            <div style="{{ $card }}">
                <div style="{{ $small }}">الحمل على السيرفر</div>
                @if ($loadPct === null)
                    <div style="{{ $big }}">—</div>
                    <div style="{{ $small }}">السعة لسه ما اتقاست (الإعدادات ← السيرفر والسعة)</div>
                @else
                    <div style="{{ $big }}color:{{ $c }}">{{ $loadPct }}%</div>
                    <div style="height:8px;border-radius:99px;background:rgba(127,127,127,.18);overflow:hidden;margin:6px 0;">
                        <div style="height:100%;width:{{ min(100, $loadPct) }}%;background:{{ $c }};"></div>
                    </div>
                    <div style="{{ $small }}">{{ $m['rps'] }} من {{ $m['capacity_rps'] }} طلب/ثانية</div>
                @endif
            </div>

            <div style="{{ $card }}">
                <div style="{{ $small }}">كم مستخدم في نفس الوقت لحدي ما يتعب السيرفر</div>
                <div style="{{ $big }}">{{ $m['capacity_users'] !== null ? '≈ '.number_format($m['capacity_users']) : '—' }}</div>
                <div style="{{ $small }}">
                    @if ($m['capacity_users'] !== null && $m['capacity_users'] > 0)
                        فاضل ≈ {{ number_format(max(0, $m['capacity_users'] - $m['online'])) }} · المستخدم الواحد ≈ {{ $m['per_user_rps'] }} طلب/ث
                    @else
                        يحتاج اختبار الضغط
                    @endif
                </div>
            </div>

            <div style="{{ $card }}">
                <div style="{{ $small }}">سرعة الرد (آخر دقيقة)</div>
                <div style="{{ $big }}">{{ $m['avg_ms'] }} <span style="font-size:14px;font-weight:500">ms</span></div>
                <div style="{{ $small }}">أبطأ طلب {{ $m['max_ms'] }}ms · بطيئة (&gt;1ث) {{ $m['slow_last_minute'] }}</div>
            </div>

            <div style="{{ $card }}">
                <div style="{{ $small }}">طلبات آخر دقيقة</div>
                <div style="{{ $big }}">{{ number_format($m['requests_last_minute']) }}</div>
                <div style="{{ $small }}color:{{ $m['errors_last_minute'] ? '#dc2626' : 'inherit' }}">
                    أخطاء سيرفر {{ $m['errors_last_minute'] }} ({{ round($m['error_rate'] * 100, 1) }}%)
                </div>
            </div>

            <div style="{{ $card }}">
                <div style="{{ $small }}">الخلفية (الجدولة والإشعارات)</div>
                <div style="font-size:15px;font-weight:600;line-height:1.9;">
                    <div style="color:{{ $tickAge === null || $tickAge > 180 ? '#dc2626' : 'inherit' }}">
                        الجدولة: {{ $tickAge === null ? 'ما اشتغلت' : ($tickAge < 120 ? 'شغالة' : 'متوقفة منذ '.intdiv($tickAge, 60).' د') }}
                    </div>
                    <div>في الانتظار: {{ $m['queue_waiting'] }} · فشلت (24س): {{ $m['queue_failed_24h'] }}</div>
                    <div style="color:{{ $m['notifications_failed_1h'] ? '#d97706' : 'inherit' }}">إشعارات فشلت (ساعة): {{ $m['notifications_failed_1h'] }}</div>
                </div>
            </div>
        </div>

        @if ($hist->count() > 1)
            <div style="margin-top:16px;">
                <div style="{{ $small }}margin-bottom:6px;">الطلبات في الدقيقة — آخر ساعة (أعلى {{ $maxReq }})</div>
                <svg viewBox="0 0 {{ $w }} {{ $h }}" preserveAspectRatio="none" style="width:100%;height:70px;display:block;">
                    <polyline points="{{ $points }}" fill="none" stroke="{{ $c }}" stroke-width="2" vector-effect="non-scaling-stroke" />
                </svg>
                <div style="display:flex;justify-content:space-between;direction:ltr;{{ $small }}">
                    <span>{{ $hist->first()['minute'] }}</span><span>{{ $hist->last()['minute'] }}</span>
                </div>
            </div>
        @endif
    </x-filament::section>
  </div>
</x-filament-widgets::widget>
