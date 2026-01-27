<?php


use Src\Models\Notification;
use Src\Models\NotifToken;
use Src\Models\User;
use Illuminate\Support\Facades\Http;

if (!function_exists('notify')) {
    function notify($title, $content, $position, $payload, $type)
    {
        $SERVER_API_KEY = env('FCM_SERVER_KEY');
        $fcmApiUrl = 'https://fcm.googleapis.com/fcm/send';
        $currentUser = auth()->user();
        error_log($currentUser->id);
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
            if($user['id'] == $currentUser->id) {continue;}
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
