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
            'adminUsers' => ((bool) ($request->user()->is_admin ?? false))
                ? User::query()->orderBy('id')->get(['id', 'name', 'email', 'alice_enabled', 'is_admin', 'updated_at'])
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
        $request->user()->fill([
            'name' => (string) $validated['name'],
            'locale' => (string) $validated['locale'],
            'time_zone' => (string) $validated['time_zone'],
        ]);
        $request->user()->save();

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
