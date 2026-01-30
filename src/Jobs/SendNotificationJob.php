<?php

namespace Src\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Src\Models\Notification;
use Src\Models\NotifToken;
use Src\Models\User;

class SendNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 3;
    public $backoff = [30, 60, 120];

    private string $title;
    private string $content;
    private string $position;
    private ?string $payload;
    private string $type;
    private ?int $excludeUserId;

    public function __construct(
        string $title,
        string $content,
        string $position,
        ?string $payload,
        string $type,
        ?int $excludeUserId = null
    ) {
        $this->title = $title;
        $this->content = $content;
        $this->position = $position;
        $this->payload = $payload;
        $this->type = $type;
        $this->excludeUserId = $excludeUserId;
        $this->onQueue(env('NOTIFICATION_QUEUE', 'default'));
    }

    public function handle(): void
    {
        $SERVER_API_KEY = env('FCM_SERVER_KEY');
        $fcmApiUrl = 'https://fcm.googleapis.com/fcm/send';

        if (empty($SERVER_API_KEY)) {
            Log::warning('FCM_SERVER_KEY not set, skipping push notifications');
            return;
        }

        try {
            $users = User::all();

            foreach ($users as $user) {
                // Create notification record in database
                Notification::create([
                    'title' => $this->title,
                    'content' => $this->content,
                    'payload' => $this->payload,
                    'user' => $user->id,
                    'position' => $this->position,
                    'type' => $this->type,
                ]);

                // Skip push notification for the current user (if specified)
                if ($this->excludeUserId && $user->id == $this->excludeUserId) {
                    continue;
                }

                // Send push notification via FCM
                $tokens = NotifToken::where('user_id', $user->id)->get();
                
                foreach ($tokens as $token) {
                    $message = [
                        'notification' => [
                            'title' => $this->title,
                            'body' => $this->content,
                        ],
                        'data' => [
                            'payload' => [
                                'title' => $this->title,
                                'content' => $this->content,
                                'payload' => $this->payload,
                                'user' => $user->id,
                                'position' => $this->position,
                                'type' => $this->type,
                            ],
                        ],
                        'to' => $token->token,
                    ];

                    try {
                        $response = Http::withHeaders([
                            'Authorization' => 'key=' . $SERVER_API_KEY,
                            'Content-Type' => 'application/json',
                        ])->post($fcmApiUrl, $message);

                        if (!$response->successful()) {
                            Log::error('FCM notification failed', [
                                'user_id' => $user->id,
                                'token' => substr($token->token, 0, 20) . '...',
                                'response' => $response->body(),
                            ]);
                        }
                    } catch (\Exception $e) {
                        Log::error('FCM notification exception', [
                            'user_id' => $user->id,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }

            Log::info('Notification job completed', [
                'title' => $this->title,
                'users_notified' => $users->count(),
            ]);

        } catch (\Exception $e) {
            Log::error('Notification job failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('Notification job permanently failed', [
            'title' => $this->title,
            'error' => $exception->getMessage(),
        ]);
    }
}
