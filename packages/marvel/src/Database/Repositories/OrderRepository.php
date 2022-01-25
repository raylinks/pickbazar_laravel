<?php


namespace Marvel\Database\Repositories;

use Exception;
use Ignited\LaravelOmnipay\Facades\OmnipayFacade as Omnipay;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Marvel\Database\Models\Balance;
use Marvel\Database\Models\Coupon;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\OrderedFile;
use Marvel\Database\Models\OrderWalletPoint;
use Marvel\Database\Models\Wallet;
use Marvel\Database\Models\Product;
use Marvel\Database\Models\Settings;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Variation;
use Marvel\Enums\Permission;
use Marvel\Enums\ProductType;
use Marvel\Events\OrderCreated;
use Marvel\Events\OrderReceived;
use Marvel\Exceptions\MarvelException;
use Marvel\Traits\Wallets;
use Prettus\Repository\Criteria\RequestCriteria;
use Prettus\Repository\Exceptions\RepositoryException;
use Prettus\Validator\Exceptions\ValidatorException;

class OrderRepository extends BaseRepository
{
    use Wallets;
    /**
     * @var array
     */
    protected $fieldSearchable = [
        'tracking_number' => 'like',
        'shop_id',
    ];
    /**
     * @var string[]
     */
    protected $dataArray = [
        'tracking_number',
        'customer_id',
        'shop_id',
        'status',
        'amount',
        'sales_tax',
        'paid_total',
        'total',
        'delivery_time',
        'payment_gateway',
        'discount',
        'coupon_id',
        'payment_id',
        'logistics_provider',
        'billing_address',
        'shipping_address',
        'delivery_fee',
        'customer_contact'
    ];

    public function boot()
    {
        try {
            $this->pushCriteria(app(RequestCriteria::class));
        } catch (RepositoryException $e) {
        }
    }

    /**
     * Configure the Model
     **/
    public function model()
    {
        return Order::class;
    }

    /**
     * @param $request
     * @return LengthAwarePaginator|JsonResponse|Collection|mixed
     */
    public function storeOrder($request)
    {

        $useWalletPoints = isset($request->use_wallet_points) ? $request->use_wallet_points : false;
        $request['tracking_number'] = Str::random(12);
        if ($request->user() && $request->user()->hasPermissionTo(Permission::SUPER_ADMIN) && isset($request['customer_id'])) {
            $request['customer_id'] =  $request['customer_id'];
        } else {
            $request['customer_id'] = $request->user()->id ?? null;
        }
        try {
            $user = User::findOrFail($request['customer_id']);
        } catch (Exception $e) {
            $user = null;
        }
        $discount = $this->calculateDiscount($request);
        if ($discount) {
            $request['paid_total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'] - $discount;
            $request['total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'] - $discount;
            $request['discount'] =  $discount;
        } else {
            $request['paid_total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'];
            $request['total'] = $request['amount'] + $request['sales_tax'] + $request['delivery_fee'];
        }
        $payment_gateway = $request['payment_gateway'];
        if ($useWalletPoints && $user) {
            $wallet = $user->wallet;
            $amount = null;
            if (isset($wallet->available_points)) {
                $amount = round($request['paid_total'], 2) - $this->walletPointsToCurrency($wallet->available_points);
            }

            if ($amount !== null && $amount <= 0) {
                $request['payment_gateway'] = 'FULL_WALLET_PAYMENT';
                $order = $this->createOrder($request);
                $this->storeOrderWalletPoint($request['paid_total'], $order->id);
                $this->manageWalletAmount($request['paid_total'], $user->id);
                return $order;
            }
        } else {
            $amount = round($request['paid_total'], 2);
        }
        switch ($payment_gateway) {
            case 'CASH_ON_DELIVERY':
                return $this->createCashOrder($request, $useWalletPoints, $amount, $user);
                break;
            case 'CASH':
                return $this->createCashOrder($request, $useWalletPoints, $amount, $user);
                break;
            case 'PAYPAL':
                // For default gateway no need to set gateway
                Omnipay::setGateway('PAYPAL');
                break;
            default:
                break;
        }

        $response = $this->capturePayment($request, $amount);
        if ($response->isSuccessful()) {
            $payment_id = $response->getTransactionReference();
            $request['payment_id'] = $payment_id;
            $order = $this->createOrder($request);
            if ($useWalletPoints === true && $user) {
                $this->storeOrderWalletPoint(round($request['paid_total'], 2) - $amount, $order->id);
                $this->manageWalletAmount(round($request['paid_total'], 2), $user->id);
            }
            return $order;
        } elseif ($response->isRedirect()) {
            return $response->getRedirectResponse();
        } else {
            throw new MarvelException(PAYMENT_FAILED);
        }
    }

    public function createCashOrder($request, $useWalletPoints, $amount, $user)
    {
        $order = $this->createOrder($request);
        if ($useWalletPoints === true && $user) {
            $this->storeOrderWalletPoint(round($request['paid_total'], 2) - $amount, $order->id);
            $this->manageWalletAmount(round($request['paid_total'], 2), $user->id);
        }
        return $order;
    }

    public function storeOrderWalletPoint($amount, $order_id)
    {
        if ($amount > 0) {
            OrderWalletPoint::create(['amount' =>  $amount, 'order_id' =>  $order_id]);
        }
    }


