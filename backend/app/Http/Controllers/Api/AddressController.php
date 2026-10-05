<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\AddressRequest;
use App\Http\Resources\AddressResource;
use App\Models\Address;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

        $address = DB::transaction(function () use ($request, $data): Address {
            $user = $this->lockOwner($request);

            // A user's first address is always the default one, otherwise checkout
            // would have nothing pre-selected.
            $isDefault = $request->boolean('is_default') || $user->addresses()->doesntExist();

            if ($isDefault) {
                $this->unsetDefaults($user->id);
            }

            return $user->addresses()->create(array_merge($data, [
                'is_default' => $isDefault,
            ]));
        });

        return (new AddressResource($address))->response()->setStatusCode(201);
    }

    public function update(AddressRequest $request, Address $address)
    {
        $this->authorizeOwnership($request, $address);

        $address = DB::transaction(function () use ($request, $address): Address {
            $user = $this->lockOwner($request);
            $address = $user->addresses()->lockForUpdate()->findOrFail($address->id);
            $data = $request->validated();

            // When is_default is not supplied the current flag is preserved;
            // demoting the default address would otherwise leave the user with
            // no default at all.
            $wasDefault = (bool) $address->is_default;
            $isDefault = $request->has('is_default') ? $request->boolean('is_default') : $wasDefault;

            if ($isDefault && ! $wasDefault) {
                $this->unsetDefaults($user->id);
            }

            $address->update(array_merge($data, ['is_default' => $isDefault]));

            if ($wasDefault && ! $isDefault) {
                $this->promoteAnotherDefault($user->id, $address);
            }

            return $address->refresh();
        });

        return new AddressResource($address);
    }

    public function destroy(Request $request, Address $address)
    {
        $this->authorizeOwnership($request, $address);

        DB::transaction(function () use ($request, $address): void {
            $user = $this->lockOwner($request);
            $address = $user->addresses()->lockForUpdate()->findOrFail($address->id);
            $wasDefault = (bool) $address->is_default;
            $address->delete();

            if ($wasDefault) {
                $this->promoteAnotherDefault($user->id, null);
            }
        });

        return response()->json(['data' => ['message' => 'Address deleted.']]);
    }

    public function setDefault(Request $request, Address $address)
    {
        $this->authorizeOwnership($request, $address);

        $address = DB::transaction(function () use ($request, $address): Address {
            $user = $this->lockOwner($request);
            $address = $user->addresses()->lockForUpdate()->findOrFail($address->id);

            $this->unsetDefaults($user->id);
            $address->update(['is_default' => true]);

            return $address->refresh();
        });

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
     * Serialise default-address changes for one customer. Locking the owner also
     * covers the no-address-yet case, where locking address rows alone cannot
     * prevent two first-address requests from both becoming the default.
     */
    protected function lockOwner(Request $request): User
    {
        return User::query()->lockForUpdate()->findOrFail($request->user()->id);
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
