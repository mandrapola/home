<?php

namespace App\Http\Controllers\Admin;

use App\Models\Pin;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use Illuminate\Support\Facades\Schema;

class PinCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;
    use UpdateOperation;

    public function setup(): void
    {
        CRUD::setModel(Pin::class);
        CRUD::setRoute(backpack_url('pins'));
        CRUD::setEntityNameStrings(__('pin'), __('pins'));
        $this->crud->denyAccess(['create', 'delete']);
    }

    protected function setupListOperation(): void
    {
        CRUD::column('id')->label('ID');
        CRUD::column('controller_id')->label(__('Controller ID'));
        CRUD::column('pin')->label(__('Pin'));
        CRUD::column('label')->label(__('Label'));
        CRUD::column('digital_style')->label(__('Digital Style'));
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        CRUD::column('unit')->label(__('Unit'));
        CRUD::column('value')->label(__('Value'));
        CRUD::column('show_on_chart')->type('boolean')->label(__('Show On Chart'));
        CRUD::column('show_on_report')->type('boolean')->label(__('Show On Report'));
        CRUD::column('is_monitored')->type('boolean')->label(__('Monitored'));
        CRUD::column('external_enabled')->type('boolean')->label(__('External Enabled'));
    }

    protected function setupUpdateOperation(): void
    {
        CRUD::setValidation([
            'label' => ['required', 'string', 'max:255'],
        ]);

        $entry = $this->crud->getCurrentEntry();
        $digitalStyle = (string) ($entry->digital_style ?? '');
        $isPower = $digitalStyle === 'power';

        CRUD::field('label')->label(__('Label'));

        if ($isPower) {
            CRUD::addField([
                'name' => 'show_on_report',
                'type' => 'radio',
                'label' => __('Show On Report'),
                'options' => [1 => __('Yes'), 0 => __('No')],
                'inline' => true,
            ]);
        } else {
            CRUD::field('unit')->label(__('Unit'));
            CRUD::addField([
                'name' => 'show_on_chart',
                'type' => 'radio',
                'label' => __('Show On Chart'),
                'options' => [1 => __('Yes'), 0 => __('No')],
                'inline' => true,
            ]);
            CRUD::addField([
                'name' => 'is_monitored',
                'type' => 'radio',
                'label' => __('Monitored'),
                'options' => [1 => __('Yes'), 0 => __('No')],
                'inline' => true,
            ]);
            CRUD::field('chart_range_hours')->type('number')->attributes(['min' => 1, 'max' => 24])->label(__('Chart Range Hours'));
            if ($digitalStyle === 'sensor_humidity') {
                CRUD::field('moisture_raw_dry')->type('number')->attributes(['step' => '0.01'])->label(__('Moisture Raw Dry'));
                CRUD::field('moisture_raw_wet')->type('number')->attributes(['step' => '0.01'])->label(__('Moisture Raw Wet'));
                CRUD::addField([
                    'name' => 'moisture_show_percent',
                    'type' => 'radio',
                    'label' => __('Moisture Show Percent'),
                    'options' => [1 => __('Yes'), 0 => __('No')],
                    'inline' => true,
                ]);
            }
        }

        if (Schema::hasColumn('pin', 'external_enabled')) {
            CRUD::addField([
                'name' => 'external_enabled',
                'type' => 'radio',
                'label' => __('External Enabled'),
                'options' => [1 => __('Yes'), 0 => __('No')],
                'inline' => true,
            ]);
        }
    }
}
