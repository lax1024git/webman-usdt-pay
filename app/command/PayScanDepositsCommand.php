<?php

declare(strict_types=1);

namespace app\command;

use app\service\pay\DepositOrderService;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('pay:scan-deposits', '扫描 USDT 入金链上到账')]
class PayScanDepositsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();
        $count = (new DepositOrderService())->scanPendingDeposits();
        $confirmed = (new DepositOrderService())->checkConfirmations();
        $output->writeln("<info>扫描 {$count} 笔到账，确认完成 {$confirmed} 笔</info>");

        return Command::SUCCESS;
    }
}
