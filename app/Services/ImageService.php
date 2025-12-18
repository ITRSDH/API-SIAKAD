<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;
use Illuminate\Http\UploadedFile;

class ImageService
{
    protected ImageManager $manager;

    public function __construct()
    {
        // Gunakan GD driver (Windows friendly). Kalau ada imagick, bisa diganti.
        $this->manager = new ImageManager(new Driver());
    }

    /**
     * Convert uploaded image to WebP, save to public disk, and optionally delete an old file.
     *
     * @param \Illuminate\Http\UploadedFile $file
     * @param int $quality 0-100
     * @param string $folder path inside disk public (no leading slash), example: 'landing/logo'
     * @param string|null $oldPath existing file path or URL to delete (optional)
     *                              Accepts:
     *                                - storage path: 'landing/logo/abc.webp'
     *                                - '/storage/landing/logo/abc.webp'
     *                                - full url: 'https://domain.com/storage/landing/logo/abc.webp'
     * @param bool $deleteOriginalIfStored whether to also attempt to delete original file if applicable
     * @return string saved storage path (e.g. 'landing/logo/xxxxx.webp')
     *
     * @throws \Exception
     */
    public function convertToWebpAndReplace(UploadedFile $file, int $quality = 80, string $folder = 'images', ?string $oldPath = null, bool $deleteOriginalIfStored = false): string
    {
        // pastikan folder tanpa slash di depan atau belakang
        $folder = trim($folder, '/');

        // nama file unik
        $filename = uniqid('', true) . '.webp';
        $storagePath = $folder . '/' . $filename;

        // buat direktori jika belum ada
        Storage::disk('public')->makeDirectory($folder);

        // baca file via Intervention
        $image = $this->manager->read($file->getRealPath());
        if (!$image) {
            throw new \Exception('Gagal membaca gambar dari upload.');
        }

        // encode ke webp
        $webpEncoded = $image->toWebp($quality);

        // simpan hasil ke disk public
        Storage::disk('public')->put($storagePath, $webpEncoded);

        // jika diminta, hapus old file (aman)
        if ($oldPath) {
            $this->deletePublicFileIfExists($oldPath);
        }

        // jika ingin hapus original yang mungkin disimpan (opsional)
        if ($deleteOriginalIfStored) {
            // coba hapus file original yang path-nya sama nama file upload (jika sebelumnya disimpan)
            // contoh kemungkinan: 'landing/logo/original.png' di disk public
            $originalCandidate = $folder . '/' . pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME) . '.' . $file->getClientOriginalExtension();
            $this->deletePublicFileIfExists($originalCandidate);
        }

        return $storagePath;
    }

    /**
     * Normalize various forms of stored-path/URL to storage path and delete if exists on public disk.
     *
     * @param string $maybePathOrUrl
     * @return void
     */
    public function deletePublicFileIfExists(string $maybePathOrUrl): void
    {
        // jika string kosong, stop
        $maybePathOrUrl = trim($maybePathOrUrl);
        if ($maybePathOrUrl === '') {
            return;
        }

        // Jika full URL contains '/storage/', ambil substring setelah '/storage/'
        // Examples:
        //  - '/storage/landing/logo/abc.webp' -> 'landing/logo/abc.webp'
        //  - 'https://domain.com/storage/landing/logo/abc.webp' -> 'landing/logo/abc.webp'
        //  - 'landing/logo/abc.webp' -> 'landing/logo/abc.webp' (tidak berubah)
        $storagePrefix = '/storage/';

        if (stripos($maybePathOrUrl, $storagePrefix) !== false) {
            $pos = stripos($maybePathOrUrl, $storagePrefix);
            $maybePathOrUrl = substr($maybePathOrUrl, $pos + strlen($storagePrefix));
        }

        // juga handle 'storage/...' tanpa leading slash
        if (stripos($maybePathOrUrl, 'storage/') === 0) {
            $maybePathOrUrl = substr($maybePathOrUrl, strlen('storage/'));
        }

        // jika url ber-format full domain tanpa /storage/ (mis: CDN path), kita tidak bisa hapus dari local storage.
        // cek apakah file ada di disk public
        if (Storage::disk('public')->exists($maybePathOrUrl)) {
            Storage::disk('public')->delete($maybePathOrUrl);
        }
    }
}
