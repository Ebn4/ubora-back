<?php
namespace App;

use App\Services\MailService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MailServiceImpl implements \App\Services\MailService
{
    public function sendMail(array $payload): bool
    {
        try {
            $response = Http::timeout(5)
                ->withHeader('Content-Type', 'application/json')
                ->post(config('services.mail_api.url'), $payload);

            if ($response->successful()) {
                return true;
            }

            Log::error('Échec de l’envoi d’e-mail via API', [
                'status' => $response->status(),
                'body' => $response->body(),
                'payload' => $payload,
            ]);

            return false;
        } catch (\Exception $e) {
            Log::error('Exception lors de l’envoi d’e-mail', [
                'message' => $e->getMessage(),
                'payload' => $payload,
            ]);
            return false;
        }
    }
}