<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('children')->whereNull('parent_id')->get();
        return response()->json($categories);
    }

    public function store(StoreCategoryRequest $request)
    {
        $category = Category::create([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id,
        ]);

        Log::info('Categoria criada', [
            'category_id' => $category->id,
            'name' => $category->name,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json($category, 201);
    }

    public function show(Category $category)
    {
        return response()->json($category->load('children'));
    }

    public function update(UpdateCategoryRequest $request, Category $category)
    {
        $category->update([
            'name' => $request->name,
            'slug' => Str::slug($request->name),
            'parent_id' => $request->parent_id,
        ]);

        Log::info('Categoria atualizada', [
            'category_id' => $category->id,
            'name' => $category->name,
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json($category);
    }

    public function destroy(Request $request, Category $category)
    {
        $categoryData = ['id' => $category->id, 'name' => $category->name];

        $category->delete();

        Log::warning('Categoria excluída', [
            'category_id' => $categoryData['id'],
            'name' => $categoryData['name'],
            'user_id' => $request->user()->id,
            'ip' => $request->ip(),
        ]);

        return response()->json(['message' => 'Categoria excluída com sucesso.']);
    }
}