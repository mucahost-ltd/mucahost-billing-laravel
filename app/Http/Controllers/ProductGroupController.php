<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveProductGroupRequest;
use App\Models\ProductGroup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ProductGroupController extends Controller
{
    public function store(SaveProductGroupRequest $request): RedirectResponse
    {
        ProductGroup::create($request->validated());

        return back();
    }

    public function update(SaveProductGroupRequest $request, ProductGroup $group): RedirectResponse
    {
        $group->update($request->validated());

        return back();
    }

    public function destroy(ProductGroup $group): RedirectResponse
    {
        DB::transaction(function () use ($group) {
            $group = ProductGroup::query()->lockForUpdate()->findOrFail($group->id);
            if ($group->products()->exists()) {
                throw ValidationException::withMessages(['group' => 'Move or remove this group’s products before deleting the group.']);
            }
            $group->delete();
        });

        return back();
    }
}
