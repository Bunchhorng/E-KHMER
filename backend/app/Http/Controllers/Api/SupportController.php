<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsletterSubscription;
use App\Models\SupportRequest;
use App\Notifications\NewsletterWelcomeNotification;
use App\Notifications\SupportReplyNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

class SupportController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'subject' => ['required', 'string', 'max:190'], 'message' => ['required', 'string', 'min:10', 'max:5000']]);
        $ticket = SupportRequest::create($data + ['user_id' => auth('sanctum')->id()]);

        return response()->json(['data' => ['id' => $ticket->id, 'status' => $ticket->status]], 201);
    }

    public function index(Request $request)
    {
        $query = SupportRequest::query()->latest();
        if (! $request->user()->isSuperAdmin() || ! $request->is('api/admin/*')) {
            $query->where('user_id', $request->user()->id);
        }
        $page = $query->paginate(15);

        return response()->json(['data' => $page->items(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()]]);
    }

    public function reply(Request $request, SupportRequest $supportRequest)
    {
        $data = $request->validate(['reply' => ['required', 'string', 'min:5', 'max:5000'], 'status' => ['required', 'in:answered,closed']]);
        $supportRequest->update($data + ['replied_by' => $request->user()->id]);
        Notification::route('mail', $supportRequest->email)->notify(new SupportReplyNotification($supportRequest->subject, $data['reply']));

        return response()->json(['data' => $supportRequest]);
    }

    public function subscribe(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'consent' => ['required', 'accepted']]);
        $email = Str::lower(trim($data['email']));
        $existing = NewsletterSubscription::where('email', $email)->first();
        if (! $existing || $existing->unsubscribed_at !== null) {
            $token = Str::random(64);
            NewsletterSubscription::updateOrCreate(['email' => $email], ['token_hash' => hash('sha256', $token), 'unsubscribed_at' => null]);
            Notification::route('mail', $email)->notify(new NewsletterWelcomeNotification($token));
        }

        return response()->json(['data' => ['message' => 'Subscription request saved.']]);
    }

    public function unsubscribe(string $token)
    {
        $subscription = NewsletterSubscription::where('token_hash', hash('sha256', $token))->firstOrFail();
        $subscription->update(['unsubscribed_at' => now()]);

        return response('You have been unsubscribed from E-KHMER updates.', 200)->header('Content-Type', 'text/plain');
    }
}
