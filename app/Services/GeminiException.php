<?php

namespace App\Services;

use RuntimeException;

/** Kegagalan memanggil Gemini API (key kosong, HTTP error, respons kosong). Disimpan sebagai status gagal. */
class GeminiException extends RuntimeException {}
