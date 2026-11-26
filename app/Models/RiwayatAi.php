<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class RiwayatAi extends Model
{
    protected $table = 'riwayat_ai';

    protected $primaryKey = 'id_riwayat_ai';

    protected $fillable = [
        'id_user',
        'pertanyaan',
        'topik',
        'konteks_data',
        'jawaban',
        'model',
        'status',
        'pesan_error',
        'waktu_respons_ms',
    ];

    protected function casts(): array
    {
        return [
            'konteks_data' => 'array',
            'waktu_respons_ms' => 'integer',
        ];
    }

    public function isBerhasil(): bool
    {
        return $this->status === 'berhasil';
    }

    /**
     * Jawaban Markdown → HTML yang aman: tag HTML dari Gemini dibuang (html_input strip) dan tautan tidak aman ditolak.
     */
    public function jawabanHtml(): HtmlString
    {
        return new HtmlString(Str::markdown((string) $this->jawaban, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_user', 'id_user');
    }
}
