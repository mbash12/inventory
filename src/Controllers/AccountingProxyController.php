<?php

namespace Src\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AccountingProxyController extends Controller
{
    private $accountingApiUrl;
    private $bearerToken;

    public function __construct()
    {
        $this->accountingApiUrl = rtrim(config('app.accounting_api_url', env('ACCOUNTING_API_URL', 'http://localhost:8001/api')), '/');
        $this->bearerToken = env('EPROC_INTEGRATION_BEARER');
    }

    public function getUnits(Request $request)
    {
        $companyId = $request->query('company_id');

        if (empty($companyId)) {
            return response()->json([
                'code' => 400,
                'message' => 'company_id is required'
            ], 400);
        }

        Log::info('Getting units from accounting API', [
            'url' => $this->accountingApiUrl . '/master/unit',
            'company_id' => $companyId,
            'bearer_token_set' => !empty($this->bearerToken),
            'bearer_token_length' => strlen($this->bearerToken ?? '')
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
            ])->get($this->accountingApiUrl . '/master/unit', [
                'company_id' => $companyId
            ]);

            Log::info('Accounting API response', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response($response->body(), $response->status())->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error connecting to accounting service', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 500,
                'message' => 'Error connecting to accounting service: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getTaxes(Request $request)
    {
        $companyId = $request->query('company_id');

        if (empty($companyId)) {
            return response()->json([
                'code' => 400,
                'message' => 'company_id is required'
            ], 400);
        }

        Log::info('Getting taxes from accounting API', [
            'url' => $this->accountingApiUrl . '/master/taxes',
            'company_id' => $companyId,
            'bearer_token_set' => !empty($this->bearerToken),
            'bearer_token_length' => strlen($this->bearerToken ?? '')
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
            ])->get($this->accountingApiUrl . '/master/taxes', [
                'company_id' => $companyId
            ]);

            Log::info('Accounting API response for taxes', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response($response->body(), $response->status())->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error connecting to accounting service for taxes', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 500,
                'message' => 'Error connecting to accounting service: ' . $e->getMessage()
            ], 500);
        }
    }

    public function getCustomers(Request $request)
    {
        $companyId = $request->query('company_id');

        if (empty($companyId)) {
            return response()->json([
                'code' => 400,
                'message' => 'company_id is required'
            ], 400);
        }

        Log::info('Getting customers from accounting API', [
            'url' => $this->accountingApiUrl . '/master/customers',
            'company_id' => $companyId,
            'bearer_token_set' => !empty($this->bearerToken),
            'bearer_token_length' => strlen($this->bearerToken ?? '')
        ]);

        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . $this->bearerToken,
                'Accept' => 'application/json',
            ])->get($this->accountingApiUrl . '/master/customers', [
                'company_id' => $companyId,
                'search' => $request->query('search')
            ]);

            Log::info('Accounting API response for customers', [
                'status' => $response->status(),
                'body' => $response->body()
            ]);

            return response($response->body(), $response->status())->header('Content-Type', 'application/json');
        } catch (\Exception $e) {
            Log::error('Error connecting to accounting service for customers', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'code' => 500,
                'message' => 'Error connecting to accounting service: ' . $e->getMessage()
            ], 500);
        }
    }
}