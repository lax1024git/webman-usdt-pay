<?php

declare(strict_types=1);

namespace app\exception;

use Throwable;
use Webman\Http\Request;
use Webman\Http\Response;
use support\exception\Handler as BaseHandler;
use app\support\ErrorCode;

class Handler extends BaseHandler
{
    public function render(Request $request, Throwable $exception): Response
    {
        if ($exception instanceof BusinessException) {
            return fail($exception->getCode(), $exception->getMessage());
        }

        if (config('app.debug')) {
            return parent::render($request, $exception);
        }

        return fail(ErrorCode::INTERNAL_ERROR, null, 500);
    }
}
