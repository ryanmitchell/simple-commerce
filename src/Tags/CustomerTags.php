<?php

namespace DoubleThreeDigital\SimpleCommerce\Tags;

use DoubleThreeDigital\SimpleCommerce\Facades\Customer;
use DoubleThreeDigital\SimpleCommerce\Orders\Order;
use Statamic\Entries\Entry as EntriesEntry;
use Statamic\Facades\Entry;

class CustomerTags extends SubTag
{
    use Concerns\FormBuilder;

    public function index()
    {
        return Customer::find($this->params->get('id'))->entry()->toAugmentedArray();
    }

    public function update()
    {
        $params = [
            'customer' => $this->params->get('id'),
        ];

        return $this->createForm(
            route('statamic.simple-commerce.customer.update'),
            $params,
            'POST'
        );
    }

    public function orders()
    {
        if ($this->params->get('from') === 'customer') {
            return Customer::find($this->params->get('customer'))
                ->orders()
                ->map(function (Order $order) {
                    return $order->entry()->toAugmentedArray();
                })
                ->toArray();
        }

        return Entry::whereCollection(config('simple-commerce.collections.orders'))
            ->where('customer', $this->params->get('customer'))
            ->map(function (EntriesEntry $entry) {
                return $entry->toAugmentedArray();
            })
            ->toArray();
    }

    public function order()
    {
        return Entry::find($this->params->get('id'));
    }
}
