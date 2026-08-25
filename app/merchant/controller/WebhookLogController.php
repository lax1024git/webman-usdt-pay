<?php

declare(strict_types=1);

namespace app\merchant\controller;

use app\model\pay\WebhookLogModel;
use app\service\pay\WebhookService;
use support\Request;

class WebhookLogController extends BaseController
{
    public function index(Request $request)
    {
        [$page, $limit] = $this->pageParams($request);
        $query = WebhookLogModel::query()
            ->where('merchant_id', $this->merchantId($request))
            ->orderByDesc('id');

        if ($orderNo = $request->get('order_no')) {
            $query->where('order_no', $orderNo);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }

        $total = $query->count();
        $items = $query->offset(($page - 1) * $limit)->limit($limit)->get();

        return success(['total' => $total, 'items' => $items]);
    }

    public function retry(Request $request, int $id)
    {
        $log = WebhookLogModel::query()
            ->where('merchant_id', $this->merchantId($request))
            ->where('id', $id)
            ->first();
        if (!$log) {
            return fail(43008, '回调记录不存在');
        }

        (new WebhookService())->retry((int) $log->id, $this->merchantId($request));
        return success([], '已加入重试队列');
    }
}
