<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function index(Request $request)
    {
        $addresses = $request->user()->addresses()
            ->orderByDesc('is_default')
            ->orderByDesc('id')
            ->get();

        return AddressResource::collection($addresses);
    }

    public function store(AddressRequest $request)
    {
        $data = $request->validated();

        // A user's first address is always the default one, otherwise checkout
        // would have nothing pre-selected.
        $isDefault = $request->boolean('is_default') || $request->user()->addresses()->doesntExist();

        if ($isDefault) {
            $this->unsetDefaults($request->user()->id);
        }

        $address = $request->user()->addresses()->create(array_merge($data, [
            'is_default' => $isDefault,
        ]));

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function update(AddressRequest $request, Address $address)
    {
        $this->authorizeOwnership($request, $address);

        $data = $request->validated();

        // When is_default is not supplied the current flag is preserved;
        // demoting the default address would otherwise leave the user with
        // no default at all.
        $wasDefault = (bool) $address->is_default;
        $isDefault = $request->has('is_default') ? $request->boolean('is_default') : $wasDefault;

        if ($isDefault && ! $wasDefault) {
            $this->unsetDefaults($request->user()->id);
        }

        $address->update(array_merge($data, ['is_default' => $isDefault]));

        if ($wasDefault && ! $isDefault) {
            $this->promoteAnotherDefault($request->user()->id, $address);
        }

        return new AddressResource($address->refresh());
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorizeOwnership($request, $address);

        $wasDefault = (bool) $address->is_default;
        $address->delete();

        if ($wasDefault) {
            $this->promoteAnotherDefault($request->user()->id, null);
        }

        return response()->json(['data' => ['message' => 'Address deleted.']]);
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeOwnership($request, $address);

        $this->unsetDefaults($request->user()->id);
        $address->update(['is_default' => true]);

        return new AddressResource($address);
    }

    protected function authorizeOwnership(Request $request, Address $address): void
    {
        if ((int) $address->user_id !== (int) $request->user()->id) {
            abort(404, 'Address not found.');
        }
    }

    protected function unsetDefaults(int $userId): void
    {
        Address::where('user_id', $userId)->where('is_default', true)->update(['is_default' => false]);
    }

    /**
     * Keep the "exactly one default" invariant after a default is removed or
     * demoted. The most recent remaining address is promoted.
     */
    protected function promoteAnotherDefault(int $userId, ?Address $exclude): void
    {
        $query = Address::where('user_id', $userId);

        if ($exclude !== null) {
            $query->whereKeyNot($exclude->getKey());
        }

        $replacement = $query->orderByDesc('id')->first();

        $replacement?->update(['is_default' => true]);
    }
}
