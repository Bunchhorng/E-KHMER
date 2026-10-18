<?php

namespace Database\Seeders;

use App\Models\NewsletterSubscription;
use App\Models\SupportRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EngagementSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@ekhmer.com')->firstOrFail();

        foreach (User::orderBy('id')->get() as $index => $user) {
            $user->update(['newsletter' => true]);
            NewsletterSubscription::create([
                'email' => $user->email,
                'token_hash' => hash('sha256', Str::random(64)),
            ]);
            $closed = $index % 2 === 0;
            SupportRequest::create([
                'user_id' => $user->id, 'email' => $user->email,
                'subject' => 'Delivery question '.($index + 1),
                'message' => 'Could you explain the delivery options available for my next order?',
                'status' => $closed ? 'closed' : 'open',
                'reply' => $closed ? 'Available delivery options are shown during checkout.' : null,
                'replied_by' => $closed ? $admin->id : null,
            ]);
        }
    }
}
