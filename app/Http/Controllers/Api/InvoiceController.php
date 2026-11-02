<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InvoiceRequest;
use App\Http\Resources\InvoiceResource;
use App\Models\Client;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class InvoiceController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', 'in:draft,sent,paid'],
        ]);

        $invoices = Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->with('client')
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');

                $query->where(function ($query) use ($search) {
                    $query->where('number', 'like', "%{$search}%")
                        ->orWhereHas('client', fn ($clientQuery) => $clientQuery->where('name', 'like', "%{$search}%"));
                });
            })
            ->latest('id')
            ->paginate(10)
            ->withQueryString();

        return InvoiceResource::collection($invoices);
    }

    public function store(InvoiceRequest $request): InvoiceResource
    {
        $data = $request->validated();
        $this->findClient($request, $data['client_id']);

        $invoice = Invoice::create([
            ...$data,
            'company_id' => $request->user()->company_id,
        ]);

        return new InvoiceResource($invoice->load('client'));
    }

    public function show(Request $request, int $invoice): InvoiceResource
    {
        return new InvoiceResource($this->findInvoice($request, $invoice)->load('client'));
    }

    public function update(InvoiceRequest $request, int $invoice): InvoiceResource
    {
        $invoice = $this->findInvoice($request, $invoice);
        $data = $request->validated();
        $this->findClient($request, $data['client_id']);

        $invoice->update($data);

        return new InvoiceResource($invoice->fresh()->load('client'));
    }

    public function destroy(Request $request, int $invoice): Response
    {
        $this->findInvoice($request, $invoice)->delete();

        return response()->noContent();
    }

    private function findInvoice(Request $request, int $id): Invoice
    {
        return Invoice::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);
    }

    private function findClient(Request $request, int $id): Client
    {
        return Client::query()
            ->where('company_id', $request->user()->company_id)
            ->findOrFail($id);
    }
}