<?php

namespace App\Http\Controllers\Admin;

use App\Models\PaymentTransaction;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
use Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

class PaymentTransactionCrudController extends CrudController
{
    use ListOperation;
    use ShowOperation;

    public function setup(): void
    {
        CRUD::setModel(PaymentTransaction::class);
        CRUD::setRoute(backpack_url('payments'));
        CRUD::setEntityNameStrings(__('payment'), __('payments'));
    }

    protected function setupListOperation(): void
    {
        CRUD::column('id')->label('ID');
        CRUD::addColumn([
            'name' => 'user.email',
            'label' => __('User'),
            'type' => 'text',
        ]);
        CRUD::addColumn([
            'name' => 'plan.name',
            'label' => __('Plan'),
            'type' => 'text',
        ]);
        CRUD::column('amount')->label(__('Amount'));
        CRUD::column('currency')->label(__('Currency'));
        CRUD::column('status')->label(__('Status'));
        CRUD::column('provider')->label(__('Provider'));
        CRUD::column('provider_payment_id')->label(__('Provider Payment ID'));
        CRUD::column('paid_at')->type('datetime')->label(__('Paid At'));

        $this->crud->denyAccess(['create', 'update', 'delete']);
    }

    protected function setupShowOperation(): void
    {
        $this->setupListOperation();
        CRUD::column('idempotence_key')->label(__('Idempotence Key'));
        CRUD::addColumn([
            'name' => 'meta',
            'label' => __('Meta'),
            'type' => 'textarea',
        ]);
    }
}
