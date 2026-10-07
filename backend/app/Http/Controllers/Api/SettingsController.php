<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Inventory;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SettingsController extends Controller
{
    protected array $defaults = [
        'storeName' => 'E-KHMER',
        'supportEmail' => 'support@e-khmer.com',
        'supportPhone' => '',
        'storeAddress' => '',
        'currency' => 'USD',
        'locale' => 'en',
        'timezone' => 'Asia/Phnom_Penh',
        'lowStockThreshold' => 5,
        'emailOrderNotifications' => true,
        'emailLowStockAlerts' => true,
        'maintenanceMode' => false,
    ];

    protected array $casts = [
        'lowStockThreshold' => 'int',
        'emailOrderNotifications' => 'bool',
        'emailLowStockAlerts' => 'bool',
        'maintenanceMode' => 'bool',
    ];

    public function show()
    {
        return $this->response($this->loadAll());
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'storeName' => ['sometimes', 'string', 'max:120'],
            'supportEmail' => ['sometimes', 'email', 'max:190'],
            'supportPhone' => ['sometimes', 'string', 'max:30'],
            'storeAddress' => ['sometimes', 'string', 'max:500'],
            'currency' => ['sometimes', 'string', 'max:5', Rule::in(['USD', 'EUR', 'KHR'])],
            'locale' => ['sometimes', 'string', 'max:5', Rule::in(['en', 'km'])],
            'timezone' => ['sometimes', 'string', 'max:60', 'timezone'],
            'lowStockThreshold' => ['sometimes', 'integer', 'min:0', 'max:999'],
            'emailOrderNotifications' => ['sometimes', 'boolean'],
            'emailLowStockAlerts' => ['sometimes', 'boolean'],
            'maintenanceMode' => ['sometimes', 'boolean'],
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $this->castStored($key, $value));
        }

        // The admin setting is the platform-wide default. Synchronize existing
        // rows as well, so changing it has an immediate, visible effect on low
        // stock badges and notifications rather than only future variants.
        if (array_key_exists('lowStockThreshold', $data)) {
            Inventory::query()->update(['low_stock_threshold' => (int) $data['lowStockThreshold']]);
        }

        return $this->response($this->loadAll());
    }

    protected function loadAll(): array
    {
        $records = Setting::all()->keyBy('key');

        $result = [];
        foreach ($this->defaults as $key => $default) {
            $stored = $records->get($key)?->value;
            $result[$key] = $stored !== null ? $this->castFromStored($key, $stored) : $default;
        }

        return $result;
    }

    protected function castStored(string $key, mixed $value): mixed
    {
        return match ($this->casts[$key] ?? null) {
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };
    }

    protected function castFromStored(string $key, mixed $value): mixed
    {
        return match ($this->casts[$key] ?? null) {
            'bool' => (bool) $value,
            'int' => (int) $value,
            default => (string) $value,
        };
    }

    protected function response(array $settings)
    {
        return response()->json(['data' => $settings]);
    }
}
