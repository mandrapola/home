<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\UserCrudRequest;
use App\Models\Plan;
use App\Models\User;
use App\Support\Billing\SubscriptionSource;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class UserCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;
    use UpdateOperation {
        update as traitUpdate;
    }

    public function setup(): void
    {
        CRUD::setModel(User::class);
        CRUD::setRoute(backpack_url('users'));
        CRUD::setEntityNameStrings(__('user'), __('users'));
    }

    protected function setupListOperation(): void
    {
        CRUD::column('id')->label('ID');
        CRUD::column('name')->label(__('Name'));
        CRUD::column('email')->label(__('E-mail'));
        CRUD::addColumn([
            'name' => 'roles_list',
            'label' => __('Roles'),
            'type' => 'model_function',
            'function_name' => 'getRolesListForAdmin',
        ]);
        CRUD::addColumn([
            'name' => 'selectedPlan.name',
            'label' => __('Plan'),
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'alice_enabled',
            'label' => __('Alice Enabled'),
            'type' => 'boolean',
        ]);
        CRUD::addButtonFromView('line', 'assign_plan', 'assign_plan', 'end');
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        CRUD::column('time_zone')->label(__('Time Zone'));
        CRUD::column('locale')->label(__('Locale'));
        CRUD::column('created_at')->label(__('Created'));
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation(UserCrudRequest::class);

        CRUD::field('name')->label(__('Name'));
        CRUD::field('email')->label(__('E-mail'));
        CRUD::addField([
            'name' => 'time_zone',
            'type' => 'select_from_array',
            'label' => __('Time Zone'),
            'options' => array_combine(\DateTimeZone::listIdentifiers(), \DateTimeZone::listIdentifiers()),
            'allows_null' => false,
        ]);
        CRUD::addField([
            'name' => 'locale',
            'type' => 'select_from_array',
            'label' => __('Locale'),
            'options' => [
                'ru' => 'ru',
                'en' => 'en',
            ],
            'allows_null' => false,
        ]);
        CRUD::addField([
            'name' => 'alice_enabled',
            'type' => 'radio',
            'label' => __('Alice Enabled'),
            'options' => [1 => __('Yes'), 0 => __('No')],
            'default' => 0,
            'inline' => true,
        ]);
    }

    public function update()
    {
        $response = $this->traitUpdate();

        $entry = $this->crud->getCurrentEntry();
        if (request()->has('roles')) {
            $roleIds = (array) request()->input('roles', []);
            $roleNames = \Spatie\Permission\Models\Role::query()
                ->whereIn('id', array_map('intval', $roleIds))
                ->pluck('name')
                ->all();
            $entry->syncRoles($roleNames);
        }

        return $response;
    }

    public function editPlan(int $id)
    {
        $targetUser = User::query()->findOrFail($id);
        $plans = Plan::query()->where('is_active', true)->orderBy('price_amount')->get(['id', 'name']);
        $subscription = $targetUser->planSubscriptions()->latest('id')->first();

        return view('admin.backpack.user-plan-edit', compact('targetUser', 'plans', 'subscription'));
    }

    public function updatePlan(Request $request, int $id): RedirectResponse
    {
        $targetUser = User::query()->findOrFail($id);
        $validated = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:plans,id'],
            'status' => ['required', 'string', 'in:pending,active,expired,canceled'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
        ]);

        $targetUser->planSubscriptions()->create([
            ...$validated,
            'source' => SubscriptionSource::ADMIN_MANUAL,
        ]);
        $targetUser->selected_plan_id = (int) $validated['plan_id'];
        $targetUser->save();

        return redirect(backpack_url('users'))->with('status', __('User plan assignment updated.'));
    }
}
