<?php

namespace App\Services\BillingSheet;

use Firebase\JWT\JWT;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * Acceso a la hoja con la API de Google Sheets v4, autenticado como cuenta de
 * servicio (OAuth 2.0 JWT bearer, firmado con la llave del JSON de la
 * cuenta). La hoja debe estar compartida con el correo de la cuenta de
 * servicio como Editor, para poder marcar REGISTRADO y NOVEDAD SISTEMA.
 */
class GoogleBillingSheet implements BillingSheet
{
    private const API = 'https://sheets.googleapis.com/v4/spreadsheets/';

    private const SCOPE = 'https://www.googleapis.com/auth/spreadsheets';

    private ?string $title = null;

    public function id(): string
    {
        $id = config('services.billing_sheet.spreadsheet_id');

        if (! $id) {
            throw new BillingSheetException('BILLING_SHEET_ID no está configurado.');
        }

        return $id;
    }

    public function rows(): array
    {
        $response = $this->request()->get(self::API.$this->id().'/values/'.rawurlencode($this->quotedTitle().'!A1:O'), [
            'valueRenderOption' => 'UNFORMATTED_VALUE',
            'dateTimeRenderOption' => 'SERIAL_NUMBER',
        ]);
        $this->ensureSuccessful($response, 'leer la hoja');

        $rows = [];

        foreach ($response->json('values', []) as $index => $values) {
            $rows[$index + 1] = $values;
        }

        return $rows;
    }

    public function write(array $cells): void
    {
        if ($cells === []) {
            return;
        }

        $data = [];

        foreach ($cells as $cell => $value) {
            $data[] = ['range' => $this->quotedTitle().'!'.$cell, 'values' => [[$value]]];
        }

        $response = $this->request()->post(self::API.$this->id().'/values:batchUpdate', [
            'valueInputOption' => 'RAW',
            'data' => $data,
        ]);
        $this->ensureSuccessful($response, 'escribir en la hoja');
    }

    /** Nombre de la pestaña cuyo gid está configurado (por defecto la primera, gid 0). */
    private function quotedTitle(): string
    {
        if ($this->title === null) {
            $response = $this->request()->get(self::API.$this->id(), ['fields' => 'sheets.properties(sheetId,title)']);
            $this->ensureSuccessful($response, 'leer las pestañas de la hoja');

            $gid = (int) config('services.billing_sheet.gid', 0);
            $sheet = collect($response->json('sheets', []))->firstWhere('properties.sheetId', $gid);

            if (! $sheet) {
                throw new BillingSheetException("La hoja no tiene una pestaña con gid {$gid}.");
            }

            $this->title = $sheet['properties']['title'];
        }

        return "'".str_replace("'", "''", $this->title)."'";
    }

    private function request()
    {
        return Http::withToken($this->accessToken())->acceptJson()->timeout(30)->retry(2, 500, throw: false);
    }

    private function accessToken(): string
    {
        $credentials = $this->credentials();

        return Cache::remember('billing-sheet.google-token.'.md5($credentials['client_email']), 3000, function () use ($credentials) {
            $now = time();
            $assertion = JWT::encode([
                'iss' => $credentials['client_email'],
                'scope' => self::SCOPE,
                'aud' => $credentials['token_uri'],
                'iat' => $now,
                'exp' => $now + 3600,
            ], $credentials['private_key'], 'RS256');

            $response = Http::asForm()->timeout(15)->post($credentials['token_uri'], [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => $assertion,
            ]);
            $this->ensureSuccessful($response, 'autenticar la cuenta de servicio');

            return $response->json('access_token');
        });
    }

    /** @return array{client_email: string, private_key: string, token_uri: string} */
    private function credentials(): array
    {
        $path = config('services.billing_sheet.credentials');

        if (! $path || ! is_readable($path)) {
            throw new BillingSheetException('No se encuentra el JSON de la cuenta de servicio de Google (GOOGLE_SHEETS_CREDENTIALS).');
        }

        $json = json_decode((string) file_get_contents($path), true);

        if (! is_array($json) || empty($json['client_email']) || empty($json['private_key'])) {
            throw new BillingSheetException('El JSON de la cuenta de servicio de Google no es válido.');
        }

        return [
            'client_email' => $json['client_email'],
            'private_key' => $json['private_key'],
            'token_uri' => $json['token_uri'] ?? 'https://oauth2.googleapis.com/token',
        ];
    }

    private function ensureSuccessful(Response $response, string $action): void
    {
        if ($response->successful()) {
            return;
        }

        $reason = match ($response->status()) {
            403 => 'sin permiso: comparte la hoja con el correo de la cuenta de servicio como Editor',
            404 => 'no existe: revisa BILLING_SHEET_ID',
            default => 'HTTP '.$response->status().' '.($response->json('error.message') ?? $response->json('error_description') ?? ''),
        };

        throw new BillingSheetException("No se pudo {$action} ({$reason}).");
    }
}
