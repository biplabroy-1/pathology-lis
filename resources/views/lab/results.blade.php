@extends('layouts.app')
@section('title', 'Result Entry & Verification')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Result Entry, Validation & Pathologist Approval</h1>
    <p class="text-sm text-slate-500">Auto-flagging for abnormal & critical values, previous result comparison & electronic signature.</p>
</div>

<div class="space-y-6">
    @foreach($orders as $ord)
        <div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
            <div class="px-5 py-3.5 bg-slate-50 border-b border-slate-200 flex flex-wrap items-center justify-between gap-3">
                <div>
                    <span class="font-mono text-xs font-bold text-teal-700 mr-2">#ORD-{{ $ord->id }}</span>
                    <span class="font-bold text-slate-900">{{ $ord->patient->name }}</span>
                    <span class="text-xs text-slate-500 ml-1">({{ $ord->patient->uhid }} · {{ $ord->patient->age }}Y/{{ $ord->patient->gender }})</span>
                    <div class="text-xs font-semibold text-slate-700 mt-0.5">{{ $ord->test->name }} · {{ $ord->department->name }}</div>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 text-xs font-semibold rounded-full {{ $ord->status === 'Approved' || $ord->status === 'Delivered' ? 'bg-emerald-50 text-emerald-700 border border-emerald-200' : 'bg-amber-50 text-amber-700 border border-amber-200' }}">
                        {{ $ord->status }}
                    </span>
                    @if($ord->report)
                        <a href="{{ route('lab.reports.show', $ord->report) }}" class="px-3 py-1 rounded-lg bg-teal-600 text-white text-xs font-semibold">Print Report</a>
                    @endif
                </div>
            </div>

            <form method="POST" action="{{ route('lab.results.save', $ord) }}" class="p-5">
                @csrf
                @if($ord->test->parameters->isNotEmpty())
                    <div class="overflow-x-auto mb-4">
                        <table class="w-full text-sm text-left">
                            <thead class="text-xs text-slate-500 uppercase border-b border-slate-200">
                                <tr>
                                    <th class="py-2 pr-4">Parameter</th>
                                    <th class="py-2 px-4">Observed Value</th>
                                    <th class="py-2 px-4">Unit</th>
                                    <th class="py-2 px-4">Reference Range</th>
                                    <th class="py-2 px-4">Previous</th>
                                    <th class="py-2 pl-4">Flag</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-100">
                                @foreach($ord->test->parameters as $param)
                                    @php
                                        $existing = $ord->results->firstWhere('test_parameter_id', $param->id) ?? $ord->results->firstWhere('parameter_name', $param->name);
                                        $isF = $ord->patient->gender === 'Female';
                                        $min = $isF ? ($param->ref_min_female ?? $param->ref_min_male) : $param->ref_min_male;
                                        $max = $isF ? ($param->ref_max_female ?? $param->ref_max_male) : $param->ref_max_male;
                                    @endphp
                                    <tr>
                                        <td class="py-2.5 pr-4 font-medium text-slate-900">{{ $param->name }}</td>
                                        <td class="py-2.5 px-4">
                                            <input type="text" name="results[{{ $param->id }}]" value="{{ $existing->result_value ?? '' }}"
                                                   class="w-32 px-2.5 py-1.5 text-sm font-semibold border border-slate-200 rounded-lg focus:ring-2 focus:ring-teal-500/30">
                                        </td>
                                        <td class="py-2.5 px-4 text-xs text-slate-500">{{ $param->unit }}</td>
                                        <td class="py-2.5 px-4 text-xs text-slate-600 tabular-nums">{{ $min }} - {{ $max }}</td>
                                        <td class="py-2.5 px-4 text-xs text-slate-400 tabular-nums">{{ $existing->previous_value ?? '-' }}</td>
                                        <td class="py-2.5 pl-4">
                                            @if($existing)
                                                <span class="px-2 py-0.5 text-[11px] font-bold rounded-full {{ str_contains($existing->flag, 'Critical') ? 'bg-rose-100 text-rose-700' : ($existing->flag === 'Normal' ? 'bg-emerald-50 text-emerald-700' : 'bg-amber-100 text-amber-800') }}">
                                                    {{ $existing->flag }}
                                                </span>
                                            @else
                                                <span class="text-xs text-slate-400">Pending</span>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="flex flex-wrap items-center justify-between gap-3 pt-2 border-t border-slate-100">
                        <div class="text-xs text-slate-500">
                            @if($ord->pathologist_signature)
                                Signed: <strong class="text-teal-700">{{ $ord->pathologist_signature }}</strong>
                            @endif
                        </div>
                        <div class="flex gap-2">
                            <button name="action_type" value="save" class="px-3.5 py-2 rounded-lg border border-slate-200 bg-white hover:bg-slate-50 text-xs font-semibold text-slate-700">Save Draft</button>
                            <button name="action_type" value="verify" class="px-3.5 py-2 rounded-lg bg-sky-600 hover:bg-sky-700 text-white text-xs font-semibold">Technician Verify</button>
                            <button name="action_type" value="approve" class="px-4 py-2 rounded-lg bg-teal-600 hover:bg-teal-700 text-white text-xs font-semibold">Pathologist Approve & Sign</button>
                        </div>
                    </div>
                @else
                    <p class="text-xs text-slate-500">Descriptive / imaging report template configured in Reports module.</p>
                @endif
            </form>
        </div>
    @endforeach
    {{ $orders->links() }}
</div>
@endsection

