<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\User;
use App\Notifications\OrderPlacedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MailMimeRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_order_confirmation_mail_renders_subject_and_html_body(): void
    {
        $customer = User::factory()->create(['name' => 'Sokha']);
        $order = Order::factory()->create([
            'user_id' => $customer->id,
            'customer_name' => $customer->name,
            'total' => '42.50',
        ]);

        $message = (new OrderPlacedNotification($order))->toMail($customer);
        $html = (string) $message->render();

        $this->assertSame('Order confirmed: #'.$order->order_number, $message->subject);
        $this->assertStringContainsString('Your order #'.$order->order_number.' has been confirmed.', $html);
        $this->assertStringContainsString('Order total: $42.50', $html);
    }
}
