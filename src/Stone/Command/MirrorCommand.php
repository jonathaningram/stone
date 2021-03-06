<?php

/*
 * This file is part of Stone.
 *
 * (c) Matthieu Moquet <matthieu@moquet.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Stone\Command;

use Composer\Composer;
use Composer\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * @author Matthieu Moquet <matthieu@moquet.net>
 */
class MirrorCommand extends BaseCommand
{
    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('mirror')
            ->setDescription('Mirror repositories')
            ->addArgument('file', InputArgument::REQUIRED, 'Json file to use')
        ;
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $filename  = $input->getArgument('file');
        $outputDir = $this->getRepositoryDirectory();

        if (!file_exists($filename)) {
            throw new \LogicException(sprintf('File %s doen\'t exist', $filename));
        }

        $output->writeln(sprintf('<info>Mirroring repositories from <comment>%s</comment></info>', $filename));

        // Create composer model
        $composer = $this->getComposer($filename);

        // Retrieves installed packages
        $installedPackages = $this->getInstalledPackages($outputDir);
        $packagesToInstall = $this->getPackagesToInstall($composer);

        // Retrieves all requires and download them
        $repositories = array();
        foreach ($packagesToInstall as $package) {
            $name = $package->getPrettyName();
            $targetDir = $outputDir.'/'.$name.'/sources';

            $initialPackage = isset($installedPackages[$name]) ? $installedPackages[$name] : null;

            $output->writeln(sprintf(
                '<info>%s</info> <comment>%s</comment>', $initialPackage ? 'Updating' : 'Downloading', $name
            ));

            $this->fetchPackage($composer->getDownloadManager(), $package, $targetDir, $initialPackage);

            $installedPackages[$name] = $package;
            $repositories[] = array(
                'type' => $package->getSourceType(),
                'url'  => 'file://'.realpath($targetDir)
            );
        }

        $output->writeln('<info>Saving</info> installed repositories');
        $this->dumpInstalledPackages($installedPackages, $outputDir);

        $output->writeln('<info>Dump packages.json</info>');
        $this->dumpPackagesJson($composer, $repositories, $outputDir);
    }
}
