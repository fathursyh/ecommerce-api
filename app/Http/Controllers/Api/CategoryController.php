<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    /**
     * Display a listing of categories
     * GET /api/v1/categories
     */
    public function index(Request $request): JsonResponse
    {
        $query = Category::query();

        // Filter by active status
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Filter by parent (get only root categories)
        if ($request->has('root') && $request->boolean('root')) {
            $query->whereNull('parent_id');
        }

        // Filter by parent_id
        if ($request->has('parent_id')) {
            $query->where('parent_id', $request->parent_id);
        }

        // Include children
        if ($request->has('with_children') && $request->boolean('with_children')) {
            $query->with('children');
        }

        // Include products count
        if ($request->has('with_products_count') && $request->boolean('with_products_count')) {
            $query->withCount('products');
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'sort_order');
        $sortOrder = $request->get('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);

        if ($request->has('paginate') && !$request->boolean('paginate')) {
            $categories = $query->get();
            return response()->json([
                'success' => true,
                'data' => CategoryResource::collection($categories),
            ]);
        }

        $categories = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
            'meta' => [
                'current_page' => $categories->currentPage(),
                'from' => $categories->firstItem(),
                'last_page' => $categories->lastPage(),
                'per_page' => $categories->perPage(),
                'to' => $categories->lastItem(),
                'total' => $categories->total(),
            ],
        ]);
    }
    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category = Category::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Category created successfully',
            'data' => new CategoryResource($category),
        ], 201);
    }

    /**
     * Display the specified category
     * GET /api/v1/categories/{id}
     */
    public function show(Request $request, $id): JsonResponse
    {
        $query = Category::query();

        // Include children
        if ($request->has('with_children') && $request->boolean('with_children')) {
            $query->with('children');
        }

        // Include parent
        if ($request->has('with_parent') && $request->boolean('with_parent')) {
            $query->with('parent');
        }

        // Include products count
        if ($request->has('with_products_count') && $request->boolean('with_products_count')) {
            $query->withCount('products');
        }

        // Include products
        if ($request->has('with_products') && $request->boolean('with_products')) {
            $query->with([
                'products' => function ($q) use ($request) {
                    $q->where('is_active', true);

                    // Limit products if specified
                    if ($request->has('products_limit')) {
                        $q->limit($request->products_limit);
                    }
                }
            ]);
        }

        $category = $query->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Update the specified category
     * PUT/PATCH /api/v1/categories/{id}
     */
    public function update(UpdateCategoryRequest $request, Category $category): JsonResponse
    {
        $data = $request->validated();

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image
            if ($category->image) {
                Storage::disk('public')->delete($category->image);
            }
            $data['image'] = $request->file('image')->store('categories', 'public');
        }

        $category->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Category updated successfully',
            'data' => new CategoryResource($category),
        ]);
    }

    /**
     * Remove the specified category
     * DELETE /api/v1/categories/{id}
     */
    public function destroy(Category $category): JsonResponse
    {
        // Check if category has products
        if ($category->products()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with existing products',
            ], 422);
        }

        // Check if category has children
        if ($category->children()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete category with child categories',
            ], 422);
        }

        // Delete image if exists
        if ($category->image) {
            Storage::disk('public')->delete($category->image);
        }

        $category->delete();

        return response()->json([
            'success' => true,
            'message' => 'Category deleted successfully',
        ]);
    }
    public function tree(Request $request): JsonResponse
    {
        $query = Category::whereNull('parent_id')
            ->with('children')
            ->orderBy('sort_order');

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('with_products_count') && $request->boolean('with_products_count')) {
            $query->withCount('products');
        }

        $categories = $query->get();

        return response()->json([
            'success' => true,
            'data' => CategoryResource::collection($categories),
        ]);
    }
}
