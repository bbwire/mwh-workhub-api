<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\UpdateOrganizationSettingRequest;
use App\Http\Resources\Api\V1\OrganizationSettingResource;
use App\Models\OrganizationSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class OrganizationSettingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user() !== null, 401);

        return response()->json([
            'data' => OrganizationSettingResource::make(OrganizationSetting::current()),
        ]);
    }

    public function update(UpdateOrganizationSettingRequest $request): JsonResponse
    {
        $settings = OrganizationSetting::current();
        $settings->fill($request->validated());
        $settings->save();

        return response()->json([
            'data' => OrganizationSettingResource::make($settings->fresh()),
        ]);
    }

    public function uploadLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('settings.manage') || $user->can('admin.access')), 403);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $settings = OrganizationSetting::current();
        $file = $request->file('file');

        if ($settings->logo_path && $settings->logo_disk) {
            Storage::disk($settings->logo_disk)->delete($settings->logo_path);
        }

        $path = $file->store('organization', 'public');

        $settings->forceFill([
            'logo_path' => $path,
            'logo_disk' => 'public',
        ])->save();

        return response()->json([
            'data' => OrganizationSettingResource::make($settings->fresh()),
        ]);
    }

    public function destroyLogo(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('settings.manage') || $user->can('admin.access')), 403);

        $settings = OrganizationSetting::current();

        if ($settings->logo_path && $settings->logo_disk) {
            Storage::disk($settings->logo_disk)->delete($settings->logo_path);
        }

        $settings->forceFill([
            'logo_path' => null,
            'logo_disk' => null,
        ])->save();

        return response()->json([
            'data' => OrganizationSettingResource::make($settings->fresh()),
        ]);
    }
}
