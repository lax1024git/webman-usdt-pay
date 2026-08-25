<?php

declare(strict_types=1);

namespace app\command;

use app\service\pay\WebhookService;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('pay:webhook-retry', '补偿重试失败的商户回调')]
class PayWebhookRetryCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();
        $count = (new WebhookService())->compensateFailed();
        $output->writeln("<info>已入队补偿回调 {$count} 条</info>");

        return Command::SUCCESS;
    }
}
