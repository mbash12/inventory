<?php


use Src\Models\Notification;
use Src\Models\NotifToken;
use Src\Models\User;
use Src\Jobs\SendNotificationJob;
use Illuminate\Support\Facades\Http;

if (!function_exists('notify')) {
    /**
     * Send notification to users
     * 
     * @param string $title Notification title
     * @param string $content Notification content
     * @param string $position Target position/role
     * @param string|null $payload JSON payload
     * @param string $type Notification type
     * @param bool $async Whether to send asynchronously (default: true)
     * @return void
     */
    function notify($title, $content, $position, $payload, $type, $async = true)
    {
        $currentUser = auth()->user();
        $excludeUserId = $currentUser ? $currentUser->id : null;

        if ($async) {
            // Dispatch to queue - non-blocking
            SendNotificationJob::dispatch(
                $title,
                $content,
                $position,
                $payload,
                $type,
                $excludeUserId
            );
        } else {
            // Synchronous fallback (for backward compatibility if needed)
            $SERVER_API_KEY = env('FCM_SERVER_KEY');
            $fcmApiUrl = 'https://fcm.googleapis.com/fcm/send';
            
            $users = User::all();
            foreach ($users as $key => $user) {
                Notification::create([
                    "title" => $title,
                    "content" => $content,
                    "payload" => $payload,
                    "user" => $user['id'],
                    "position" => $position,
                    "type" => $type
                ]);
                if($user['id'] == $excludeUserId) {continue;}
                $tokens = NotifToken::where('user_id', $user['id'])->get();
                foreach ($tokens as $key => $token) {
                    $message = [
                        'notification' => [
                            'title' => $title,
                            'body' => $content,
                        ],
                        'data' => [
                            'payload' => [
                                "title" => $title,
                                "content" => $content,
                                "payload" => $payload,
                                "user" => $user['id'],
                                "position" => $position,
                                "type" => $type
                            ],
                        ],
                        'to' => $token->token,
                    ];
                    $response = Http::withHeaders([
                        'Authorization' => 'key=' . $SERVER_API_KEY,
                        'Content-Type' => 'application/json',
                    ])->post($fcmApiUrl, $message);
                }
            }
        }
    }
}
