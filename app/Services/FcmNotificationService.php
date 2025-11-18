<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

class FcmNotificationService
{
    protected $google;
    protected $projectId;

    public function __construct(GoogleAccessTokenService $google)
    {
        $this->google = $google;

        // Sesuaikan dengan project Firebase Anda
        $this->projectId = env('FIREBASE_PROJECT_ID');
    }

    /**
     * Kirim notifikasi ke device token
     */
    public function sendToToken($deviceToken, $title, $body, $data = [])
    {
        try {
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ]
                ]
            ];

            $accessToken = $this->google->getAccessToken();

            $response = Http::withToken($accessToken)
                ->post('https://fcm.googleapis.com/v1/projects/'.$this->projectId.'/messages:send', $payload);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body'   => $response->json(),
                'payload'   => $payload,
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }

    /**
     * Kirim notifikasi ke topic
     */
    public function sendToTopic($topic, $title, $body, $data = [])
    {
        $url = "https://fcm.googleapis.com/v1/projects/{$this->projectId}/messages:send";

        $accessToken = $this->google->getAccessToken();

        $payload = [
            "message" => [
                "topic" => $topic,
                "notification" => [
                    "title" => $title,
                    "body" => $body,
                ],
                "data" => $data,
            ]
        ];

        $response = Http::withToken($accessToken)->post($url, $payload);

        return $response->json();
    }
}
