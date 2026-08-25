<?php

declare(strict_types=1);

namespace app\service\export;

use app\model\pay\WithdrawOrderModel;

class PayWithdrawExportHandler implements ExportHandlerInterface
{
    public function prepareFilters(array $filters): array
    {
        return $filters;
    }

    public function validate(array $filters): void
    {
    }

    public function count(array $filters): int
    {
        return $this->query($filters)->count();
    }

    public function fetchBatch(array $filters, int $cursorId, int $limit): array
    {
        $query = $this->query($filters)->orderByDesc('id');
        if ($cursorId > 0) {
            $query->where('id', '<', $cursorId);
        }

        return $query->limit($limit)->get()->map(fn (WithdrawOrderModel $row) => $row->toArray())->all();
    }

    public function headers(): array
    {
        return ['ID', '订单号', '商户单号', '金额', '手续费', '实付', '收款地址', '状态', 'TxHash', '驳回原因', '创建时间', '成功时间'];
    }

    public function mapCsvRow(array $row): array
    {
        return [
            $row['id'] ?? '',
            $row['order_no'] ?? '',
            $row['out_trade_no'] ?? '',
            $row['withdraw_amount'] ?? '',
            $row['fee_amount'] ?? '',
            $row['payout_amount'] ?? '',
            $row['to_address'] ?? '',
            $row['status'] ?? '',
            $row['tx_hash'] ?? '',
            $row['reject_reason'] ?? '',
            $row['created_at'] ?? '',
            $row['succeeded_at'] ?? '',
        ];
    }

    public function filenamePrefix(): string
    {
        return 'pay_withdrawals';
    }

    private function query(array $filters)
    {
        $query = WithdrawOrderModel::query();
        foreach (['status', 'merchant_id', 'order_no', 'out_trade_no', 'tx_hash'] as $field) {
            if (!empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }
        if (!empty($filters['created_from'])) {
            $query->where('created_at', '>=', $filters['created_from']);
        }
        if (!empty($filters['created_to'])) {
            $query->where('created_at', '<=', $filters['created_to'] . ' 23:59:59');
        }

        return $query;
    }
}
