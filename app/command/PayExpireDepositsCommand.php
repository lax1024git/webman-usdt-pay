<?php

declare(strict_types=1);

namespace app\command;

use app\service\pay\DepositOrderService;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('pay:expire-deposits', '过期未支付的 USDT 入金订单')]
class PayExpireDepositsCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();
        $count = (new DepositOrderService())->expirePendingOrders();
        $output->writeln("<info>已过期 {$count} 笔入金订单</info>");

        return Command::SUCCESS;
    }
}
