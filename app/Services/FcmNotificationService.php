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

        // Sesuaikan dengan project Firebase Anda - format harus projects/{projectId}
        $projectId = env('FIREBASE_PROJECT_ID', 'satukurirwebpush');
        
        // Validasi project ID
        if (empty($projectId)) {
            throw new \Exception('FIREBASE_PROJECT_ID tidak ditemukan di .env');
        }
        
        // Format menjadi projects/{projectId}
        $this->projectId = strpos($projectId, 'projects/') === 0 ? $projectId : 'projects/' . $projectId;
        
        \Log::info('FCM Service Initialized', [
            'projectId' => $this->projectId,
            'env_value' => $projectId
        ]);
    }

    /**
     * Kirim notifikasi ke device token
     */
    public function sendToToken($deviceToken, $title, $body, $data)
    {
        try {
            $payload = [
                'message' => [
                    'token' => $deviceToken,
                    'notification' => [
                        'title' => $title,
                        'body'  => $body,
                    ],
                    ...(!empty($data) ? ['data' => $data] : []),
                ]
            ];

            $accessToken = $this->google->getAccessToken();
            
            // Debug logging
            \Log::info('FCM Request Debug', [
                'projectId' => $this->projectId,
                'token' => substr($deviceToken, 0, 50) . '...',
                'url' => 'https://fcm.googleapis.com/v1/' . $this->projectId . '/messages:send'
            ]);

            $response = Http::withToken($accessToken)
                ->post('https://fcm.googleapis.com/v1/' . $this->projectId . '/messages:send', $payload);

            \Log::info('FCM Response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body'   => $response->json(),
                'payload'   => $payload,
            ];

        } catch (\Exception $e) {
            \Log::error('FCM Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
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
        try {
            $url = "https://fcm.googleapis.com/v1/" . $this->projectId . "/messages:send";

            $accessToken = $this->google->getAccessToken();

            $payload = [
                "message" => [
                    "topic" => $topic,
                    "notification" => [
                        "title" => $title,
                        "body" => $body,
                    ],
                    ...(!empty($data) ? ['data' => $data] : []),
                ]
            ];

            \Log::info('FCM Topic Request', [
                'url' => $url,
                'topic' => $topic
            ]);

            $response = Http::withToken($accessToken)->post($url, $payload);

            \Log::info('FCM Topic Response', [
                'status' => $response->status(),
                'body' => $response->json()
            ]);

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'body'   => $response->json(),
                'payload'   => $payload,
            ];
        } catch (\Exception $e) {
            \Log::error('FCM Topic Error', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return [
                'success' => false,
                'status' => 500,
                'body' => ['error' => $e->getMessage()]
            ];
        }
    }
}
