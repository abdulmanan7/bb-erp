<?php

namespace App\Controllers\Api;

use App\Models\InvoiceItemModel;
use App\Models\InvoiceModel;
use CodeIgniter\HTTP\ResponseInterface;

class Invoices extends BaseApiController
{
    public function create(): ResponseInterface
    {
        $b = $this->body();
        $client = trim((string) ($b['client'] ?? ''));
        if ($client === '') {
            return $this->fail('Client name required.');
        }
        $date = $this->validDate($b['date'] ?? '');

        $m = new InvoiceModel();
        $items = new InvoiceItemModel();
        $id = $this->newId();

        $db = db_connect();
        $db->transStart();
        $m->insert([
            'id'         => $id,
            'no'         => $m->nextNo($date),
            'client'     => $client,
            'address'    => trim((string) ($b['address'] ?? '')),
            'inv_date'   => $date,
            'notes'      => trim((string) ($b['notes'] ?? '')),
            'created_by' => $this->user()['name'],
            'created_at' => $this->now(),
        ]);
        $items->replaceFor($id, (array) ($b['items'] ?? []));
        $db->transComplete();

        return $this->json(['invoice' => $m->map($m->find($id))]);
    }

    public function update($id = null): ResponseInterface
    {
        $m = new InvoiceModel();
        if (! $m->find($id)) {
            return $this->fail('Invoice not found.', 404);
        }
        $b = $this->body();
        $client = trim((string) ($b['client'] ?? ''));
        if ($client === '') {
            return $this->fail('Client name required.');
        }

        $db = db_connect();
        $db->transStart();
        $m->update($id, [
            'client'   => $client,
            'address'  => trim((string) ($b['address'] ?? '')),
            'inv_date' => $this->validDate($b['date'] ?? ''),
            'notes'    => trim((string) ($b['notes'] ?? '')),
        ]);
        (new InvoiceItemModel())->replaceFor($id, (array) ($b['items'] ?? []));
        $db->transComplete();

        return $this->json(['invoice' => $m->map($m->find($id))]);
    }

    public function delete($id = null): ResponseInterface
    {
        $m = new InvoiceModel();
        if (! $m->find($id)) {
            return $this->fail('Invoice not found.', 404);
        }
        (new InvoiceItemModel())->where('invoice_id', $id)->delete();
        $m->delete($id);
        return $this->json(['ok' => true]);
    }
}
