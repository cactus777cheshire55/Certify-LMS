<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\Certification\CertificatePdfGenerationException;
use App\Models\Certificate;
use Illuminate\Support\Facades\Storage;
use Mpdf\Mpdf;
use Mpdf\Output\Destination;
use Throwable;

/**
 * 修了証 PDF を生成し、プライベート保管領域(`private` disk)の `Certificate::pdf_path` に保存する Service。
 *
 * - テンプレートは `resources/views/certificates/pdf.blade.php`(全資格共通)
 * - 日本語描画は mPDF 同梱の CJK フォント `sun-exta` を使用し、外部フォントのダウンロードは不要
 * - 生成 / 保存のいずれかに失敗した場合は CertificatePdfGenerationException を投げる。
 *   呼び出し元(`Certificate\IssueAction`)のトランザクションをロールバックさせ、修了証を未発行状態に保つ
 */
class CertificatePdfService
{
    public const DISK = 'private';

    /**
     * @throws CertificatePdfGenerationException
     */
    public function generate(Certificate $certificate): void
    {
        $certificate->loadMissing(['user', 'certification']);

        try {
            $html = view('certificates.pdf', ['certificate' => $certificate])->render();

            $mpdf = new Mpdf([
                'mode' => 'utf-8',
                'format' => 'A4-L',
                'default_font' => 'sun-exta',
                'tempDir' => storage_path('framework/cache/mpdf'),
            ]);
            $mpdf->SetTitle('修了証');
            $mpdf->WriteHTML($html);

            $binary = $mpdf->Output('', Destination::STRING_RETURN);
        } catch (Throwable $e) {
            throw new CertificatePdfGenerationException($e);
        }

        if (! Storage::disk(self::DISK)->put($certificate->pdf_path, $binary)) {
            throw new CertificatePdfGenerationException;
        }
    }
}
