@extends('marketer.layout')

@section('title', 'Regional Tours & Farmer Visits')

@section('topbar')
    {{ $marketer->f_name }} {{ $marketer->l_name }}<br>
    <span class="small">{{ $marketer->email }}</span>
@endsection

@section('nav')
    @include('marketer._nav', ['active' => 'tours'])
@endsection

@section('content')
    @php($refCode = $marketer->marketer_code ?: ('MKT-' . $marketer->id))
    <div class="card">
        <h1>Regional Tours &amp; Farmer Field Visits</h1>
        <p class="muted small" style="margin-top:0;">
            Track upcoming regional tours, check in farmers during field visits, and onboard them directly with your referral code <strong>{{ $refCode }}</strong>.
        </p>

        <form method="get" action="{{ route('marketer.regional-tours') }}" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:16px;">
            <select name="region" style="padding:8px 10px;border-radius:6px;border:1px solid #d1d5db;max-width:240px;">
                <option value="">All Regions</option>
                @foreach($regions as $r)
                    <option value="{{ $r }}" {{ $region === $r ? 'selected' : '' }}>{{ $r }}</option>
                @endforeach
            </select>
            <button type="submit" style="padding:8px 14px;font-size:13px;">Filter</button>
            @if(count($registrations) > 0)
                <button type="button" class="btn secondary" onclick="exportFieldCallSheet()" style="padding:8px 14px;font-size:13px;margin-left:auto;">
                    Download Field Call Sheet (CSV)
                </button>
            @endif
        </form>

        <h2 style="margin-top:8px;">Scheduled Tour Stops ({{ count($tours) }})</h2>
        @if(count($tours) === 0)
            <p class="muted small">No scheduled tours match this region yet.</p>
        @else
            <div style="overflow-x:auto;margin-bottom:20px;">
                <table>
                    <thead>
                        <tr>
                            <th>Tour</th>
                            <th>Region / Venue</th>
                            <th>Dates</th>
                            <th>Registered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($tours as $tour)
                            <tr>
                                <td><strong>{{ $tour->title }}</strong></td>
                                <td>{{ $tour->region }} @if($tour->venue)<br><span class="small muted">{{ $tour->venue }}</span>@endif</td>
                                <td class="small">{{ optional($tour->start_date)->format('M d, Y') }}</td>
                                <td>{{ $tour->registrations_count }} / {{ $tour->capacity }}</td>
                                <td><span class="badge">{{ ucfirst(str_replace('_', ' ', $tour->status)) }}</span></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        <h2>Farmer Registrations &amp; Region Visit Requests</h2>
        @if(count($registrations) === 0)
            <p class="muted small">No farmer registrations yet.</p>
        @else
            <div style="overflow-x:auto;">
                <table>
                    <thead>
                        <tr>
                            <th>Farmer / Cooperative</th>
                            <th>Contact</th>
                            <th>Region / Crops</th>
                            <th>Tour</th>
                            <th>Status</th>
                            <th>Field Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($registrations as $reg)
                            <tr>
                                <td>
                                    <strong>{{ $reg->farmer_name }}</strong>
                                    @if($reg->is_cooperative)
                                        <span class="badge">Co-op ({{ $reg->farmer_count }})</span>
                                    @endif
                                </td>
                                <td class="small">
                                    @php($cleanDigits = preg_replace('/\D+/', '', (string) $reg->phone))
                                    @php($waPhone = str_starts_with($cleanDigits, '0') && strlen($cleanDigits) === 10 ? '233' . substr($cleanDigits, 1) : $cleanDigits)
                                    <a href="tel:{{ $reg->phone }}" style="font-weight:600;text-decoration:none;">{{ $reg->phone }}</a>
                                    @if(!empty($waPhone))
                                        · <a href="https://wa.me/{{ $waPhone }}?text={{ rawurlencode('Hello ' . $reg->farmer_name . ', this is Aduanefie Regional Tour team regarding your visit request.') }}" target="_blank" rel="noopener" style="color:#128C7E;font-weight:600;text-decoration:none;">WhatsApp</a>
                                    @endif
                                    @if($reg->email)<br>{{ $reg->email }}@endif
                                </td>
                                <td class="small">{{ $reg->region }}@if($reg->community) · {{ $reg->community }}@endif<br><span class="muted">{{ $reg->primary_crops ?? '-' }}</span></td>
                                <td class="small">{{ $reg->tour?->title ?? 'Visit Request' }}</td>
                                <td><span class="badge">{{ ucfirst(str_replace('_', ' ', $reg->status)) }}</span></td>
                                <td>
                                    <form method="post" action="{{ route('marketer.regional-tours.status', $reg->id) }}" style="display:flex;gap:6px;flex-wrap:wrap;align-items:center;">
                                        @csrf
                                        <select name="status" style="padding:5px;font-size:12px;">
                                            @foreach(['registered', 'confirmed', 'visited', 'onboarded', 'no_show'] as $st)
                                                <option value="{{ $st }}" {{ $reg->status === $st ? 'selected' : '' }}>{{ ucfirst(str_replace('_', ' ', $st)) }}</option>
                                            @endforeach
                                        </select>
                                        <button type="submit" style="padding:5px 10px;font-size:12px;">Save</button>
                                        <a class="btn secondary" target="_blank" style="padding:5px 10px;font-size:12px;"
                                           href="https://aduanefie.com/store-registration?ref={{ urlencode($refCode) }}">Onboard</a>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @if(method_exists($registrations, 'links'))
                <div style="margin-top:16px;">{{ $registrations->links() }}</div>
            @endif
        @endif
    </div>

    <script>
        function exportFieldCallSheet() {
            var rows = [
                ['Farmer / Cooperative', 'Phone', 'Email', 'Region', 'Community', 'Primary Crops', 'Tour', 'Status']
            ];
            @foreach($registrations as $reg)
                rows.push([
                    @json($reg->farmer_name ?? ''),
                    @json($reg->phone ?? ''),
                    @json($reg->email ?? ''),
                    @json($reg->region ?? ''),
                    @json($reg->community ?? ''),
                    @json($reg->primary_crops ?? ''),
                    @json($reg->tour?->title ?? 'Visit Request'),
                    @json($reg->status ?? '')
                ]);
            @endforeach
            var csv = rows.map(function (r) {
                return r.map(function (cell) {
                    var s = String(cell || '').replace(/"/g, '""');
                    if (/^[=+\-@]/.test(s)) s = "'" + s;
                    return '"' + s + '"';
                }).join(',');
            }).join('\n');
            var blob = new Blob(["\uFEFF" + csv], { type: 'text/csv;charset=utf-8;' });
            var url = URL.createObjectURL(blob);
            var a = document.createElement('a');
            a.href = url;
            a.download = 'aduanefie-field-call-sheet.csv';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
@endsection
