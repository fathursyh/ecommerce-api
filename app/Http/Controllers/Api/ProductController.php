<?php

namespace App\Http\Controllers\Api;

use App\ApiResponse;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductImageResource;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use DB;
use Illuminate\Http\Request;
use Storage;

class ProductController extends Controller
{
    use ApiResponse;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Product::query()->with(['category', 'images']);

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by featured
        if ($request->has('is_featured')) {
            $query->where('is_featured', $request->boolean('is_featured'));
        }

        // Filter by category
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Filter by category slug
        if ($request->has('category_slug')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category_slug);
            });
        }

        // Filter by stock availability
        if ($request->has('in_stock')) {
            if ($request->boolean('in_stock')) {
                $query->where('stock_quantity', '>', 0);
            } else {
                $query->where('stock_quantity', '<=', 0);
            }
        }

        // Price range filter
        if ($request->has('min_price')) {
            $query->where('price', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price', '<=', $request->max_price);
        }

        // Search by name, description, or SKU
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%");
            });
        }

        // Include reviews data
        if ($request->has('with_reviews') && $request->boolean('with_reviews')) {
            $query->withCount('reviews')
                ->withAvg('reviews', 'rating');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');

        // Special sorting cases
        if ($sortBy === 'price_low_high') {
            $query->orderByRaw('COALESCE(sale_price, price) ASC');
        } elseif ($sortBy === 'price_high_low') {
            $query->orderByRaw('COALESCE(sale_price, price) DESC');
        } elseif ($sortBy === 'popularity') {
            $query->withCount('reviews')->orderBy('reviews_count', 'desc');
        } else {
            $query->orderBy($sortBy, $sortOrder);
        }

        // Pagination
        $perPage = $request->get('per_page', 15);

        if ($request->has('paginate') && !$request->boolean('paginate')) {
            $products = $query->get();
            return response()->json([
                'success' => true,
                'data' => ProductResource::collection($products),
            ]);
        }

        $products = $query->paginate($perPage);

        return $this->paginatedResponse(ProductResource::collection($products));
    }


    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProductRequest $request)
    {
        DB::beginTransaction();

        try {
            $data = $request->except('images');
            $product = Product::create($data);

            // Handle image uploads
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => $index === 0, // First image is primary
                        'sort_order' => $index,
                    ]);
                }
            }

            DB::commit();

            $product->load(['category', 'images']);

            return $this->successResponse(
                new ProductResource($product),
                'Product created successfully',
                201
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to create product',
                500,
                $e->getMessage()
            );
        }
    }


    /**
     * Display the specified resource.
     */
    public function show(Request $request, $id)
    {
        $query = Product::with(['category', 'images']);

        // Include reviews
        if ($request->has('with_reviews') && $request->boolean('with_reviews')) {
            $query->withCount('reviews')
                ->withAvg('reviews', 'rating');
        }

        // Find by ID or slug
        if (is_numeric($id)) {
            $product = $query->findOrFail($id);
        } else {
            $product = $query->where('slug', $id)->firstOrFail();
        }

        return $this->successResponse(
            new ProductResource($product)
        );
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProductRequest $request, Product $product)
    {
        DB::beginTransaction();

        try {
            $data = $request->except(['images', 'remove_images']);
            $product->update($data);

            // Handle image removal
            if ($request->has('remove_images')) {
                foreach ($request->remove_images as $imageId) {
                    $image = ProductImage::where('product_id', $product->id)
                        ->where('id', $imageId)
                        ->first();

                    if ($image) {
                        Storage::disk('public')->delete($image->image_path);
                        $image->delete();
                    }
                }
            }

            // Handle new image uploads
            if ($request->hasFile('images')) {
                $currentMaxSort = ProductImage::where('product_id', $product->id)
                    ->max('sort_order') ?? -1;

                foreach ($request->file('images') as $index => $image) {
                    $path = $image->store('products', 'public');

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_path' => $path,
                        'is_primary' => ProductImage::where('product_id', $product->id)->count() === 0,
                        'sort_order' => $currentMaxSort + $index + 1,
                    ]);
                }
            }

            DB::commit();

            $product->load(['category', 'images']);

            return $this->successResponse(
                new ProductResource($product),
                'Product updated successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to update product',
                500,
                $e->getMessage()
            );
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        DB::beginTransaction();

        try {
            // Delete all product images from storage
            foreach ($product->images as $image) {
                Storage::disk('public')->delete($image->image_path);
            }

            // Soft delete the product (includes cascade delete for images)
            $product->delete();

            DB::commit();

            return $this->successResponse(
                null,
                'Product deleted successfully'
            );

        } catch (\Exception $e) {
            DB::rollBack();

            return $this->errorResponse(
                'Failed to delete product',
                500,
                $e->getMessage(),
            );
        }
    }

    /**
     * Update product image order or primary status
     * PATCH /api/v1/products/{id}/images/{imageId}
     */
    public function updateImage(Request $request, Product $product, $imageId)
    {
        $request->validate([
            'is_primary' => ['sometimes', 'boolean'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $image = ProductImage::where('product_id', $product->id)
            ->where('id', $imageId)
            ->firstOrFail();

        // If setting as primary, unset other primary images
        if ($request->has('is_primary') && $request->boolean('is_primary')) {
            ProductImage::where('product_id', $product->id)
                ->where('id', '!=', $imageId)
                ->update(['is_primary' => false]);
        }

        $image->update($request->only(['is_primary', 'sort_order']));

        return $this->successResponse(
            new ProductImageResource($image),
            'Product image updated successfully'
        );
    }
    /**
     * Get featured products
     * GET /api/v1/products/featured
     */
    public function featured(Request $request)
    {
        $limit = $request->get('limit', 8);

        $products = Product::with(['category', 'images'])
            ->where('is_active', true)
            ->where('is_featured', true)
            ->where('stock_quantity', '>', 0)
            ->limit($limit)
            ->get();

        return $this->successResponse(
            ProductResource::collection($products),
        );
    }
    /**
     * Get related products (same category)
     * GET /api/v1/products/{id}/related
     */
    public function related(Product $product, Request $request)
    {
        $limit = $request->get('limit', 4);

        $relatedProducts = Product::with(['category', 'images'])
            ->where('category_id', $product->category_id)
            ->where('id', '!=', $product->id)
            ->where('is_active', true)
            ->where('stock_quantity', '>', 0)
            ->inRandomOrder()
            ->limit($limit)
            ->get();

        return $this->successResponse(
            ProductResource::collection($relatedProducts),
        );
    }
}
