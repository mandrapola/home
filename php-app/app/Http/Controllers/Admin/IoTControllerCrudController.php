<?php

namespace App\Http\Controllers\Admin;

use App\Models\IoTController;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class IoTControllerCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(IoTController::class);
        CRUD::setRoute(backpack_url('controllers'));
        CRUD::setEntityNameStrings(__('controller'), __('controllers'));
    }

    protected function setupListOperation(): void
    {
        CRUD::column('id')->label('ID');
        CRUD::column('name')->label(__('Name'));
        CRUD::column('status')->label(__('Status'));
        CRUD::column('last_seen_at')->type('datetime')->label(__('Last Seen'));
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        CRUD::addColumn([
            'name' => 'pins',
            'label' => __('Pins'),
            'type' => 'relationship_count',
            'suffix' => '',
        ]);
        CRUD::addButtonFromView('line', 'pins_table', 'controller_pins_table', 'end');
    }
}
