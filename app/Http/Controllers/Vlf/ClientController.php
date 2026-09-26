<?php

namespace App\Http\Controllers\Vlf;

use App\Http\Controllers\Controller;
use App\Models\Vlf\Client;
use App\Models\Vlf\Matter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClientController extends Controller
{
    private const RULES = [
        'name' => 'required|string|max:255',
        'tin' => 'nullable|string|max:50',
        'type' => 'nullable|in:Company,Individual,Estate,Government,NGO',
        'contactName' => 'nullable|string|max:255',
        'contactEmail' => 'nullable|email|max:255',
        'contactPhone' => 'nullable|string|max:50',
        'address' => 'nullable|string|max:255',
        'notes' => 'nullable|string|max:5000',
    ];

    public function store(Request $request): JsonResponse
    {
        $client = Client::create($this->columns($request->validate(self::RULES)));

        return response()->json($client->load('matters')->toClient(), 201);
    }

    public function update(Request $request, Client $client): JsonResponse
    {
        $rules = array_map(fn ($rule) => 'sometimes|'.$rule, self::RULES);
        $client->update($this->columns($request->validate($rules + ['verified' => 'sometimes|boolean'])));

        return response()->json($client->load('matters')->toClient());
    }

    /**
     * Conflict check for intake: is the opposing party one of our clients, or has
     * the prospective client been on the other side of one of our matters?
     */
    public function conflict(Request $request): JsonResponse
    {
        $data = $request->validate([
            'client' => 'nullable|string|max:255',
            'opposing' => 'required|string|max:255',
        ]);

        $opposing = trim($data['opposing']);
        $prospective = trim($data['client'] ?? '');

        $opposingIsClient = Client::where('name', 'like', '%'.$opposing.'%')->get(['id', 'name']);
        $clientWasOpponent = $prospective === ''
            ? collect()
            : Matter::where('opposing_party', 'like', '%'.$prospective.'%')->get(['ref', 'title']);

        $matches = $opposingIsClient->map(fn ($c) => "{$c->name} is an existing client of the firm")
            ->merge($clientWasOpponent->map(fn ($m) => "{$prospective} is the opposing party in {$m->ref} ({$m->title})"))
            ->values();

        return response()->json([
            'conflict' => $matches->isNotEmpty(),
            'matches' => $matches,
            'existingClient' => $prospective === '' ? null : Client::where('name', $prospective)->first()?->load('matters')->toClient(),
        ]);
    }

    private function columns(array $data): array
    {
        $map = ['contactName' => 'contact_name', 'contactEmail' => 'contact_email', 'contactPhone' => 'contact_phone'];

        return collect($data)->mapWithKeys(fn ($v, $k) => [$map[$k] ?? $k => $v])->all();
    }
}
