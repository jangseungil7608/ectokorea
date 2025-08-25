<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 5);
        
        // 現在 ログインした ユーザーの 商品だけ 取得
        return auth('api')->user()->products()
            ->orderBy('created_at', 'desc')
            ->paginate($perPage);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'description' => 'nullable|string',
            'price' => 'required|integer',
        ]);
        
        // ログインした ユーザーの IDを 追加
        $validated['user_id'] = auth('api')->id();
        
        return response()->json(Product::create($validated));
    }

    /**
     * Display the specified resource.
     */
    public function show(Product $product)
    {
        // ユーザー 所有権 確認
        if ($product->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        return response()->json($product);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Product $product)
    {
        // ユーザー 所有権 確認
        if ($product->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $validated = $request->validate([
            'name' => 'string',
            'description' => 'nullable|string',
            'price' => 'integer',
        ]);
        
        $product->update($validated);
        return response()->json($product);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Product $product)
    {
        // ユーザー 所有権 確認
        if ($product->user_id !== auth('api')->id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }
        
        $product->delete();
        return response()->json([
            'message' => 'Product deleted successfully.',
        ]);
    }
}
