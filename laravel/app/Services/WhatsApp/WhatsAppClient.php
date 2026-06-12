<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class WhatsAppClient
{
    private string $apiUrl;
    private string $phoneNumberId;
    private string $accessToken;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) config('whatsapp.api_url', 'https://graph.facebook.com/v22.0'), '/');
        $this->phoneNumberId = (string) config('whatsapp.phone_number_id');
        $this->accessToken = (string) config('whatsapp.access_token');
    }

    public function isConfigured(): bool
    {
        return $this->phoneNumberId !== '' && $this->accessToken !== '';
    }

    public function sendText(string $to, string $message): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp no configurado. Mensaje no enviado.', ['to' => $to]);
            return false;
        }

        $url = $this->apiUrl.'/'.$this->phoneNumberId.'/messages';

        $response = Http::withToken($this->accessToken)
            ->timeout(15)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizeNumber($to),
                'type' => 'text',
                'text' => ['body' => $message],
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp send failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        return true;
    }

    public function sendDocument(string $to, string $documentUrl, string $caption = ''): bool
    {
        if (! $this->isConfigured()) {
            Log::warning('WhatsApp no configurado. Documento no enviado.', ['to' => $to]);
            return false;
        }

        $url = $this->apiUrl.'/'.$this->phoneNumberId.'/messages';

        $response = Http::withToken($this->accessToken)
            ->timeout(30)
            ->post($url, [
                'messaging_product' => 'whatsapp',
                'to' => $this->normalizeNumber($to),
                'type' => 'document',
                'document' => [
                    'link' => $documentUrl,
                    'caption' => $caption ?: null,
                ],
            ]);

        if ($response->failed()) {
            Log::error('WhatsApp sendDocument failed', [
                'to' => $to,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return false;
        }

        return true;
    }

    private function normalizeNumber(string $number): string
    {
        $clean = preg_replace('/[^0-9]/', '', $number);
        if (strlen($clean) < 10) {
            throw new RuntimeException('Numero WhatsApp invalido: '.$number);
        }
        if (! str_starts_with($clean, '54')) {
            $clean = '54'.$clean;
        }
        return $clean;
    }
}
