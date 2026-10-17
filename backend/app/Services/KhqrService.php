<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/** Dynamic individual KHQR, following NBC KHQR SDK v2.9 tag layout. */
class KhqrService
{
    private function tag(string $id, string $value): string
    {
        if (strlen($value) > 99) {
            throw ValidationException::withMessages(['payment' => 'KHQR field exceeds its supported length.']);
        }

        return $id.str_pad((string) strlen($value), 2, '0', STR_PAD_LEFT).$value;
    }

    public function generate(string $account, string $name, string $city, string $amount, string $currency, string $reference, Carbon $expiresAt): string
    {
        if (! in_array($currency, ['USD', 'KHR'], true) || (float) $amount <= 0) {
            throw ValidationException::withMessages(['payment' => 'Unsupported KHQR amount or currency.']);
        }
        $qr = $this->tag('00', '01').$this->tag('01', '12')
            .$this->tag('29', $this->tag('00', $account))
            .$this->tag('52', '5999').$this->tag('53', $currency === 'USD' ? '840' : '116')
            .$this->tag('54', $amount).$this->tag('58', 'KH')
            .$this->tag('59', $name).$this->tag('60', $city)
            .$this->tag('62', $this->tag('01', substr($reference, 0, 25)))
            .$this->tag('99', $this->tag('00', (string) now()->getTimestampMs()).$this->tag('01', (string) $expiresAt->getTimestampMs())).'6304';
        $crc = 0xFFFF;
        for ($i = 0; $i < strlen($qr); $i++) {
            $crc ^= ord($qr[$i]) << 8;
            for ($bit = 0; $bit < 8; $bit++) {
                $crc = (($crc & 0x8000) ? ($crc << 1) ^ 0x1021 : ($crc << 1)) & 0xFFFF;
            }
        }

        return $qr.strtoupper(str_pad(dechex($crc), 4, '0', STR_PAD_LEFT));
    }
}
