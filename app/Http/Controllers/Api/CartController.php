<?php

namespace App\Http\Controllers\api;

use App\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\AddToCartRequest;
use App\Http\Requests\SyncCartRequest;
use App\Http\Requests\UpdateCartItemRequest;
use App\Http\Resources\CartResource;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use DB;
use Illuminate\Http\Request;
use Str;

class CartController extends Controller
{
    use ApiResponse;

    private function getOrCreateCart(Request $request)
    {
        if ($request->user()) {
            return Cart::firstOrCreate(
                ['user_id' => $request->user()->id],
                ['session_id' => null]
            );
        }

        $sessionId = $request->header('X-Session-ID') ?? Str::uuid()->toString();

        return Cart::firstOrCreate(
            ['session_id' => $sessionId],
            ['user_id' => null]
        );
    }

    /**
     * GET /api/v1/cart
     */
    public function index(Request $request)
    {
        try {
            $sessionId = $request->header('X-Session-ID') ?? Str::uuid()->toString();
            $cart = Cart::where('session_id', $sessionId)->firstOrFail();

            $cart->load(['items.product.images']);

            return $this->successResponse(
                new CartResource($cart),
            );
        } catch (\Exception $e) {
            return $this->errorResponse('Resource not found', 404);
        }
    }

    /**
     * POST /api/v1/cart/items
     */
    public function addItem(AddToCartRequest $request)
    {
        DB::beginTransaction();

        try {
            $cart = $this->getOrCreateCart($request);
            $product = Product::findOrFail($request->product_id);

            if ($product->stock_quantity < $request->quantity) {
                return $this->errorResponse(
                    'Insufficient stock available',
                    422,
                    ['available_stock' => $product->stock_quantity]
                );
            }

            $cartItem = CartItem::where('cart_id', $cart->id)
                ->where('product_id', $product->id)
                ->first();

            if ($cartItem) {
                $newQuantity = $cartItem->quantity + $request->quantity;

                if ($product->stock_quantity < $newQuantity) {
                    return $this->errorResponse(
                        'Cannot add more items. Insufficient stock.',
                        422,
                        [
                            'available_stock' => $product->stock_quantity,
                            'current_cart_quantity' => $cartItem->quantity,
                        ]
                    );
                }

                $cartItem->update([
                    'quantity' => $newQuantity,
                    'price' => $product->effective_price,
                ]);
            } else {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => $request->quantity,
                    'price' => $product->effective_price,
                ]);
            }

            DB::commit();
            $cart->load(['items.product.images']);

            return $this->successResponse(
                new CartResource($cart),
                'Item added to cart successfully',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to add item to cart', 500, $e->getMessage());
        }
    }

    /**
     * PUT /api/v1/cart/items/{cartItem}
     */
    public function updateItem(UpdateCartItemRequest $request, CartItem $cartItem)
    {
        DB::beginTransaction();

        try {
            $cart = $this->getOrCreateCart($request);

            if ($cartItem->cart_id !== $cart->id) {
                return $this->errorResponse('Cart item not found', 404);
            }

            $product = $cartItem->product;

            if ($product->stock_quantity < $request->quantity) {
                return $this->errorResponse(
                    'Insufficient stock available',
                    422,
                    ['available_stock' => $product->stock_quantity]
                );
            }

            $cartItem->update([
                'quantity' => $request->quantity,
                'price' => $product->effective_price,
            ]);

            DB::commit();

            $cart->load(['items.product.images']);

            return $this->successResponse(
                new CartResource($cart),
                'Cart item updated successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to update cart item', 500, $e->getMessage());
        }
    }

    /**
     * DELETE /api/v1/cart/items/{cartItem}
     */
    public function removeItem(Request $request, CartItem $cartItem)
    {
        $cart = $this->getOrCreateCart($request);

        if ($cartItem->cart_id !== $cart->id) {
            return $this->errorResponse('Cart item not found', 404);
        }

        $cartItem->delete();
        $cart->load(['items.product.images']);

        return $this->successResponse(
            new CartResource($cart),
            'Item removed from cart'
        );
    }

    /**
     * DELETE /api/v1/cart
     */
    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request);
        $cart->items()->delete();

        return $this->successResponse(
            new CartResource($cart->fresh()),
            'Cart cleared successfully'
        );
    }

    /**
     * POST /api/v1/cart/sync
     */
    public function sync(SyncCartRequest $request)
    {
        DB::beginTransaction();

        try {
            $cart = $this->getOrCreateCart($request);

            $cart->items()->delete();

            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);

                if (!$product || $product->stock_quantity < $item['quantity']) {
                    continue;
                }

                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $product->id,
                    'quantity' => min($item['quantity'], $product->stock_quantity),
                    'price' => $product->effective_price,
                ]);
            }

            DB::commit();
            $cart->load(['items.product.images']);

            return $this->successResponse(
                new CartResource($cart),
                'Cart synced successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to sync cart', 500, $e->getMessage());
        }
    }

    /**
     * POST /api/v1/cart/merge
     */
    public function merge(Request $request)
    {
        $request->validate([
            'session_id' => ['required', 'string'],
        ]);

        if (!$request->user()) {
            return $this->errorResponse('User must be authenticated', 401);
        }

        DB::beginTransaction();

        try {
            $guestCart = Cart::where('session_id', $request->session_id)
                ->with('items')
                ->first();

            if (!$guestCart || $guestCart->items->isEmpty()) {
                $userCart = $this->getOrCreateCart($request);
                $userCart->load(['items.product.images']);

                return $this->successResponse(
                    new CartResource($userCart),
                    'No guest cart to merge'
                );
            }

            $userCart = Cart::firstOrCreate(
                ['user_id' => $request->user()->id],
                ['session_id' => null]
            );

            foreach ($guestCart->items as $guestItem) {
                $product = $guestItem->product;

                $userItem = CartItem::where('cart_id', $userCart->id)
                    ->where('product_id', $guestItem->product_id)
                    ->first();

                if ($userItem) {
                    $newQuantity = min(
                        $userItem->quantity + $guestItem->quantity,
                        $product->stock_quantity
                    );

                    $userItem->update([
                        'quantity' => $newQuantity,
                        'price' => $product->effective_price,
                    ]);
                } else {
                    CartItem::create([
                        'cart_id' => $userCart->id,
                        'product_id' => $guestItem->product_id,
                        'quantity' => min($guestItem->quantity, $product->stock_quantity),
                        'price' => $product->effective_price,
                    ]);
                }
            }

            $guestCart->items()->delete();
            $guestCart->delete();

            DB::commit();

            $userCart->load(['items.product.images']);

            return $this->successResponse(
                new CartResource($userCart),
                'Carts merged successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->errorResponse('Failed to merge carts', 500, $e->getMessage());
        }
    }
}
