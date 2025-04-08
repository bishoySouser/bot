<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

// استخدام env() بدلاً من define() لحماية البيانات الحساسة
$verifyToken = env('FACEBOOK_VERIFY_TOKEN', 'default_token');
$pageAccessToken = env('FACEBOOK_PAGE_ACCESS_TOKEN', 'default_access_token');
$dialogflowProjectId = env('DIALOGFLOW_PROJECT_ID', 'default_project_id');
$dialogflowApiKey = env('DIALOGFLOW_API_KEY', 'default_api_key');


Route::get('/facebook/webhook', function (Request $request) use ($pageAccessToken, $dialogflowApiKey) {
    $data = $request->all();

    if (!empty($data['entry'][0]['messaging'])) {
        foreach ($data['entry'][0]['messaging'] as $event) {
            if (!empty($event['message'])) {
                $senderId = $event['sender']['id'];
                $messageText = $event['message']['text'];

                // إرسال الرسالة إلى Dialogflow
                $response = Http::withHeaders([
                    'Authorization' => "Bearer $dialogflowApiKey"
                ])->post('https://api.dialogflow.com/v1/query?v=20150910', [
                    'query' => [$messageText],
                    'lang' => 'en',
                    'sessionId' => $senderId,
                    'timezone' => 'Africa/Cairo',
                ]);

                $botReply = $response->json()['result']['fulfillment']['speech'] ?? 'لم أفهم ذلك!';

                // إرسال الرد إلى المرسل
                Http::post("https://graph.facebook.com/v18.0/me/messages?access_token=$pageAccessToken", [
                    'recipient' => ['id' => $senderId],
                    'message' => ['text' => $botReply],
                ]);
            }
        }
    }
    return response()->json(['status' => 'success']);
});

Route::get('/facebook/comment-webhook', function (Request $request) use ($pageAccessToken, $dialogflowApiKey) {
    $data = $request->all();

    if (!empty($data['entry'][0]['changes'])) {
        foreach ($data['entry'][0]['changes'] as $change) {
            if ($change['field'] === 'comments') {
                $commentId = $change['value']['comment_id'];
                $commentText = $change['value']['message'];

                // إرسال التعليق إلى Dialogflow
                $response = Http::withHeaders([
                    'Authorization' => "Bearer $dialogflowApiKey"
                ])->post('https://api.dialogflow.com/v1/query?v=20150910', [
                    'query' => [$commentText],
                    'lang' => 'en',
                    'sessionId' => $commentId,
                    'timezone' => 'Africa/Cairo',
                ]);

                $botReply = $response->json()['result']['fulfillment']['speech'] ?? 'شكراً على تعليقك!';

                // الرد على التعليق
                Http::post("https://graph.facebook.com/v18.0/$commentId/comments?access_token=$pageAccessToken", [
                    'message' => $botReply,
                ]);
            }
        }
    }
    return response()->json(['status' => 'success']);
});
