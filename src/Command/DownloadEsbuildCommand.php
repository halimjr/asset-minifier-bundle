<?php

namespace HalimJr\AssetMinifierBundle\Command;

use HalimJr\AssetMinifierBundle\Binary\EsbuildBinary;
use HalimJr\AssetMinifierBundle\Binary\Platform;
use HalimJr\AssetMinifierBundle\Exception\BinaryDownloadException;
use HalimJr\AssetMinifierBundle\Exception\PlatformNotSupportedException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'asset-minifier:download',
    description: 'Downloads the esbuild binary for the current platform.',
)]
class DownloadEsbuildCommand extends Command
{
    public function __construct(private readonly EsbuildBinary $binary)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Preview what would be downloaded without actually downloading.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        if ($input->getOption('dry-run')) {
            $io->title('esbuild download preview');
            $io->definitionList(
                ['Version'     => $this->binary->getVersion()],
                ['Platform'    => Platform::detect()],
                ['Download URL'=> $this->binary->getDownloadUrl()],
                ['Destination' => $this->binary->getPath()],
                ['Status'      => $this->binary->isAvailable() ? '<info>Already downloaded</info>' : '<comment>Not yet downloaded</comment>'],
            );
            return Command::SUCCESS;
        }

        if (!$this->binary->isManaged()) {
            if ($this->binary->isAvailable()) {
                $io->success(sprintf('Using pre-configured binary at "%s".', $this->binary->getPath()));
            } else {
                $io->error(sprintf(
                    'binary_path is configured but no binary was found at "%s".',
                    $this->binary->getPath()
                ));
                return Command::FAILURE;
            }
            return Command::SUCCESS;
        }

        if ($this->binary->isAvailable()) {
            $io->success(sprintf('esbuild v%s is already downloaded.', $this->binary->getVersion()));

            return Command::SUCCESS;
        }

        try {
            $this->binary->download($output);
        } catch (PlatformNotSupportedException | BinaryDownloadException $e) {
            $io->error($e->getMessage());

            return Command::FAILURE;
        }

        $io->success(sprintf('esbuild v%s downloaded to: %s', $this->binary->getVersion(), $this->binary->getPath()));

        return Command::SUCCESS;
    }
}