    public function manageWalletAmount($total, $customer_id)
    {
        try {
            $total = $this->currencyToWalletPoints($total);
            $wallet = Wallet::where('customer_id', $customer_id)->first();
            $available_points = $wallet->available_points - $total >= 0 ? $wallet->available_points - $total : 0;
            if ($available_points === 0) {
                $spend = $wallet->points_used + $wallet->available_points;
            } else {
                $spend = $wallet->points_used + $total;
            }
            $wallet->available_points = $available_points;
            $wallet->points_used = $spend;
            $wallet->save();
        } catch (Exception $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    /**
     * @param $request
     * @return mixed
     */
    protected function capturePayment($request, $amount)
    {
        try {
            $settings = Settings::first();
            $currency = $settings['options']['currency'];
        } catch (\Throwable $th) {
            $currency = 'USD';
        }

        $payment_info = array(
            'amount'   => $amount,
            'currency' => $currency,
        );
        if (Omnipay::getGateway() === 'STRIPE') {
            $payment_info['token'] = $request['token'];
        } else {
            $payment_info['card'] = Omnipay::creditCard($request['card']);
        }

        $transaction =
            Omnipay::purchase($payment_info);
        return $transaction->send();
    }

    /**
     * @param $request
     * @return array|LengthAwarePaginator|Collection|mixed
     */
    protected function createOrder($request)
    {
        try {
            $orderInput = $request->only($this->dataArray);
            $order = $this->create($orderInput);
            $products = $this->processProducts($request['products'], $request['customer_id'], $order);
            $order->products()->attach($products);
            $this->createChildOrder($order->id, $request);
            $this->calculateShopIncome($order);
            $order->children = $order->children;
            // event(new OrderCreated($order));
            return $order;
        } catch (Exception $e) {
            throw new MarvelException(SOMETHING_WENT_WRONG);
        }
    }

    protected function calculateShopIncome($parent_order)
    {
        foreach ($parent_order->children as  $order) {
            $balance = Balance::where('shop_id', '=', $order->shop_id)->first();
            $adminCommissionRate = $balance->admin_commission_rate;
            $shop_earnings = ($order->total * (100 - $adminCommissionRate)) / 100;
            $balance->total_earnings = $balance->total_earnings + $shop_earnings;
            $balance->current_balance = $balance->current_balance + $shop_earnings;
            $balance->save();
        }
    }

    protected function processProducts($products, $customer_id, $order)
    {
        foreach ($products as $key => $product) {
            if (!isset($product['variation_option_id'])) {
                $product['variation_option_id'] = null;
                $products[$key] = $product;
            }
            try {
                if ($order->parent_id === null) {
                    $productData = Product::with('digital_file')->findOrFail($product['product_id']);
                    if ($productData->product_type === ProductType::SIMPLE) {
                        if ($productData->is_digital) {
                            $digital_file = $productData->digital_file;
                            OrderedFile::create([
                                'purchase_key' => Str::random(16),
                                'digital_file_id' => $digital_file->id,
                                'customer_id' => $customer_id
                            ]);
                        }
                    } else if ($productData->product_type === ProductType::VARIABLE) {
                        $variation_option = Variation::with('digital_file')->findOrFail($product['variation_option_id']);
                        if ($variation_option->is_digital) {
                            $digital_file = $variation_option->digital_file;
                            OrderedFile::create([
                                'purchase_key' => Str::random(16),
                                'digital_file_id' => $digital_file->id,
                                'customer_id' => $customer_id,
                            ]);
                        }
                    }
                }
            } catch (Exception $e) {
                throw new MarvelException(config('shop.app_notice_domain') . 'ERROR.NOT_FOUND');
            }
        }
        return $products;
    }

    protected function calculateDiscount($request)
    {
        try {
            if (!isset($request['coupon_id'])) {
                return false;
            }
            $coupon = Coupon::findOrFail($request['coupon_id']);
            if (!$coupon->is_valid) {
                return false;
            }
            switch ($coupon->type) {
                case 'percentage':
                    return ($request['amount'] * $coupon->amount) / 100;
                case 'fixed':
                    return $coupon->amount;
                    break;
                case 'free_shipping':
                    return isset($request['delivery_fee']) ? $request['delivery_fee'] : false;
                    break;
            }
            return false;
        } catch (\Exception $exception) {
            return false;
        }
    }

    public function createChildOrder($id, $request)
    {
        $products = $request->products;
        $productsByShop = [];

        foreach ($products as $key => $cartProduct) {
            $product = Product::findOrFail($cartProduct['product_id']);
            $productsByShop[$product->shop_id][] = $cartProduct;
        }

        foreach ($productsByShop as $shop_id => $cartProduct) {
            $amount = array_sum(array_column($cartProduct, 'subtotal'));
            $orderInput = [
                'tracking_number' => Str::random(12),
                'shop_id' => $shop_id,
                'status' => $request->status,
                'customer_id' => $request->customer_id,
                'shipping_address' => $request->shipping_address,
                'billing_address' => $request->billing_address,
                'customer_contact' => $request->customer_contact,
                'delivery_time' => $request->delivery_time,
                'delivery_fee' => 0,
                'sales_tax' => 0,
                'discount' => 0,
                'parent_id' => $id,
                'amount' => $amount,
                'total' => $amount,
                'paid_total' => $amount,
            ];

            $order = $this->create($orderInput);
            $order->products()->attach($this->processProducts($cartProduct,  $request['customer_id'],  $order));
            // event(new OrderReceived($order));
        }
    }
}
