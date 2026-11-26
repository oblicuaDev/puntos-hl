<?php

namespace Tests\Feature;

use App\Services\BillingSheet\BillingSheetException;
use App\Services\BillingSheet\GoogleBillingSheet;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GoogleBillingSheetTest extends TestCase
{
    private string $credentials;

    protected function setUp(): void
    {
        parent::setUp();

        $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($key, $privateKey);

        $this->credentials = tempnam(sys_get_temp_dir(), 'gsa');
        file_put_contents($this->credentials, json_encode([
            'client_email' => 'carga@proyecto.iam.gserviceaccount.com',
            'private_key' => $privateKey,
            'token_uri' => 'https://oauth2.googleapis.com/token',
        ]));

        config([
            'services.billing_sheet.credentials' => $this->credentials,
            'services.billing_sheet.spreadsheet_id' => 'abc123',
            'services.billing_sheet.gid' => 0,
        ]);
    }

    protected function tearDown(): void
    {
        @unlink($this->credentials);
        parent::tearDown();
    }

    public function test_reads_rows_and_writes_cells_on_the_configured_tab(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-123', 'expires_in' => 3599]),
            'sheets.googleapis.com/v4/spreadsheets/abc123?*' => Http::response(['sheets' => [
                ['properties' => ['sheetId' => 7, 'title' => 'Otra']],
                ['properties' => ['sheetId' => 0, 'title' => 'Facturación de H&L']],
            ]]),
            'sheets.googleapis.com/v4/spreadsheets/abc123/values/*' => Http::response(['values' => [
                ['CEDULA / NIT', 'NOMBRE'],
                [52287290, 'REYES SANDRA'],
            ]]),
            'sheets.googleapis.com/v4/spreadsheets/abc123/values:batchUpdate' => Http::response([]),
        ]);

        $sheet = new GoogleBillingSheet;

        $this->assertSame([1 => ['CEDULA / NIT', 'NOMBRE'], 2 => [52287290, 'REYES SANDRA']], $sheet->rows());
        $sheet->write(['M2' => 'X', 'O2' => 'Registrado']);

        Http::assertSent(fn (Request $request) => str_contains($request->url(), '/values/'.rawurlencode("'Facturación de H&L'!A1:O"))
            && $request->hasHeader('Authorization', 'Bearer token-123')
            && $request['valueRenderOption'] === 'UNFORMATTED_VALUE');

        Http::assertSent(fn (Request $request) => str_ends_with($request->url(), 'values:batchUpdate')
            && $request['valueInputOption'] === 'RAW'
            && $request['data'] === [
                ['range' => "'Facturación de H&L'!M2", 'values' => [['X']]],
                ['range' => "'Facturación de H&L'!O2", 'values' => [['Registrado']]],
            ]);

        Http::assertSent(fn (Request $request) => $request->url() === 'https://oauth2.googleapis.com/token'
            && $request['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer');
    }

    public function test_explains_a_missing_share_permission(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'token-123']),
            'sheets.googleapis.com/*' => Http::response(['error' => ['message' => 'The caller does not have permission']], 403),
        ]);

        $this->expectException(BillingSheetException::class);
        $this->expectExceptionMessage('comparte la hoja con el correo de la cuenta de servicio como Editor');

        (new GoogleBillingSheet)->rows();
    }
}
