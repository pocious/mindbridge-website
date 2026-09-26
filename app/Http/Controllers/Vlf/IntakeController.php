<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\Vlf\Client;
use App\Models\Vlf\Matter;
use App\Support\VlfNotifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * New matter intake: finds or creates the client, then opens the matter with the
 * next free KSC-{year}-{number} reference and notifies the assigned team.
 */
class IntakeController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clientId' => 'nullable|integer|exists:vlf_clients,id',
            'clientName' => 'required_without:clientId|nullable|string|max:255',
            'clientTin' => 'nullable|string|max:50',
            'clientType' => 'nullable|in:Company,Individual,Estate,Government,NGO',
            'contactName' => 'nullable|string|max:255',
            'contactEmail' => 'nullable|email|max:255',
            'opposingParty' => 'required|string|max:255',
            'description' => 'required|string|max:1000',
            'practiceArea' => 'required|string|max:100',
            'court' => 'required|string|max:255',
            'instructionDate' => 'required|date',
            'advocate' => 'required|string|max:100',
            'supervisor' => 'required|string|max:100',
            'feeArrangement' => 'required|string|max:255',
            'openedBy' => 'nullable|string|max:100',
        ]);

        $matter = DB::transaction(function () use ($data) {
            $client = isset($data['clientId'])
                ? Client::findOrFail($data['clientId'])
                : Client::firstOrCreate(['name' => trim($data['clientName'])], [
                    'tin' => $data['clientTin'] ?? null,
                    'type' => $data['clientType'] ?? 'Company',
                    'contact_name' => $data['contactName'] ?? null,
                    'contact_email' => $data['contactEmail'] ?? null,
                ]);

            $year = now()->year;
            $last = Matter::where('ref', 'like', "KSC-{$year}-%")->lockForUpdate()->pluck('ref')
                ->map(fn ($ref) => (int) substr($ref, -4))->max() ?? 0;

            return Matter::create([
                'ref' => sprintf('KSC-%d-%04d', $year, $last + 1),
                'client_id' => $client->id,
                'title' => $client->name.' v. '.$data['opposingParty'],
                'opposing_party' => $data['opposingParty'],
                'practice_area' => $data['practiceArea'],
                'description' => $data['description'],
                'court' => $data['court'],
                'advocate' => $data['advocate'],
                'supervisor' => $data['supervisor'],
                'fee_arrangement' => $data['feeArrangement'],
                'instruction_date' => $data['instructionDate'],
                'stage' => 'Intake',
                'status_label' => 'New instruction',
                'status_level' => 'ok',
            ]);
        });

        $text = "New matter {$matter->ref} opened — {$matter->title}. You are responsible advocate.";
        VlfNotifier::notify($matter->advocate, 'task', $text, ['matter' => $matter->ref], 'New matter assigned');
        if ($matter->supervisor !== $matter->advocate) {
            VlfNotifier::notify($matter->supervisor, 'info', "New matter {$matter->ref} opened — {$matter->title}. You are supervising partner.", ['matter' => $matter->ref], 'New matter opened');
        }

        return response()->json($matter->load('client')->toClient(), 201);
    }
}
