<?php

declare(strict_types=1);

namespace app\support\chain;

interface ChainAdapterInterface
{
    public function chain(): string;

    public function validateAddress(string $address): bool;

    public function deriveDepositAddress(int $index): string;

    public function getUsdtBalance(string $address): string;

    /**
     * @return list<array{tx_hash:string,from:string,to:string,amount:string,block_number:int,confirmations:int,log_index:int}>
     */
    public function fetchIncomingTransfers(string $address, ?int $minTimestamp = null): array;

    public function broadcastUsdtTransfer(string $fromPrivateKey, string $toAddress, string $amount): string;

    public function getConfirmations(string $txHash): int;
}
