@extends('layouts.app')
@section('title', 'Marketing & CRM')

@section('content')
<div class="mb-6">
    <h1 class="text-2xl font-bold text-slate-900">Marketing, Campaigns, CRM & Patient Feedback</h1>
    <p class="text-sm text-slate-500">Lead management, health checkup promotions, follow-up reminders & support tickets.</p>
</div>

<div class="grid xl:grid-cols-3 gap-6">
    <div class="bg-white rounded-xl border border-slate-200 p-5 h-fit">
        <h2 class="text-base font-bold text-slate-900 mb-4">Add Patient Lead</h2>
        <form method="POST" action="{{ route('admin.crm.leads') }}" class="space-y-3">
            @csrf
            <select name="branch_id" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                @foreach($branches as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
            </select>
            <input type="text" name="name" placeholder="Lead Name *" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <input type="text" name="phone" placeholder="Phone *" required class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <select name="source" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
                <option>WhatsApp</option><option>Website</option><option>Health Camp</option><option>Doctor Referral</option>
            </select>
            <input type="text" name="campaign_name" placeholder="Campaign (e.g. Winter Full Body)" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <input type="text" name="interest" placeholder="Interested Package / Test" class="w-full px-3 py-2 text-sm border border-slate-200 rounded-lg">
            <button class="w-full py-2.5 bg-teal-600 hover:bg-teal-700 text-white text-sm font-semibold rounded-lg">Add Lead</button>
        </form>
    </div>

    <div class="xl:col-span-2 space-y-6">
        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-bold text-slate-900 mb-3">Active Leads & Campaigns</h2>
            @foreach($leads as $lead)
                <div class="p-3 rounded-lg bg-slate-50 border border-slate-100 mb-2 flex justify-between items-center text-xs">
                    <div>
                        <strong class="text-sm text-slate-900">{{ $lead->name }}</strong> · {{ $lead->phone }}
                        <div class="text-slate-500">{{ $lead->source }} · {{ $lead->campaign_name }} · Interest: {{ $lead->interest }}</div>
                    </div>
                    <span class="px-2.5 py-1 rounded-full bg-teal-50 text-teal-700 font-semibold">{{ $lead->status }}</span>
                </div>
            @endforeach
        </div>

        <div class="bg-white rounded-xl border border-slate-200 p-5">
            <h2 class="text-base font-bold text-slate-900 mb-3">Support Tickets & Patient Reviews</h2>
            @foreach($tickets as $tkt)
                <div class="p-3 rounded-lg border border-slate-100 mb-2 text-xs flex justify-between items-center">
                    <div>
                        <strong class="font-mono text-teal-700">{{ $tkt->ticket_no }}</strong> · <strong>{{ $tkt->subject }}</strong>
                        <div class="text-slate-500">{{ $tkt->description }}</div>
                    </div>
                    <div class="text-right">
                        <span class="px-2 py-0.5 rounded bg-emerald-50 text-emerald-700 font-semibold">{{ $tkt->status }}</span>
                        @if($tkt->rating)<div class="text-amber-500 font-bold mt-1">★ {{ $tkt->rating }}/5</div>@endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
@endsection

