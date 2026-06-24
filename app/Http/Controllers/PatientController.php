<?php

namespace App\Http\Controllers;

use App\Models\RecordEkg;
use App\Support\RecordEkgService;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Throwable;

class PatientController extends Controller
{
    public function index(Request $request)
    {
        try {
            $records = RecordEkgService::filteredQuery($request)
                ->paginate(RecordEkgService::perPage($request))
                ->withQueryString();
            $dbError = null;
        } catch (QueryException $exception) {
            $records = collect();
            $dbError = $exception->getMessage();
        }

        return view('patients.index', compact('records', 'dbError'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
        ]);

        try {
            RecordEkg::create([
                'nama' => $data['nama'],
                'umur' => 0,
                'jk' => '',
                'alamat' => '',
                'tspt' => 0,
                'bpm' => 0,
                'irr' => 0,
                'irrlokal' => 0,
                'hr' => '[]',
            ]);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Data pasien gagal disimpan: '.$exception->getMessage());
        }

        return redirect()->route('patients.index')->with('success', 'Pasien berhasil ditambahkan.');
    }

    public function update(Request $request, string $patient)
    {
        $data = $request->validate([
            'nama' => ['required', 'string', 'max:255'],
            'umur' => ['nullable', 'integer', 'min:0', 'max:120'],
            'jk' => ['nullable', 'string', 'max:50'],
            'alamat' => ['nullable', 'string', 'max:255'],
            'tglrekam' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $record = RecordEkg::query()->findOrFail($patient);
            $record->update([
                'nama' => $data['nama'],
                'umur' => $data['umur'] ?? 0,
                'jk' => $data['jk'] ?? '',
                'alamat' => $data['alamat'] ?? '',
                'tglrekam' => $data['tglrekam'] ?? $record->tglrekam,
            ]);
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Data pasien gagal diperbarui: '.$exception->getMessage());
        }

        return redirect()->back()->with('success', 'Data pasien berhasil diperbarui.');
    }

    public function destroy(string $patient)
    {
        try {
            RecordEkg::query()->findOrFail($patient)->delete();
        } catch (Throwable $exception) {
            return redirect()->back()->with('error', 'Data pasien gagal dihapus: '.$exception->getMessage());
        }

        return redirect()->route('patients.index')->with('success', 'Data pasien berhasil dihapus.');
    }
}
