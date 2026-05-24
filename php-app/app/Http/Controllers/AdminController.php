<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\IoTController;
use App\Models\Plan;
use App\Models\User;
use App\Models\UserPlanSubscription;
use App\Support\Billing\SubscriptionSource;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class AdminController extends Controller
{
    public function dashboard(): View
    {
        return view('admin.dashboard', [
            'usersCount' => (int) User::query()->count(),
            'controllersCount' => (int) IoTController::query()->count(),
            'plansCount' => (int) Plan::query()->count(),
        ]);
    }

    public function users(): View
    {
        $users = User::query()
            ->with(['roles:id,name', 'selectedPlan:id,name'])
            ->orderBy('id')
            ->get(['id', 'name', 'email', 'alice_enabled', 'selected_plan_id', 'created_at']);
        return view('admin.users', ['users' => $users]);
    }

    public function editUser(User $user): View
    {
        return view('admin.user-edit', ['targetUser' => $user]);
    }

    public function updateUser(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email,' . $user->id],
            'time_zone' => ['required', 'timezone:all'],
            'locale' => ['required', 'string', 'max:10'],
            'alice_enabled' => ['required', 'boolean'],
        ]);

        $user->fill([
            'name' => (string) $validated['name'],
            'email' => (string) $validated['email'],
            'time_zone' => (string) $validated['time_zone'],
            'locale' => (string) $validated['locale'],
            'alice_enabled' => ! empty($validated['alice_enabled']),
        ])->save();

        return redirect()->route('admin.users')->with('status', __('User updated.'));
    }

    public function editUserPlan(User $user): View
    {
        $plans = Plan::query()->where('is_active', true)->orderBy('daily_price_units')->get(['id', 'name']);
        $subscription = UserPlanSubscription::query()
            ->where('user_id', $user->id)
            ->latest('id')
            ->first();

        return view('admin.user-plan-edit', [
            'targetUser' => $user,
            'plans' => $plans,
            'subscription' => $subscription,
        ]);
    }

    public function updateUserPlan(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['required', 'string', 'in:pending,active,expired,canceled'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $startsAt = Carbon::parse((string) $validated['starts_at']);
        $endsAt = ! empty($validated['ends_at']) ? Carbon::parse((string) $validated['ends_at']) : null;

        UserPlanSubscription::query()->create([
            'user_id' => $user->id,
            'plan_id' => (int) $validated['plan_id'],
            'status' => (string) $validated['status'],
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'source' => SubscriptionSource::ADMIN_MANUAL,
        ]);

        $user->selected_plan_id = (int) $validated['plan_id'];
        $user->save();

        return redirect()->route('admin.users')->with('status', __('User plan assignment updated.'));
    }

    public function controllers(): View
    {
        $controllers = IoTController::query()
            ->withCount('users')
            ->orderBy('name')
            ->get(['id', 'name', 'status', 'last_seen_at']);

        return view('admin.controllers', ['controllers' => $controllers]);
    }

    public function controllerShow(string $controllerId): View
    {
        $controller = IoTController::query()->findOrFail($controllerId);
        $pins = DB::table('pin')
            ->where('controller_id', $controllerId)
            ->orderBy('pin')
            ->get(['id', 'pin', 'label', 'digital_style']);

        return view('admin.controller-show', [
            'controller' => $controller,
            'pins' => $pins,
        ]);
    }

    public function pinEdit(string $pinId): View
    {
        $pin = DB::table('pin')->where('id', $pinId)->first();
        abort_if(! $pin, 404);

        return view('admin.pin-edit', [
            'pin' => $pin,
            'hasExternalEnabled' => DB::getSchemaBuilder()->hasColumn('pin', 'external_enabled'),
        ]);
    }

    public function pinUpdate(Request $request, string $pinId): RedirectResponse
    {
        $pin = DB::table('pin')->where('id', $pinId)->first();
        abort_if(! $pin, 404);

        $isPower = (string) ($pin->digital_style ?? '') === 'power';

        $rules = [
            'label' => ['required', 'string', 'max:255'],
        ];

        if ($isPower) {
            $rules['show_on_report'] = ['required', 'boolean'];
        } else {
            $rules['unit'] = ['nullable', 'string', 'max:32'];
            $rules['show_on_chart'] = ['required', 'boolean'];
            $rules['is_monitored'] = ['required', 'boolean'];
            $rules['chart_range_hours'] = ['required', 'integer', 'min:1', 'max:24'];
            if ((string) ($pin->digital_style ?? '') === 'sensor_humidity') {
                $rules['moisture_raw_dry'] = ['nullable', 'numeric'];
                $rules['moisture_raw_wet'] = ['nullable', 'numeric'];
                $rules['moisture_show_percent'] = ['required', 'boolean'];
            }
        }

        if (DB::getSchemaBuilder()->hasColumn('pin', 'external_enabled')) {
            $rules['external_enabled'] = ['required', 'boolean'];
        }

        $validated = $request->validate($rules);

        $updateData = [
            'label' => (string) $validated['label'],
        ];

        if ($isPower) {
            $updateData['show_on_report'] = ! empty($validated['show_on_report']) ? 1 : 0;
        } else {
            $updateData['unit'] = isset($validated['unit']) && trim((string) $validated['unit']) !== ''
                ? (string) $validated['unit']
                : null;
            $updateData['show_on_chart'] = ! empty($validated['show_on_chart']) ? 1 : 0;
            $updateData['is_monitored'] = ! empty($validated['is_monitored']) ? 1 : 0;
            $updateData['chart_range_hours'] = (int) $validated['chart_range_hours'];
            if ((string) ($pin->digital_style ?? '') === 'sensor_humidity') {
                $updateData['moisture_raw_dry'] = isset($validated['moisture_raw_dry']) && $validated['moisture_raw_dry'] !== ''
                    ? (float) $validated['moisture_raw_dry']
                    : null;
                $updateData['moisture_raw_wet'] = isset($validated['moisture_raw_wet']) && $validated['moisture_raw_wet'] !== ''
                    ? (float) $validated['moisture_raw_wet']
                    : null;
                $updateData['moisture_show_percent'] = ! empty($validated['moisture_show_percent']) ? 1 : 0;
            }
        }

        if (array_key_exists('external_enabled', $validated) && DB::getSchemaBuilder()->hasColumn('pin', 'external_enabled')) {
            $updateData['external_enabled'] = ! empty($validated['external_enabled']) ? 1 : 0;
        }

        DB::table('pin')->where('id', $pinId)->update($updateData);

        return redirect()->route('admin.controller.show', ['controllerId' => $pin->controller_id])
            ->with('status', __('Pin updated.'));
    }
}
