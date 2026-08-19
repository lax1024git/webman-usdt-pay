<?php

declare(strict_types=1);

namespace app\command;

use app\service\LangTextService;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand('lang:translate', '批量一键翻译多语言文案（Google Translate）')]
class LangTranslateCommand extends Command
{
    protected function configure(): void
    {
        $this
            ->addOption('id', null, InputOption::VALUE_REQUIRED, '仅翻译指定文案 ID')
            ->addOption('type', 't', InputOption::VALUE_REQUIRED, '文案类型：front|admin')
            ->addOption('force', 'f', InputOption::VALUE_NONE, '覆盖已有译文（默认只填空）')
            ->addOption('only-empty', null, InputOption::VALUE_NONE, '跳过已全部填满的文案行')
            ->addOption('limit', 'l', InputOption::VALUE_REQUIRED, '最多处理条数');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();

        $id = (int) ($input->getOption('id') ?? 0);
        $type = $input->getOption('type');
        $limit = (int) ($input->getOption('limit') ?? 0);
        $overwrite = (bool) $input->getOption('force');
        $onlyEmpty = (bool) $input->getOption('only-empty');

        $output->writeln('<comment>开始翻译任务…</comment>');
        $output->writeln(sprintf(
            '参数: id=%s type=%s force=%s only-empty=%s limit=%s',
            $id > 0 ? (string) $id : '-',
            is_string($type) && $type !== '' ? $type : '-',
            $overwrite ? 'yes' : 'no',
            $onlyEmpty ? 'yes' : 'no',
            $limit > 0 ? (string) $limit : '-'
        ));

        $result = (new LangTextService())->translateBatch([
            'id' => $id,
            'type' => is_string($type) ? $type : null,
            'overwrite' => $overwrite,
            'only_empty' => $onlyEmpty,
            'limit' => $limit,
        ]);

        $output->writeln(sprintf(
            '<info>完成：扫描 %d，成功 %d，失败 %d</info>',
            $result['total'],
            $result['success'],
            $result['failed']
        ));

        foreach ($result['errors'] as $err) {
            $output->writeln('<error>' . $err . '</error>');
        }

        return $result['failed'] > 0 ? Command::FAILURE : Command::SUCCESS;
    }
}
