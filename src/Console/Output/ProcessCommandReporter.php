<?php declare(strict_types=1);

namespace Rector\Console\Output;

use Rector\Application\Error;
use Rector\Console\ConsoleStyle;
use Rector\Contract\Rector\RectorInterface;
use Rector\NodeTraverser\RectorNodeTraverser;
use Rector\Reporting\FileDiff;
use Rector\YamlRector\YamlFileProcessor;
use function Safe\sprintf;

final class ProcessCommandReporter
{
    /**
     * @var ConsoleStyle
     */
    private $consoleStyle;

    /**
     * @var RectorNodeTraverser
     */
    private $rectorNodeTraverser;

    /**
     * @var YamlFileProcessor
     */
    private $yamlFileProcessor;

    public function __construct(
        RectorNodeTraverser $rectorNodeTraverser,
        ConsoleStyle $consoleStyle,
        YamlFileProcessor $yamlFileProcessor
    ) {
        $this->consoleStyle = $consoleStyle;
        $this->rectorNodeTraverser = $rectorNodeTraverser;
        $this->yamlFileProcessor = $yamlFileProcessor;
    }

    public function reportLoadedRectors(): void
    {
        $rectorCount = $this->rectorNodeTraverser->getRectorCount() + $this->yamlFileProcessor->getYamlRectorsCount();

        $this->consoleStyle->title(sprintf('%d Loaded Rector%s', $rectorCount, $rectorCount === 1 ? '' : 's'));

        $allRectors = array_merge(
            $this->rectorNodeTraverser->getRectors() + $this->yamlFileProcessor->getYamlRectors()
        );

        $rectorClasses = array_map(function (RectorInterface $rector): string {
            return get_class($rector);
        }, $allRectors);

        $this->consoleStyle->listing($rectorClasses);
    }

    /**
     * @param string[] $changedFiles
     */
    public function reportChangedFiles(array $changedFiles): void
    {
        if (count($changedFiles) <= 0) {
            return;
        }

        $this->consoleStyle->title(
            sprintf('%d Changed file%s', count($changedFiles), count($changedFiles) === 1 ? '' : 's')
        );
        $this->consoleStyle->listing($changedFiles);
    }

    /**
     * @param FileDiff[] $fileDiffs
     */
    public function reportFileDiffs(array $fileDiffs): void
    {
        if (count($fileDiffs) <= 0) {
            return;
        }

        $this->consoleStyle->title(
            sprintf('%d file%s with changes', count($fileDiffs), count($fileDiffs) === 1 ? '' : 's')
        );

        $i = 0;
        foreach ($fileDiffs as $fileDiff) {
            $this->consoleStyle->writeln(sprintf('<options=bold>%d) %s</>', ++$i, $fileDiff->getFile()));
            $this->consoleStyle->newLine();
            $this->consoleStyle->writeln($fileDiff->getDiff());
            $this->consoleStyle->newLine();
        }
    }

    /**
     * @param Error[] $errors
     */
    public function reportErrors(array $errors): void
    {
        foreach ($errors as $error) {
            $message = sprintf(
                'Could not process "%s" file, due to: %s"%s".',
                $error->getFileInfo()->getPathname(),
                PHP_EOL,
                $error->getMessage()
            );

            if ($error->getLine()) {
                $message .= ' On line: ' . $error->getLine();
            }

            $this->consoleStyle->error($message);
        }
    }
}
