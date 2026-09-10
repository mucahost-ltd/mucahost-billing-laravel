<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveSupportDepartmentRequest;
use App\Models\SupportDepartment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SupportDepartmentController extends Controller
{
    public function store(SaveSupportDepartmentRequest $request): RedirectResponse
    {
        SupportDepartment::create($request->validated());

        return back();
    }

    public function update(SaveSupportDepartmentRequest $request, SupportDepartment $department): RedirectResponse
    {
        $department->update($request->validated());

        return back();
    }

    public function destroy(SupportDepartment $department): RedirectResponse
    {
        DB::transaction(function () use ($department): void {
            $department = SupportDepartment::query()->lockForUpdate()->findOrFail($department->id);

            if ($department->tickets()->exists()) {
                throw ValidationException::withMessages(['department' => 'Move or close tickets in this department before deleting it.']);
            }

            $department->delete();
        });

        return back();
    }
}
