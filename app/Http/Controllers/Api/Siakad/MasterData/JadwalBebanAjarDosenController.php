<?php

namespace App\Http\Controllers\Api\Siakad\MasterData;

use Illuminate\Http\Request;
use App\Models\MasterData\Dosen;
use App\Models\MasterData\Ruang;
use App\Models\MasterData\KelasMK;
use App\Http\Controllers\Controller;
use App\Models\MasterData\DosenKelasMk;
use App\Models\MasterData\JadwalKuliah;
use App\Models\MasterData\BebanAjarDosen;

class JadwalBebanAjarDosenController extends Controller
{
    public function createJadwalBebanajarDosen($dosenmk)
    {
        try {
            // Ambil data dosen_kelas_mk berdasarkan ID
            $dosen_mk = DosenKelasMk::with([
                'dosen:id,nama_dosen,nup',
                'kelasMk.mataKuliah:id,nama_mk,sks',
                'kelasMk.kelasPararel:id,id_prodi,nama_kelas',
                'kelasMk.kelasPararel.prodi:id,nama_prodi',
                'kelasMk.semester:id,nama_semester',
                'kelasMk.jenisKelas:id,nama_kelas',
            ])->findOrFail($dosenmk);

            // Ambil semua ruang yang tersedia
            $ruangs = Ruang::select(['id', 'nama_ruang', 'jenis_ruang'])->get();

            // Ambil semua hari dalam seminggu
            $hariOptions = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

            // Generate jam mulai dan selesai (contoh: dari 07:00 - 21:00)
            $jamOptions = [];
            for ($hour = 7; $hour <= 21; $hour++) {
                for ($minute = 0; $minute < 60; $minute += 30) {
                    $jam = sprintf('%02d:%02d', $hour, $minute);
                    $jamOptions[] = $jam;
                }
            }

            // Cek apakah sudah ada jadwal untuk dosen_kelas_mk ini
            $existingJadwal = JadwalKuliah::where('id_kelas_mk', $dosen_mk->id_kelas_mk)
                ->where('id_dosen', $dosen_mk->id_dosen)
                ->with(['ruang:id,nama_ruang,jenis_ruang'])
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'dosen_mk' => $dosen_mk,
                    'ruangs' => $ruangs,
                    'hari_options' => $hariOptions,
                    'jam_options' => $jamOptions,
                    'existing_jadwal' => $existingJadwal // Jika ada jadwal sebelumnya
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function getJadwalDetail($dosenmk)
    {
        try {
            $dosen_mk = DosenKelasMk::with([
                'dosen:id,nama_dosen,nup',
                'kelasMk.mataKuliah:id,nama_mk,sks',
                'kelasMk.kelasPararel:id,id_prodi,nama_kelas',
                'kelasMk.kelasPararel.prodi:id,nama_prodi',
                'kelasMk.semester:id,nama_semester',
                'kelasMk.jenisKelas:id,nama_kelas',
            ])->findOrFail($dosenmk);

            $ruang = Ruang::select(['id', 'nama_ruang', 'jenis_ruang'])->get();

            $jadwal = JadwalKuliah::where('id_kelas_mk', $dosen_mk->id_kelas_mk)
                ->where('id_dosen', $dosen_mk->id_dosen)
                ->first();

            $bebanajar = BebanAjarDosen::where('id_kelas_mk', $dosen_mk->id_kelas_mk)
                ->where('id_dosen', $dosen_mk->id_dosen)
                ->first();

            return response()->json([
                'status' => 'success',
                'data' => [
                    'dosen_mk' => $dosen_mk,
                    'jadwal' => $jadwal,
                    'bebanajar' => $bebanajar,
                    'ruang' => $ruang,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to retrieve data.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function storeOrUpdateJadwalKuliah(Request $request, $dosenmk = null)
    {
        try {
            $request->validate([
                'id_kelas_mk' => 'required|exists:kelas_mk,id',
                'id_dosen' => 'required|exists:dosen,id',
                'id_ruang' => 'required|exists:ruang,id',
                'id_semester' => 'required|exists:semester,id',
                'hari' => 'required|in:Senin,Selasa,Rabu,Kamis,Jumat,Sabtu,Minggu',
                'jam_mulai' => 'required|date_format:H:i',
                'jam_selesai' => 'required|date_format:H:i|after:jam_mulai',
            ]);

            // Cek apakah sudah ada jadwal untuk kombinasi ini
            $existingJadwal = JadwalKuliah::where('id_kelas_mk', $request->id_kelas_mk)
                ->where('id_dosen', $request->id_dosen)
                ->first();

            // Cek konflik ruang (kecuali jika sedang update dan ruang sama)
            $conflictQuery = JadwalKuliah::where('id_ruang', $request->id_ruang)
                ->where('hari', $request->hari)
                ->where('id', '!=', $existingJadwal ? $existingJadwal->id : null); // Exclude current record if updating

            $existingSchedule = $conflictQuery->where(function ($query) use ($request) {
                $query->whereBetween('jam_mulai', [$request->jam_mulai, $request->jam_selesai])
                    ->orWhereBetween('jam_selesai', [$request->jam_mulai, $request->jam_selesai])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('jam_mulai', '<=', $request->jam_mulai)
                            ->where('jam_selesai', '>=', $request->jam_selesai);
                    });
            })->first();

            if ($existingSchedule) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Ruang sudah terisi pada waktu tersebut.'
                ], 409);
            }

            // Cek konflik dosen (kecuali jika sedang update dan dosen sama)
            $conflictDosenQuery = JadwalKuliah::where('id_dosen', $request->id_dosen)
                ->where('hari', $request->hari)
                ->where('id', '!=', $existingJadwal ? $existingJadwal->id : null); // Exclude current record if updating

            $existingDosenSchedule = $conflictDosenQuery->where(function ($query) use ($request) {
                $query->whereBetween('jam_mulai', [$request->jam_mulai, $request->jam_selesai])
                    ->orWhereBetween('jam_selesai', [$request->jam_mulai, $request->jam_selesai])
                    ->orWhere(function ($q) use ($request) {
                        $q->where('jam_mulai', '<=', $request->jam_mulai)
                            ->where('jam_selesai', '>=', $request->jam_selesai);
                    });
            })->first();

            if ($existingDosenSchedule) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Dosen sudah memiliki jadwal pada waktu tersebut.'
                ], 409);
            }

            if ($existingJadwal) {
                // UPDATE existing schedule
                $existingJadwal->update([
                    'id_kelas_mk' => $request->id_kelas_mk,
                    'id_dosen' => $request->id_dosen,
                    'id_ruang' => $request->id_ruang,
                    'id_semester' => $request->id_semester,
                    'hari' => $request->hari,
                    'jam_mulai' => $request->jam_mulai,
                    'jam_selesai' => $request->jam_selesai,
                ]);

                // Update beban ajar
                $this->updateBebanAjarDosen($request->id_dosen, $request->id_kelas_mk, $request->id_semester);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Jadwal berhasil diperbarui',
                    'data' => $existingJadwal->fresh()
                ], 200);
            } else {
                // CREATE new schedule
                $jadwal = JadwalKuliah::create([
                    'id_kelas_mk' => $request->id_kelas_mk,
                    'id_dosen' => $request->id_dosen,
                    'id_ruang' => $request->id_ruang,
                    'id_semester' => $request->id_semester,
                    'hari' => $request->hari,
                    'jam_mulai' => $request->jam_mulai,
                    'jam_selesai' => $request->jam_selesai,
                ]);

                // Update atau buat beban ajar dosen
                $this->updateBebanAjarDosen($request->id_dosen, $request->id_kelas_mk, $request->id_semester);

                return response()->json([
                    'status' => 'success',
                    'message' => 'Jadwal berhasil dibuat',
                    'data' => $jadwal
                ], 201);
            }
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menyimpan jadwal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    private function updateBebanAjarDosen($id_dosen, $id_kelas_mk, $id_semester)
    {
        $kelasMk = KelasMk::find($id_kelas_mk);
        if (!$kelasMk) return;

        // Hitung jumlah jam per minggu berdasarkan SKS
        $jumlahJam = $kelasMk->mataKuliah->sks * 8; // Misal: 1 SKS = 8 jam per semester

        // Periksa apakah sudah ada beban ajar
        $bebanAjar = BebanAjarDosen::where('id_dosen', $id_dosen)
            ->where('id_kelas_mk', $id_kelas_mk)
            ->where('id_semester', $id_semester)
            ->first();

        if ($bebanAjar) {
            $bebanAjar->update(['jumlah_jam' => $jumlahJam]);
        } else {
            BebanAjarDosen::create([
                'id_dosen' => $id_dosen,
                'id_kelas_mk' => $id_kelas_mk,
                'id_semester' => $id_semester,
                'jumlah_jam' => $jumlahJam,
            ]);
        }
    }

    // Method untuk delete jadwal
    public function deleteJadwal($id)
    {
        try {
            $jadwal = JadwalKuliah::findOrFail($id);
            $jadwal->delete();

            // Hapus juga beban ajar terkait jika perlu
            BebanAjarDosen::where('id_dosen', $jadwal->id_dosen)
                ->where('id_kelas_mk', $jadwal->id_kelas_mk)
                ->where('id_semester', $jadwal->id_semester)
                ->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Jadwal berhasil dihapus'
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Gagal menghapus jadwal.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
