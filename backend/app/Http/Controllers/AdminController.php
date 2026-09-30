<?php

namespace App\Http\Controllers;

use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('role');

        if ($request->filled('email')) {
            $query->where('email', 'like', '%'.$request->input('email').'%');
        }

        $users = $query->paginate($request->input('per_page', 10));

        return response()->json($users);
    }

    // Добавим метод для получения списка ролей для фронтенда
    public function getRoles()
    {
        return response()->json(Role::all());
    }

    public function changeRole(Request $request, $id)
    {
        // Проверяем, что переданный role_id реально существует в таблице roles
        $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $user = User::findOrFail($id);
        $user->role_id = $request->input('role_id');
        $user->save();

        // Подгружаем обновленную роль, чтобы вернуть её на фронт
        $user->load('role');

        return response()->json([
            'message' => 'Роль успешно обновлена',
            'user' => $user,
        ]);
    }
}
