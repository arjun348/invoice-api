<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);

        $clients = Client::query()
            ->where('company_id', $request->user()->company_id)
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%");
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return ClientResource::collection($clients);
    }

    public function store(ClientRequest $request): ClientResource
    {
        $client = Client::create([
            ...$request->validated(),
            'company_id' => $request->user()->company_id,
        ]);

        return new ClientResource($client);
    }

    public function show(Request $request, int $client): ClientResource
    {
        return new ClientResource($this->findClient($request, $client));
    }

    public function update(ClientRequest $request, int $client): ClientResource
    {
        $client = $this->findClient($request, $client);
        $client->update($request->validated());

        return new ClientResource($client->fresh());
    }

    public function destroy(Request $request, int $client): Response|JsonResponse
    {
        $client = $this->findClient($request, $client);

        if ($client->invoices()->exists()) {
            return response()->json([
                'message' => 'Cannot delete a client that has invoices.',
            ], 422);
        }

        $client->delete();

        return response()->noContent();
    }

    private function findClient(Request $request, int $id): Client
    {
        return Client::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);
    }
}