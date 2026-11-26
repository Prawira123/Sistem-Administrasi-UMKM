<?php

namespace App\Http\Requests\StokOpname;

use App\Models\StokOpname;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Input hasil hitung fisik. Format: detail[id_detail_opname][stok_fisik|keterangan].
 * Keterangan wajib bila stok_fisik ≠ stok_sistem (rusak, hilang, kedaluwarsa, salah catat).
 */
class SimpanHitunganRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Pembuat draft/Administrator dicek di controller dan service.
    }

    public function rules(): array
    {
        return [
            'detail' => ['required', 'array'],
            'detail.*.stok_fisik' => ['required', 'integer', 'min:0', 'max:1000000'],
            'detail.*.keterangan' => ['nullable', 'string', 'max:255'],
            'ajukan' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'detail.required' => 'Belum ada hasil hitung yang diisi.',
            'detail.*.stok_fisik.required' => 'Stok fisik wajib diisi.',
            'detail.*.stok_fisik.integer' => 'Stok fisik harus bilangan bulat.',
            'detail.*.stok_fisik.min' => 'Stok fisik tidak boleh negatif.',
            'detail.*.keterangan.max' => 'Keterangan maksimal 255 karakter.',
        ];
    }

    /** Keterangan wajib untuk baris yang berselisih (butuh stok_sistem dari database). */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                /** @var StokOpname $opname */
                $opname = $this->route('opname');
                $detail = $opname->detail()->with('barang')->get()->keyBy('id_detail_opname');

                foreach ($this->input('detail', []) as $id => $baris) {
                    $item = $detail[$id] ?? null;
                    if (! $item || $validator->errors()->has("detail.{$id}.stok_fisik") || ! is_numeric($baris['stok_fisik'] ?? null)) {
                        continue;
                    }

                    if ((int) $baris['stok_fisik'] !== $item->stok_sistem && trim((string) ($baris['keterangan'] ?? '')) === '') {
                        $validator->errors()->add(
                            "detail.{$id}.keterangan",
                            "Keterangan {$item->barang->nama_barang} wajib diisi karena ada selisih."
                        );
                    }
                }
            },
        ];
    }
}
