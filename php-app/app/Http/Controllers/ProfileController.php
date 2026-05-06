<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): View
    {
        $timeZones = \DateTimeZone::listIdentifiers();
        $locales = ['ru', 'en'];

        return view('profile.edit', [
            'user' => $request->user(),
            'timeZones' => $timeZones,
            'locales' => $locales,
            'adminUsers' => ($request->user() && $request->user()->hasRole('administrator'))
                ? User::query()
                    ->with('roles:id,name')
                    ->orderBy('id')
                    ->get(['id', 'name', 'email', 'alice_enabled', 'updated_at'])
                : collect(),
            'aliceLinkedAccount' => DB::table('alice_accounts')
                ->where('user_id', $request->user()->id)
                ->whereNull('unlinked_at')
                ->orderByDesc('updated_at')
                ->first(['yandex_user_id', 'updated_at']),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $user = $request->user();

        $newEmail = isset($validated['email']) && trim((string) $validated['email']) !== ''
            ? (string) $validated['email']
            : (string) $user->email;

        $user->fill([
            'name' => (string) $validated['name'],
            'email' => $newEmail,
            'locale' => (string) ($validated['locale'] ?? $user->locale),
            'time_zone' => (string) ($validated['time_zone'] ?? $user->time_zone),
        ]);

        if ($newEmail !== (string) $user->getOriginal('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    public function updateUserAliceAccess(Request $request, User $targetUser): RedirectResponse
    {
        $validated = $request->validate([
            'alice_enabled' => ['required', 'boolean'],
        ]);

        $targetUser->alice_enabled = ! empty($validated['alice_enabled']);
        $targetUser->save();

        return Redirect::route('profile.edit')->with('alice-status', __('Alice access updated.'));
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
