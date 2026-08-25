<?php

declare(strict_types=1);

namespace app\command;

use app\service\pay\CollectionService;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('pay:collect', '扫描入金地址并归集 USDT 到热钱包')]
class PayCollectCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();
        $count = (new CollectionService())->trigger();
        $output->writeln("<info>已入队归集任务 {$count} 笔</info>");

        return Command::SUCCESS;
    }
}
