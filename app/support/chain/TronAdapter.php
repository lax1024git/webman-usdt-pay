<?php

declare(strict_types=1);

namespace app\support\chain;

use app\support\Decimal;
use app\support\pay\HotWalletConfig;

class TronAdapter implements ChainAdapterInterface
{
    private const TRANSFER_SELECTOR = 'a9059cbb';

    private string $apiUrl;

    private string $apiKey;

    private string $contract;

    private TronSigner $signer;

    public function __construct()
    {
        $this->apiUrl = rtrim((string) env('TRON_API_URL', 'https://api.trongrid.io'), '/');
        $this->apiKey = (string) env('TRON_API_KEY', '');
        $this->contract = (string) env('TRON_USDT_CONTRACT', 'TR7NHqjeKQxGTCi8q8ZY4pL8otSzgjLj6t');
        $this->signer = new TronSigner();
    }

    public function chain(): string
    {
        return 'TRC20';
    }

    public function validateAddress(string $address): bool
    {
        return TronAddress::isValid($address);
    }

    public function deriveDepositAddress(int $index): string
    {
        if (TronHdWallet::isConfigured()) {
            return TronHdWallet::derive($index)['address'];
        }

        $hot = HotWalletConfig::address();
        if ($hot !== '' && $this->validateAddress($hot)) {
            return $hot;
        }

        throw new \RuntimeException('请配置 TRON_HD_MNEMONIC/TRON_HD_SEED，或 TRON_HOT_WALLET_ADDRESS');
    }

    public function getUsdtBalance(string $address): string
    {
        $param = TronAddress::encodeAddressParam($address);
        $payload = $this->request('POST', '/wallet/triggerconstantcontract', [
            'owner_address' => $address,
            'contract_address' => $this->contract,
            'function_selector' => 'balanceOf(address)',
            'parameter' => $param,
            'visible' => true,
        ]);

        $hex = $payload['constant_result'][0] ?? '0';
        if ($hex === '' || $hex === '0') {
            return '0.000000';
        }

        return Decimal::format(bcdiv((string) hexdec($hex), '1000000', 6));
    }

    public function fetchIncomingTransfers(string $address, ?int $minTimestamp = null): array
    {
        $query = http_build_query([
            'limit' => 50,
            'contract_address' => $this->contract,
            'only_to' => 'true',
            'order_by' => 'block_timestamp,desc',
        ]);
        $payload = $this->request('GET', '/v1/accounts/' . $address . '/transactions/trc20?' . $query);
        $items = [];
        foreach ($payload['data'] ?? [] as $row) {
            if (strcasecmp((string) ($row['to'] ?? ''), $address) !== 0) {
                continue;
            }
            if ($minTimestamp !== null && (int) ($row['block_timestamp'] ?? 0) < $minTimestamp * 1000) {
                continue;
            }
            $items[] = [
                'tx_hash' => (string) ($row['transaction_id'] ?? ''),
                'from' => (string) ($row['from'] ?? ''),
                'to' => (string) ($row['to'] ?? ''),
                'amount' => Decimal::format(bcdiv((string) ($row['value'] ?? '0'), '1000000', 6)),
                'block_number' => (int) ($row['block_number'] ?? 0),
                'confirmations' => 0,
                'log_index' => 0,
            ];
        }

        return $items;
    }

    public function broadcastUsdtTransfer(string $fromPrivateKey, string $toAddress, string $amount): string
    {
        if ($fromPrivateKey === '') {
            throw new \RuntimeException('未配置热钱包私钥');
        }
        if (!$this->validateAddress($toAddress)) {
            throw new \InvalidArgumentException('目标地址无效');
        }

        $fromAddress = $this->signer->addressFromPrivateKey($fromPrivateKey);
        $amountSun = bcmul(Decimal::format($amount), '1000000', 0);
        $amountHex = str_pad(gmp_strval(gmp_init($amountSun), 16), 64, '0', STR_PAD_LEFT);
        $addressParam = TronAddress::encodeAddressParam($toAddress);
        $parameter = $addressParam . $amountHex;

        $feeLimit = (int) env('TRON_FEE_LIMIT', 100_000_000);

        $trigger = $this->request('POST', '/wallet/triggersmartcontract', [
            'owner_address' => $fromAddress,
            'contract_address' => $this->contract,
            'function_selector' => 'transfer(address,uint256)',
            'parameter' => $parameter,
            'fee_limit' => $feeLimit,
            'call_value' => 0,
            'visible' => true,
        ]);

        if (!empty($trigger['result']['code']) && $trigger['result']['code'] !== 'SUCCESS') {
            $msg = $trigger['result']['message'] ?? 'unknown';
            if (is_string($msg) && ctype_xdigit($msg)) {
                $decoded = @hex2bin($msg);
                $msg = ($decoded !== false && $decoded !== '') ? $decoded : $msg;
            }
            throw new \RuntimeException('构建交易失败: ' . $msg);
        }

        $transaction = $trigger['transaction'] ?? null;
        if (!is_array($transaction)) {
            throw new \RuntimeException('TronGrid 未返回 transaction 对象');
        }

        $txId = (string) ($transaction['txID'] ?? '');
        if ($txId === '') {
            throw new \RuntimeException('交易缺少 txID');
        }

        $signature = $this->signer->signTxId($txId, $fromPrivateKey);
        $transaction['signature'] = [$signature];

        $broadcast = $this->request('POST', '/wallet/broadcasttransaction', $transaction);
        if (empty($broadcast['result']) || $broadcast['result'] !== true) {
            $msg = $broadcast['message'] ?? json_encode($broadcast, JSON_UNESCAPED_UNICODE);
            throw new \RuntimeException('广播失败: ' . (is_string($msg) ? $msg : 'unknown'));
        }

        return (string) ($broadcast['txid'] ?? $txId);
    }

    public function getConfirmations(string $txHash): int
    {
        if ($txHash === '') {
            return 0;
        }
        $info = $this->request('POST', '/wallet/gettransactioninfobyid', ['value' => $txHash, 'visible' => true]);
        if (empty($info['blockNumber'])) {
            return 0;
        }
        $block = $this->request('POST', '/wallet/getnowblock', []);
        $current = (int) ($block['block_header']['raw_data']['number'] ?? 0);
        $txBlock = (int) $info['blockNumber'];

        return max(0, $current - $txBlock + 1);
    }

    public function getTrxBalance(string $address): string
    {
        $payload = $this->request('POST', '/wallet/getaccount', [
            'address' => $address,
            'visible' => true,
        ]);
        $sun = (string) ($payload['balance'] ?? '0');

        return Decimal::format(bcdiv($sun, '1000000', 6));
    }

    /**
     * @return array<string, mixed>
     */
    private function request(string $method, string $path, array $body = []): array
    {
        $url = $this->apiUrl . $path;
        $headers = ['Content-Type: application/json'];
        if ($this->apiKey !== '') {
            $headers[] = 'TRON-PRO-API-KEY: ' . $this->apiKey;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_HTTPHEADER => $headers,
        ]);
        if ($method === 'GET') {
            curl_setopt($ch, CURLOPT_HTTPGET, true);
        } else {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_THROW_ON_ERROR));
        }
        $raw = curl_exec($ch);
        $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if ($raw === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException('TronGrid 请求失败: ' . $err);
        }
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new \RuntimeException("TronGrid HTTP {$httpCode}: {$raw}");
        }

        $decoded = json_decode($raw, true);

        return is_array($decoded) ? $decoded : [];
    }
}
