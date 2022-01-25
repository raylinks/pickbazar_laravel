<?php


namespace Marvel\Database\Repositories;

use Exception;
use Illuminate\Support\Arr;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Tax;
use Marvel\Database\Models\Shipping;
use Marvel\Database\Models\Settings;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Variation;
use Marvel\Exceptions\MarvelException;
use Marvel\Traits\Wallets;

class CheckoutRepository
{
    use Wallets;

    public function verify($request)
    {
        $user = $request->user() ?? null;
        $wallet = $user->wallet ?? null;
        $settings = Settings::first();
        $minimumOrderAmount = isset($settings['options']['minimumOrderAmount']) ? $settings['options']['minimumOrderAmount'] : 0;
        $unavailable_products = $this->checkStock($request['products']);
        $amount = $this->getOrderAmount($request, $unavailable_products);
        $shipping_charge = $this->calculateShippingCharge($request, $amount);
        $tax = $this->calculateTax($request, $shipping_charge, $amount);
        $total = $amount + $tax + $shipping_charge;
        if ($total < $minimumOrderAmount) {
            throw new MarvelException('Minimum order amount is ' . $minimumOrderAmount);
        }
        return [
            'total_tax'            => $tax,
            'shipping_charge'      => $shipping_charge,
            'unavailable_products' => $unavailable_products,
            'wallet_amount' => isset($wallet->available_points) ? $wallet->available_points : 0,
            'wallet_currency' => isset($wallet->available_points) ? $this->walletPointsToCurrency($wallet->available_points) : 0
        ];
    }

    public function getOrderAmount($request, $unavailable_products)
    {
        if (count($unavailable_products)) {
            return $this->calculateAmountWithAvailable($request['products'], $unavailable_products);
        }
        return  $request['amount'];
    }

    public function calculateTax($request, $shipping_charge, $amount)
    {
        $tax_class = $this->getTaxClass($request);
        if ($tax_class) {
            return $this->getTotalTax($amount, $tax_class);
        }
        return $tax_class;
    }

    public function calculateAmountWithAvailable($products, $unavailable_products)
    {
        $amount = 0;
        foreach ($products as $product) {
            if (!in_array($product['product_id'], $unavailable_products)) {
                $amount += $product['subtotal'];
            }
        }
        return $amount;
    }

    public function calculateShippingCharge($request, $amount)
    {
        try {
            $ordered_products = $request['products'];
            $physical_products = Product::whereIn('id', Arr::pluck($ordered_products, 'product_id'))->where('is_digital', false)->get();
            if (!count($physical_products)) {
                return 0;
            }
            $settings = Settings::first();
            $class_id = $settings['options']['shippingClass'];
            if ($class_id) {
                $shipping_class = Shipping::find($class_id);
                return $this->getShippingCharge($shipping_class, $amount);
            } else {
                return $this->calculateShippingChargeByProduct($request['products']);
            }
        } catch (\Throwable $th) {
            return 0;
        }
    }

    protected function calculateShippingChargeByProduct($products)
    {
        $total_charge = 0;
        foreach ($products as $product) {
            $total_charge += $this->calculateEachProductCharge($product['product_id'], $product['subtotal']);
        }
        return $total_charge;
    }

    protected function calculateEachProductCharge($id, $amount)
    {
        $product = Product::with('shipping')->findOrFail($id);
        if (isset($product->shipping)) {
            return $this->getShippingCharge($product->shipping, $amount);
        }
        return 0;
    }

    public function checkStock($products)
    {
        $unavailable_products = [];
        foreach ($products as $product) {
            if (isset($product['variation_option_id'])) {
                $is_not_in_stock = $this->isVariationInStock($product['variation_option_id'], $product['order_quantity']);
            } else {
                $is_not_in_stock = $this->isInStock($product['product_id'], $product['order_quantity']);
            }
            if ($is_not_in_stock) {
                $unavailable_products[] = $is_not_in_stock;
            }
        }
        return $unavailable_products;
    }

    protected function isInStock($id, $order_quantity)
    {
        try {
            $product = Product::findOrFail($id);
            if ($order_quantity > $product->quantity) {
                return $id;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function isVariationInStock($variation_id, $order_quantity)
    {
        try {
            $variationOption = Variation::findOrFail($variation_id);
            if ($order_quantity > $variationOption->quantity) {
                return $variationOption->product_id;
            }
            return false;
        } catch (Exception $e) {
            return false;
        }
    }

    protected function getShippingCharge($shipping_class, $amount)
    {
        switch ($shipping_class->type) {
            case 'fixed':
                return $shipping_class->amount;
                break;
            case 'percentage':
                return ($shipping_class->amount * $amount) / 100;
                break;
            default:
                return 0;
        }
    }

    protected function getTaxClass($request)
    {
        try {
            $settings = Settings::first();

            // Get tax settings from settings
            $tax_class = $settings['options']['taxClass'];
            return Tax::find($tax_class);
        } catch (\Throwable $th) {
            return 0;
        }

        // switch ($tax_type) {
        //     case 'global':
        //         return Tax::where('is_global', '=', true)->first();
        //         break;
        //     case 'billing_address':
        //         $billing_address = $request['billing_address'];
        //         return $this->getTaxClassByAddress($billing_address);
        //         break;
        //     case 'shipping_address':
        //         $shipping_address = $request['shipping_address'];
        //         return $this->getTaxClassByAddress($shipping_address);
        //         break;
        // }
    }

    protected function getTaxClassByAddress($address)
    {
        return Tax::where('country', '=', $address['country'])
            ->orWhere('state', '=', $address['state'])
            ->orWhere('city', '=', $address['city'])
            ->orWhere('zip', '=', $address['zip'])
            ->orderBy('priority', 'asc')
            ->first();
    }

    protected function getTotalTax($amount, $tax_class)
    {
        return ($amount * $tax_class->rate) / 100;
    }
}
