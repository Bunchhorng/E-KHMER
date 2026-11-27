<?php

namespace Database\Seeders;

use App\Models\NewsletterSubscription;
use App\Models\SupportRequest;
use App\Models\User;
use Database\Seeders\Concerns\EnsuresLocalDemoEnvironment;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class EngagementSeeder extends Seeder
{
    use EnsuresLocalDemoEnvironment;

    public function run(): void
    {
        $this->ensureLocalOrTestingEnvironment('Demo engagement data may only be seeded in local or testing environments.');

        $admin = User::where('email', 'admin@ekhmer.com')->firstOrFail();

        $subscribers = User::where('newsletter', true)->orderBy('id')->take(25)->get();
        foreach ($subscribers as $user) {
            NewsletterSubscription::firstOrCreate(
                ['email' => $user->email],
                ['token_hash' => hash('sha256', 'demo-newsletter-'.$user->email.'-'.Str::random(12))]
            );
        }

        $tickets = [
            ['customer@ekhmer.com', 'Provincial delivery estimate for Siem Reap', 'Can Virak Buntham Express deliver fragile ceramic tableware to Siem Reap within 3 days?', 'closed', 'Yes! Standard provincial courier arrives in 2–4 business days with protective bubble-wrap packaging.'],
            ['customer@ekhmer.com', 'Bakong KHQR payment confirmation window', 'How long do I have to scan the KHQR code during checkout?', 'open', null],
            ['sokha.chea@ekhmer.test', 'Bulk pricing for wedding krama gift boxes', 'Do you offer a coupon or bundle discount for 30 silk kramas?', 'closed', 'You can apply code ANGKOR15 for Angkor Silk & Krama House orders above $20, or message the seller directly.'],
            ['dara.meas@ekhmer.test', 'Cash on delivery receipt request', 'Please advise where I can download my PDF receipt after COD collection.', 'closed', 'Open your Order Details page and click Download Receipt; payment status updates once the courier records collection.'],
            ['bopha.phan@ekhmer.test', 'Exchange size on linen shirt', 'Can I request a size exchange from M to L within 14 days?', 'open', null],
            ['visal.chhim@ekhmer.test', 'Checking stock restock for Kampot pepper', 'When will the 250g whole white peppercorn pack be back in stock?', 'open', null],
        ];

        $usersByEmail = User::whereIn('email', array_column($tickets, 0))->pluck('id', 'email');

        foreach ($tickets as [$email, $subject, $message, $status, $reply]) {
            SupportRequest::firstOrCreate(
                ['email' => $email, 'subject' => $subject],
                [
                    'user_id' => $usersByEmail[$email] ?? null,
                    'message' => $message,
                    'status' => $status,
                    'reply' => $reply,
                    'replied_by' => $status === 'closed' ? $admin->id : null,
                ]
            );
        }
    }
}
