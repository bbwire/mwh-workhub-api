<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

class AdminNotifiableUsers
{
    /**
     * Active users who should receive org-wide task due/overdue alerts.
     *
     * @return Collection<int, User>
     */
    public function all(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->permission('admin.access')
            ->get();
    }
}
