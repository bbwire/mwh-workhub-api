<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Auth\UpdatePasswordRequest;
use App\Http\Requests\Api\V1\Auth\UpdateProfileRequest;
use App\Http\Resources\Api\V1\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function update(UpdateProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->fill($request->only(['name', 'phone']));
        $user->save();
        $user->load(['roles', 'permissions', 'departments', 'workspaces']);

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }

    public function updatePassword(UpdatePasswordRequest $request): JsonResponse
    {
        $user = $request->user();
        $user->password = $request->validated('password');
        $user->save();

        return response()->json([
            'message' => 'Password updated.',
        ]);
    }

    public function uploadAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $request->validate([
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,gif,webp', 'max:2048'],
        ]);

        $file = $request->file('file');
        $user->deleteAvatarFile();

        $path = $file->store('avatars', 'public');

        $user->forceFill([
            'avatar_path' => $path,
            'avatar_disk' => 'public',
        ])->save();

        $user->load(['roles', 'permissions', 'departments', 'workspaces']);

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }

    public function destroyAvatar(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 401);

        $user->deleteAvatarFile();
        $user->forceFill([
            'avatar_path' => null,
            'avatar_disk' => null,
        ])->save();

        $user->load(['roles', 'permissions', 'departments', 'workspaces']);

        return response()->json([
            'data' => UserResource::make($user),
        ]);
    }
}
