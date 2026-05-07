<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\PinRequest;
use App\Models\IoTController;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class PinCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class PinCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\Pin::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/pins');
        CRUD::setEntityNameStrings('pin', 'pins');

        // Pins are managed from the Controller preview; standalone list/create/delete are disabled.
        CRUD::denyAccess(['list', 'create', 'delete']);

        if (! backpack_user()?->can('admin.pins.view')) {
            abort(403);
        }
        if (! backpack_user()?->can('admin.pins.update')) {
            CRUD::denyAccess(['update']);
        }
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'controller',
            'type' => 'relationship',
            'label' => 'Controller',
            'attribute' => 'name',
        ]);
        CRUD::column('pin');
        CRUD::column('label');
        CRUD::column('digital_style');
        CRUD::column('unit');
        CRUD::column('show_on_chart')->type('boolean');
        CRUD::column('show_on_report')->type('boolean');
        CRUD::column('is_monitored')->type('boolean');
        CRUD::column('external_enabled')->type('boolean');
        CRUD::column('enable_scenario')->type('boolean');
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(PinRequest::class);

        CRUD::field('pin')->tipe('text')->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
        CRUD::field('label');
        CRUD::field('digital_style')->tipe('text')->attributes(['readonly' => 'readonly', 'disabled' => 'disabled']);
        CRUD::field('unit')->type('select_from_array')->options(IoTController::PIN_UNIT_OPTIONS);
        CRUD::field('show_on_chart')->type('checkbox');
        CRUD::field('show_on_report')->type('checkbox');
        CRUD::field('is_monitored')->type('checkbox');
        CRUD::field('external_enabled')->type('checkbox');
        CRUD::field('moisture_raw_dry')->type('number');
        CRUD::field('moisture_raw_wet')->type('number');
        CRUD::field('moisture_show_percent')->type('checkbox');
        CRUD::field('chart_range_hours')->type('number');
        CRUD::field('enable_scenario')->type('checkbox');
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
