<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('per_page', 12);
        $products = Product::with(['category', 'images'])->paginate($perPage);
        return response()->json($products);
    }

    public function store(StoreProductRequest $request)
    {
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('products', 'public');
        }

        $product = Product::create([
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'price'       => $request->price,
            'stock'       => $request->stock,
            'category_id' => $request->category_id,
            'image'       => $imagePath,
        ]);

        Log::info('Produto criado', [
            'product_id' => $product->id,
            'name' => $product->name,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json($product->load(['category', 'images']), 201);
    }

    public function show(Product $product)
    {
        return response()->json($product->load(['category', 'images']));
    }

    public function update(UpdateProductRequest $request, Product $product)
    {
        $data = [
            'name'        => $request->name,
            'slug'        => Str::slug($request->name),
            'description' => $request->description,
            'price'       => $request->price,
            'stock'       => $request->stock,
            'category_id' => $request->category_id,
        ];

        if ($request->hasFile('image')) {
            if ($product->image) {
                Storage::disk('public')->delete($product->image);
            }
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product->update($data);

        Log::info('Produto atualizado', [
            'product_id' => $product->id,
            'name' => $product->name,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json($product->load(['category', 'images']));
    }

    public function destroy(Request $request, Product $product)
    {
        $productData = ['id' => $product->id, 'name' => $product->name];

        if ($product->image) {
            Storage::disk('public')->delete($product->image);
        }
        $product->delete();

        Log::warning('Produto excluído', [
            'product_id' => $productData['id'],
            'name' => $productData['name'],
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Produto excluído com sucesso.']);
    }
}