@extends('layouts.app')
@section('title', '11 Laboratory Departments')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Laboratory Department Dashboards</h1>
    <p class="text-sm text-slate-500">Department-specific work queues across all 11 diagnostic divisions.</p>
</div>

<div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-6">
    @foreach($departments as $d)
        @php $isAct = $activeDept && $activeDept->id === $d->id; @endphp
        <a href="{{ route('lab.departments', ['dept' => $d->slug]) }}"
           class="p-3.5 rounded-xl border transition {{ $isAct ? 'bg-teal-600 text-white border-teal-600 shadow-sm' : 'bg-white text-slate-800 border-slate-200 hover:border-teal-300' }}">
            <div class="text-xs font-mono opacity-75">{{ $d->code }}</div>
            <div class="font-bold text-sm mt-0.5 truncate">{{ $d->name }}</div>
            <div class="mt-2 flex items-center justify-between text-[11px]">
                <span>{{ $d->pending_count }} Pending</span>
                <span>{{ $d->completed_count }} Done</span>
            </div>
        </a>
    @endforeach
</div>

@if($activeDept)
<div class="bg-white rounded-xl border border-slate-200 overflow-hidden">
    <div class="px-5 py-4 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center justify-between gap-2">
        <div>
            <h2 class="text-base font-bold text-slate-900">{{ $activeDept->name }} Work Queue</h2>
            <p class="text-xs text-slate-500">{{ $activeDept->description }} · Head: {{ $activeDept->head_name }} · Standard TAT: {{ $activeDept->turn_around_hours }}h</p>
        </div>
        <a href="{{ route('lab.worklist', ['department_id' => $activeDept->id]) }}" class="px-3 py-1.5 rounded-lg bg-teal-50 text-teal-700 text-xs font-semibold">Open in Worklist →</a>
    </div>
    <div class="overflow-x-auto">
        <table class="w-full text-sm text-left">
            <thead class="bg-slate-50 text-xs font-semibold text-slate-500 uppercase">
                <tr>
                    <th class="px-4 py-3">Sample</th>
                    <th class="px-4 py-3">Patient</th>
                    <th class="px-4 py-3">Investigation</th>
                    <th class="px-4 py-3">Technician</th>
                    <th class="px-4 py-3">Priority</th>
                    <th class="px-4 py-3">Status</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse($orders as $ord)
                    <tr>
                        <td class="px-4 py-3 font-mono text-xs text-teal-700">{{ $ord->sample->sample_id_code }}</td>
                        <td class="px-4 py-3 font-semibold text-slate-900">{{ $ord->patient->name }}</td>
                        <td class="px-4 py-3 text-slate-700">{{ $ord->test->name }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ $ord->technician->name ?? 'Unassigned' }}</td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-slate-100">{{ $ord->priority }}</span></td>
                        <td class="px-4 py-3"><span class="px-2 py-0.5 text-[11px] font-semibold rounded-full bg-teal-50 text-teal-700">{{ $ord->status }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-xs text-slate-400">No active orders in {{ $activeDept->name }} queue.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection

