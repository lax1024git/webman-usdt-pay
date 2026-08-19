<?php

declare(strict_types=1);

namespace app\command;

use app\support\Schema\ModelSchemaSynchronizer;
use database\support\DatabaseBootstrap;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

#[AsCommand('model', '根据 app/model 中的 tableSchema 定义同步数据库表结构与索引')]
class ModelCommand extends Command
{
    /** @var list<string> */
    private const MODULES = [
        'sys', 'content', 'member', 'pay', 'ad', 'invest', 'activity', 'ops', 'service', 'trade',
    ];

    protected function configure(): void
    {
        $this->addArgument(
            'name',
            InputArgument::OPTIONAL,
            '模型类名或路径，如 ArticleModel、invest/InvestmentProductModel'
        );
        $this->addOption('make', 'm', InputOption::VALUE_NONE, '生成带 tableSchema 的模型存根');
        $this->addOption('force', 'f', InputOption::VALUE_NONE, '生成模型时强制覆盖已存在文件');
        $this->addOption(
            'dir',
            'd',
            InputOption::VALUE_REQUIRED,
            '生成模型时的子目录（sys/content/member/pay/ad/invest/activity/ops/service/trade）'
        );
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, '仅扫描并列出将同步的模型，不写库');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        DatabaseBootstrap::init();

        $name = $input->getArgument('name');
        $name = is_string($name) && $name !== '' ? $name : null;

        if ($input->getOption('make')) {
            if ($name === null) {
                $output->writeln('<error>请指定模型名，例如: php webman model ArticleModel --make</error>');
                $output->writeln('<comment>子目录示例: php webman model FooModel --make --dir=invest</comment>');
                $output->writeln('<comment>或: php webman model invest/FooModel --make</comment>');

                return Command::FAILURE;
            }

            return $this->makeModel(
                $name,
                (bool) $input->getOption('force'),
                is_string($input->getOption('dir')) ? $input->getOption('dir') : null,
                $output
            );
        }

        $synchronizer = new ModelSchemaSynchronizer();

        if ($input->getOption('dry-run')) {
            return $this->dryRun($synchronizer, $name, $output);
        }

        if ($name === null) {
            $output->writeln('<comment>未指定模型，将全量同步所有 tableSchema（可能较慢）。建议: php webman model ActivityModel</comment>');
        }

        try {
            $result = $synchronizer->sync($name, static function (
                string $status,
                string $model,
                string $table,
                int $index,
                int $total
            ) use ($output): void {
                $label = $table !== '' ? "{$model} -> {$table}" : $model;
                $prefix = "[{$index}/{$total}]";
                match ($status) {
                    'start' => $output->writeln("<comment>{$prefix} 同步中: {$label}</comment>"),
                    'created' => $output->writeln("<info>{$prefix} 已创建: {$label}</info>"),
                    'updated' => $output->writeln("<info>{$prefix} 已更新: {$label}</info>"),
                    'skipped' => $output->writeln("<comment>{$prefix} 无需变更: {$label}</comment>"),
                    'failed' => $output->writeln("<error>{$prefix} 失败: {$label}</error>"),
                    default => null,
                };
            });
        } catch (Throwable $e) {
            $output->writeln('<error>同步失败: ' . $e->getMessage() . '</error>');

            return Command::FAILURE;
        }

        if ($name !== null && $result['created'] === [] && $result['updated'] === [] && $result['skipped'] === [] && ($result['failed'] ?? []) === []) {
            $output->writeln("<error>未找到模型: {$name}</error>");

            return Command::FAILURE;
        }

        foreach ($result['failed'] ?? [] as $item) {
            $output->writeln("<error>失败 {$item['table']}: {$item['error']}</error>");
        }

        $created = count($result['created']);
        $updated = count($result['updated']);
        $skipped = count($result['skipped']);
        $failed = count($result['failed'] ?? []);

        if ($created === 0 && $updated === 0 && $skipped === 0 && $failed === 0) {
            $output->writeln('<comment>未发现实现 DefinesTableSchema 的模型</comment>');
        } else {
            $output->writeln("<info>完成: 创建 {$created} / 更新 {$updated} / 跳过 {$skipped} / 失败 {$failed}</info>");
        }

        return $failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function dryRun(ModelSchemaSynchronizer $synchronizer, ?string $name, OutputInterface $output): int
    {
        $models = $synchronizer->discover($name);
        if ($models === []) {
            $output->writeln($name !== null
                ? "<error>未找到模型: {$name}</error>"
                : '<comment>未发现实现 DefinesTableSchema 的模型</comment>');

            return $name !== null ? Command::FAILURE : Command::SUCCESS;
        }

        $output->writeln('<info>将同步 ' . count($models) . ' 个模型:</info>');
        foreach ($models as $model) {
            $output->writeln(sprintf(
                '  [%d] %s -> %s',
                $model['order'],
                $model['name'],
                $model['schema']['table'] ?? '?'
            ));
        }

        return Command::SUCCESS;
    }

    private function makeModel(string $name, bool $force, ?string $dirOption, OutputInterface $output): int
    {
        [$module, $className] = $this->resolveMakeTarget($name, $dirOption);
        if ($module !== null && !in_array($module, self::MODULES, true)) {
            $output->writeln('<error>不支持的模块目录: ' . $module . '</error>');
            $output->writeln('<comment>可选: ' . implode(', ', self::MODULES) . '</comment>');

            return Command::FAILURE;
        }

        $relativeDir = $module !== null ? "app/model/{$module}" : 'app/model';
        $namespace = $module !== null ? "app\\model\\{$module}" : 'app\\model';
        $path = base_path("{$relativeDir}/{$className}.php");

        if (file_exists($path) && !$force) {
            $output->writeln("<error>模型已存在: {$path}，使用 --force 覆盖</error>");

            return Command::FAILURE;
        }

        $entityName = str_ends_with($className, 'Model') ? substr($className, 0, -5) : $className;
        $table = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $entityName) ?? $entityName);
        $prefix = $this->guessTablePrefix($module);
        if ($prefix !== '' && !str_starts_with($table, $prefix)) {
            $table = $prefix . $table;
        }

        $stub = <<<PHP
<?php

declare(strict_types=1);

namespace {$namespace};

use app\model\Concerns\DefinesTableSchema;
use Illuminate\Database\Eloquent\Model;

class {$className} extends Model
{
    use DefinesTableSchema;

    protected \$table = '{$table}';

    protected \$fillable = [];

    public static function tableSchema(): array
    {
        return [
            'table' => '{$table}',
            'comment' => '',
            'columns' => [
                'id' => ['type' => 'increments', 'comment' => '主键ID'],
            ],
            'timestamps' => true,
            'columnComments' => [
                'created_at' => '创建时间',
                'updated_at' => '更新时间',
            ],
            'indexes' => [],
        ];
    }
}

PHP;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $stub);
        $output->writeln("<info>已生成模型: {$path}</info>");

        return Command::SUCCESS;
    }

    /**
     * @return array{0: ?string, 1: string}
     */
    private function resolveMakeTarget(string $name, ?string $dirOption): array
    {
        $name = str_replace('\\', '/', trim($name));
        $module = $dirOption !== null && $dirOption !== '' ? trim($dirOption, '/') : null;
        $classPart = $name;

        if (str_contains($name, '/')) {
            $parts = explode('/', $name);
            $classPart = array_pop($parts);
            if ($module === null && $parts !== []) {
                $module = $parts[0];
            }
        }

        return [$module, ModelSchemaSynchronizer::normalizeModelClassName($classPart)];
    }

    private function guessTablePrefix(?string $module): string
    {
        return match ($module) {
            'sys' => 'sy_',
            'content' => 'co_',
            'member', 'trade' => 'me_',
            'pay' => 'pa_',
            'ad' => 'ad_',
            'invest' => 'iv_',
            'activity' => 'act_',
            'ops' => 'toc_',
            'service' => 'sc_',
            default => '',
        };
    }
}
