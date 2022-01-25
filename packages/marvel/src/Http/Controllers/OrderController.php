<?php

namespace Marvel\Http\Controllers;

use Barryvdh\DomPDF\Facade as PDF;
use Exception;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Marvel\Database\Models\Balance;
use Marvel\Database\Models\Order;
use Marvel\Database\Models\Settings;
use Marvel\Database\Models\Shop;
use Marvel\Database\Models\User;
use Marvel\Database\Models\Wallet;
use Marvel\Database\Models\Refund;
use Marvel\Database\Repositories\OrderRepository;
use Marvel\Enums\Permission;
use Marvel\Events\OrderCreated;
use Marvel\Exceptions\MarvelException;
use Marvel\Http\Requests\OrderCreateRequest;
use Marvel\Http\Requests\OrderUpdateRequest;
use Marvel\Traits\Wallets;

class OrderController extends CoreController
{
    use Wallets;
    public $repository;

    public function __construct(OrderRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * Display a listing of the resource.
     *
     * @param Request $request
     * @return Collection|Order[]
     */
    public function index(Request $request)
    {
        $limit = $request->limit ?   $request->limit : 10;
        return $this->fetchOrders($request)->paginate($limit)->withQueryString();
    }

    public function fetchOrders(Request $request)
    {
        $user = $request->user();
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN) && (!isset($request->shop_id) || $request->shop_id === 'undefined')) {
            return $this->repository->with('children')->where('id', '!=', null)->where('parent_id', '=', null); //->paginate($limit);
        } else if ($this->repository->hasPermission($user, $request->shop_id)) {
            if ($user && $user->hasPermissionTo(Permission::STORE_OWNER)) {
                return $this->repository->with('children')->where('shop_id', '=', $request->shop_id)->where('parent_id', '!=', null); //->paginate($limit);
            } elseif ($user && $user->hasPermissionTo(Permission::STAFF)) {
                return $this->repository->with('children')->where('shop_id', '=', $request->shop_id)->where('parent_id', '!=', null); //->paginate($limit);
            }
        } else {
            return $this->repository->with('children')->where('customer_id', '=', $user->id)->where('parent_id', '=', null); //->paginate($limit);
        }
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param OrderCreateRequest $request
     * @return LengthAwarePaginator|\Illuminate\Support\Collection|mixed
     */
    public function store(OrderCreateRequest $request)
    {
        return $this->repository->storeOrder($request);
    }

    /**
     * Display the specified resource.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function show(Request $request, $params)
    {
        $user = $request->user() ?? null;
        try {
            $order = $this->repository->with(['products', 'status', 'children.shop', 'wallet_point'])->where('id', $params)->orWhere('tracking_number', $params)->firstOrFail();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
        if (!$order->customer_id) {
            return $order;
        }
        if ($user && $user->hasPermissionTo(Permission::SUPER_ADMIN)) {
            return $order;
        } elseif (isset($order->shop_id)) {
            if ($user && ($this->repository->hasPermission($user, $order->shop_id) || $user->id == $order->customer_id)) {
                return $order;
            }
        } elseif ($user && $user->id == $order->customer_id) {
            return $order;
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }
    public function findByTrackingNumber(Request $request, $tracking_number)
    {
        $user = $request->user() ?? null;
        try {
            $order = $this->repository->with(['products', 'status', 'children.shop', 'wallet_point'])->findOneByFieldOrFail('tracking_number', $tracking_number);
            if ($order->customer_id === null) {
                return $order;
            }
            if ($user && ($user->id === $order->customer_id || $user->can('super_admin'))) {
                return $order;
            } else {
                throw new MarvelException(NOT_AUTHORIZED);
            }
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }

    /**
     * Update the specified resource in storage.
     *
     * @param OrderUpdateRequest $request
     * @param int $id
     * @return JsonResponse
     */
    public function update(OrderUpdateRequest $request, $id)
    {
        $request->id = $id;
        return $this->updateOrder($request);
    }


    public function updateOrder(Request $request)
    {
        try {
            $order = $this->repository->findOrFail($request->id);
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
        $user = $request->user();
        if (isset($order->shop_id)) {
            if ($this->repository->hasPermission($user, $order->shop_id)) {
                return $this->changeOrderStatus($order, $request->status);
            }
        } else if ($user->hasPermissionTo(Permission::SUPER_ADMIN)) {
            return $this->changeOrderStatus($order, $request->status);
        } else {
            throw new MarvelException(NOT_AUTHORIZED);
        }
    }

    public function changeOrderStatus($order, $status)
    {
        $order->status = $status;
        $order->save();
        try {
            $children = json_decode($order->children);
        } catch (\Throwable $th) {
            $children = $order->children;
        }
        if (is_array($children) && count($children)) {
            foreach ($order->children as $child_order) {
                $child_order->status = $status;
                $child_order->save();
            }
        }
        return $order;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param int $id
     * @return JsonResponse
     */
    public function destroy($id)
    {
        try {
            return $this->repository->findOrFail($id)->delete();
        } catch (\Exception $e) {
            throw new MarvelException(NOT_FOUND);
        }
    }
}
