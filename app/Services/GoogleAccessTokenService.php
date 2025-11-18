<?php

namespace App\Services;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Illuminate\Support\Facades\Cache;

class GoogleAccessTokenService
{
    protected $scopes = [
        'https://www.googleapis.com/auth/firebase.messaging',
    ];

    public function getAccessToken()
    {
        // Jika token masih valid di cache → langsung pakai
        if (Cache::has('google_access_token')) {
            return Cache::get('google_access_token');
        }

        // Load service account JSON
        $path = storage_path('app/google/service-account.json');

        $credentials = new ServiceAccountCredentials($this->scopes, $path);
        $token = $credentials->fetchAuthToken();

        // Simpan token ke cache selama 55 menit (token berlaku 60 menit)
        Cache::put('google_access_token', $token['access_token'], now()->addMinutes(55));

        return $token['access_token'];
    }
}