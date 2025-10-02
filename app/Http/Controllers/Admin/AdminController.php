<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Support\Number;
use Illuminate\View\View;

class AdminController extends Controller
{
    //
    public function index(): View
    {
        $userCount = User::count();
        $userSuspendedCount = User::where('status', 'suspended')->count();
        $userBannedCount = User::where('status', 'banned')->count();

        return view('admin.index')
            ->with('userCount', Number::format($userCount))
            ->with('userSuspendedCount', Number::format($userSuspendedCount));
    }

    //
    public function users(): View
    {
        $users = User::paginate(10);

        return view('admin.users.index')
            ->with('users', $users);
    }
}
