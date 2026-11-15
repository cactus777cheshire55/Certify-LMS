<?php

declare(strict_types=1);

namespace App\Exceptions\Certification;

use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * 修了証 PDF の生成 / 保存に失敗した際の例外（HTTP 500）。
 * `Certificate\IssueAction` のトランザクション内で投げられ、修了証レコードの発行をロールバックさせる。
 */
final class CertificatePdfGenerationException extends HttpException
{
    public function __construct(?\Throwable $previous = null)
    {
        parent::__construct(500, '修了証 PDF の生成に失敗しました。時間をおいて再度お試しください。', $previous);
    }
}
