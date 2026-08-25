<?php

declare(strict_types=1);

namespace app\command;

use app\support\pay\PaySecretCipher;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('pay:encrypt-key', '加密 TRON 热钱包私钥，写入 TRON_HOT_WALLET_PRIVATE_KEY_ENCRYPTED')]
class PayEncryptKeyCommand extends Command
{
    protected function configure(): void
    {
        $this->addArgument('private_key', InputArgument::REQUIRED, '64 位 hex 私钥（不含 0x）');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $key = trim((string) $input->getArgument('private_key'));
        $key = ltrim($key, '0x');
        if (!preg_match('/^[0-9a-fA-F]{64}$/', $key)) {
            $output->writeln('<error>私钥必须为 64 位 hex</error>');
            return Command::FAILURE;
        }

        $encrypted = PaySecretCipher::encrypt(strtolower($key));
        $output->writeln('<info>将以下值写入 .env：</info>');
        $output->writeln('TRON_HOT_WALLET_PRIVATE_KEY_ENCRYPTED=' . $encrypted);
        $output->writeln('<comment>并删除明文 TRON_HOT_WALLET_PRIVATE_KEY（如有）</comment>');

        return Command::SUCCESS;
    }
}
