<?php

namespace App\Http\Controllers;

use App\Models\Puskesmas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('puskesmas')
            ->when($request->filled('q'), function ($query) use ($request) {
                $term = '%'.$request->query('q').'%';
                $query->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhereHas('puskesmas', fn ($puskesmas) => $puskesmas->where('name', 'like', $term));
                });
            })
            ->when($request->filled('role'), fn ($query) => $query->where('role', $request->query('role')))
            ->latest()
            ->paginate(min(max((int) $request->query('per_page', 10), 5), 50))
            ->withQueryString();

        $puskesmasOptions = Puskesmas::query()->orderBy('name')->get();

        return view('users.index', compact('users', 'puskesmasOptions'));
    }

    public function store(Request $request)
    {
        $data = $this->validatedData($request);
        $data['password'] = $request->string('password')->toString();
        $data = $this->normalizeRoleData($data);

        try {
            User::query()->create($data);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'User gagal ditambahkan: '.$exception->getMessage());
        }

        return redirect()->route('users.index')->with('success', 'User berhasil ditambahkan.');
    }

    public function update(Request $request, User $user)
    {
        $data = $this->validatedData($request, $user->id);
        $data = $this->normalizeRoleData($data);

        if ($request->filled('password')) {
            $data['password'] = $request->string('password')->toString();
        }

        try {
            $user->update($data);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'User gagal diperbarui: '.$exception->getMessage());
        }

        return redirect()->route('users.index')->with('success', 'User berhasil diperbarui.');
    }

    public function destroy(Request $request, User $user)
    {
        if ($request->user()->id === $user->id) {
            return redirect()->back()->with('error', 'User yang sedang login tidak bisa dihapus.');
        }

        $user->delete();

        return redirect()->route('users.index')->with('success', 'User berhasil dihapus.');
    }

    private function validatedData(Request $request, ?int $ignoreId = null): array
    {
        $passwordRule = $ignoreId ? ['nullable', 'string', 'min:6'] : ['required', 'string', 'min:6'];

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($ignoreId)],
            'role' => ['required', Rule::in(['super_admin', 'admin_puskesmas'])],
            'puskesmas_id' => ['nullable', 'integer', 'exists:puskesmas,id'],
            'password' => $passwordRule,
        ]);
    }

    private function normalizeRoleData(array $data): array
    {
        if ($data['role'] === 'super_admin') {
            $data['puskesmas_id'] = null;
            return $data;
        }

        if (empty($data['puskesmas_id'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'puskesmas_id' => 'Admin puskesmas wajib dipasangkan ke satu puskesmas.',
            ]);
        }

        return $data;
    }
}
