<?php

declare(strict_types=1);

namespace app\command;

use app\service\pay\DepositOrderService;
use app\service\pay\WithdrawOrderService;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('pay:check-confirmations', '检查入金/出金链上确认数并入账')]
class PayCheckConfirmationsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();
        $deposit = (new DepositOrderService())->checkConfirmations();
        $withdraw = (new WithdrawOrderService())->checkConfirmations();
        $output->writeln("<info>入金确认完成 {$deposit} 笔，出金确认完成 {$withdraw} 笔</info>");

        return Command::SUCCESS;
    }
}
