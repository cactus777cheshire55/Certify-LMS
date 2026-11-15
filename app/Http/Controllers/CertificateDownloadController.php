<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Certificate;
use App\Services\CertificatePdfService;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * 修了証 PDF のダウンロード Controller。
 *
 * 認可は CertificatePolicy::download(受講生本人 / 担当資格のコーチ / 管理者)で完結。
 * PDF は発行時に CertificatePdfService がプライベート保管領域へ保存済のものを配信し、ここでは再生成しない。
 * 保管領域に実体が無い場合は 404。
 */
class CertificateDownloadController extends Controller
{
    public function download(Certificate $certificate): StreamedResponse
    {
        $this->authorize('download', $certificate);

        $disk = Storage::disk(CertificatePdfService::DISK);

        abort_unless($disk->exists($certificate->pdf_path), 404, '修了証 PDF が見つかりません。');

        return $disk->download(
            $certificate->pdf_path,
            "certificate_{$certificate->id}.pdf",
            ['Content-Type' => 'application/pdf'],
        );
    }
}
