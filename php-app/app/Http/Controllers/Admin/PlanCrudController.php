<?php

namespace App\Http\Controllers\Admin;

use App\Models\Plan;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Validation\Rule;

class PlanCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;
    use CreateOperation;
    use UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(Plan::class);
        CRUD::setRoute(backpack_url('plans'));
        CRUD::setEntityNameStrings(__('plan'), __('plans'));
    }

    protected function setupListOperation(): void
    {
        CRUD::column('id')->label('ID');
        CRUD::column('name')->label(__('Name'));
        CRUD::column('daily_price_units')->label(__('Daily price'));
        CRUD::column('price_currency')->label(__('Currency'));
        CRUD::column('max_controllers')->label(__('Max Controllers'));
        CRUD::column('max_pin_data_rows')->label(__('Max Pin Data Rows'));
        CRUD::column('alice_enabled')->type('boolean')->label(__('Alice Enabled'));
        CRUD::column('is_active')->type('boolean')->label(__('Active'));
    }

    protected function setupCreateOperation(): void
    {
        CRUD::setValidation([
            'code' => ['required', 'string', 'max:64', 'unique:plans,code'],
            'name' => ['required', 'string', 'max:128'],
            'daily_price_units' => ['required', 'integer', 'min:0'],
            'price_currency' => ['required', 'string', 'size:3'],
        ]);

        $this->setupPlanFields();
    }

    private function setupPlanFields(): void
    {

        CRUD::field('name')->label(__('Name'));
        CRUD::field('description')->type('textarea')->label(__('Description'));
        CRUD::field('daily_price_units')->type('number')->attributes(['step' => '1', 'min' => '0'])->label(__('Daily price'));
        CRUD::addField([
            'name' => 'code',
            'type' => 'text',
            'label' => __('Code'),
            'attributes' => [
                'placeholder' => 'free, pro, premium_30',
            ],
            'hint' => __('Internal unique identifier. Use lowercase latin letters, numbers and underscore.'),
        ]);
        CRUD::addField([
            'name' => 'price_currency',
            'type' => 'select_from_array',
            'label' => __('Currency'),
            'options' => [
                'RUB' => 'RUB',
                'USD' => 'USD',
                'EUR' => 'EUR',
            ],
            'allows_null' => false,
            'default' => 'RUB',
        ]);
        CRUD::field('max_controllers')->type('number')->label(__('Max Controllers'));
        CRUD::field('max_pin_data_rows')->type('number')->label(__('Max Pin Data Rows'));
        CRUD::addField([
            'name' => 'alice_enabled',
            'type' => 'radio',
            'label' => __('Alice Enabled'),
            'options' => [1 => __('Yes'), 0 => __('No')],
            'inline' => true,
        ]);
        CRUD::addField([
            'name' => 'is_active',
            'type' => 'radio',
            'label' => __('Active'),
            'options' => [1 => __('Yes'), 0 => __('No')],
            'inline' => true,
        ]);
    }

    protected function setupUpdateOperation(): void
    {
        $id = $this->crud->getCurrentEntryId();
        CRUD::setValidation([
            'code' => ['required', 'string', 'max:64', Rule::unique('plans', 'code')->ignore($id)],
            'name' => ['required', 'string', 'max:128'],
            'daily_price_units' => ['required', 'integer', 'min:0'],
            'price_currency' => ['required', 'string', 'size:3'],
        ]);

        $this->setupPlanFields();
    }
}
