<?php

namespace DoubleThreeDigital\SimpleCommerce\Http\Controllers\Actions;

use DoubleThreeDigital\SimpleCommerce\Events\OrderPaid;
use DoubleThreeDigital\SimpleCommerce\Events\OrderSuccessful;
use DoubleThreeDigital\SimpleCommerce\Events\VariantLowStock;
use DoubleThreeDigital\SimpleCommerce\Events\VariantOutOfStock;
use DoubleThreeDigital\SimpleCommerce\Http\Requests\CheckoutRequest;
use DoubleThreeDigital\SimpleCommerce\Models\Country;
use DoubleThreeDigital\SimpleCommerce\Models\LineItem;
use DoubleThreeDigital\SimpleCommerce\Models\Order;
use DoubleThreeDigital\SimpleCommerce\Models\State;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Statamic\Stache\Stache;

class CheckoutController
{
    public function store(CheckoutRequest $request)
    {
        $order = Order::where('uuid', Session::get(config('simple-commerce.cart_session_key')))->first();

        $payment = (new $request->gateway)->completePurchase($request->all());

        if ($payment === true) {
            $order->update([
                'is_paid' => true,
            ]);

            Event::dispatch(new OrderPaid($order));
        }

        if (Auth::guest()) {
            $customerModel = config('simple-commerce.customers.model');
            $customerModel = new $customerModel();

            $customer = $customerModel::where('email', $request->email)->first();

            if ($customer === null) {
                $customer = new $customerModel();
                $fields = $customerModel->fields;

                collect($request->all())
                    ->reject(function ($value, $key) use ($fields) {
                        return !in_array($key, $fields);
                    })
                    ->each(function ($value, $key) use ($customer) {
                        $customer->{$key} = $value;
                    })
                    ->toArray();

                if (! $customer->password) {
                    $customer->password = Hash::make(uniqid().'ssspppp');
                }

                $customer->save();
            }
        } else {
            $customer = Auth::user();
        }

        $order->update([
            'customer_id' => $customer->id,
        ]);

        $order->billingAddress->update([
            'customer_id' => $customer->id,
        ]);

        $order->shippingAddress->update([
            'customer_id' => $customer->id,
        ]);

        collect($order->lineItems)
            ->reject(function (LineItem $lineItem) {
                if ($lineItem->variant->unlimited_stock) {
                    return true;
                }

                return false;
            })
            ->each(function (LineItem $lineItem) {
                $lineItem->variant()->update([
                    'stock' => ($lineItem->variant->stock - $lineItem->quantity),
                ]);

                if ($lineItem->variant->stock <= config('simple-commerce.low_stock_counter')) {
                    Event::dispatch(new VariantLowStock($lineItem->variant));
                }

                if ($lineItem->variant->stock === 0) {
                    Event::dispatch(new VariantOutOfStock($lineItem->variant));
                }
            });

        $order->update([
            'is_completed' => true,
        ]);

        Event::dispatch(new OrderSuccessful($order));
        Session::remove(config('simple-commerce.cart_session_key'));

        return $request->_redirect ? redirect($request->_redirect) : back();
    }
}
