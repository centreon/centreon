<?php
require_once './vendor/autoload.php';
use Sepia\PoParser\SourceHandler\FileSystem;
use Sepia\PoParser\Parser;
use Sepia\PoParser\PoCompiler;

if ($argc < 3) {
    echo "Usage: php {$argv[0]} <i18n directory> <centreon project>\n";
    exit(1);
}

$i18nDir = $_SERVER['argv'][1];
$projectName = $_SERVER['argv'][2];

$referenceTranslationHelpFilePath = $i18nDir . '/help.pot';
$referenceTranslationMessageFilePath = $i18nDir . '/messages.pot';

$translationFilePaths = [
    'centreon-bam' => '../' . $projectName . '/www/modules/centreon-bam-server/locale/messages.pot',
];

$removedEntries = 0;

//.po Compiler
$compiler = new PoCompiler();

//Centreon help.po
$helpFileHandler = new FileSystem($referenceTranslationHelpFilePath);
$helpPoParser = new Parser($helpFileHandler);
$helpFileCatalog = $helpPoParser->parse();

//Centreon messages.po
$messageFileHandler = new FileSystem($referenceTranslationMessageFilePath);
$messagePoParser = new Parser($messageFileHandler);
$messageFileCatalog = $messagePoParser->parse();

$translationFileHandler = new FileSystem($translationFilePaths[$projectName]);
$translationPoParser = new Parser($translationFileHandler);
$translationFileCatalog = $translationPoParser->parse();
foreach ($translationFileCatalog->getEntries() as $translationEntry) {
    foreach($helpFileCatalog->getEntries() as $referenceHelpEntry) {
        if ($translationEntry->getMsgId() === $referenceHelpEntry->getMsgId()) {
            $translationFileCatalog->removeEntry($translationEntry->getMsgId());
            $removedEntries++;
        }
    }
    foreach($messageFileCatalog->getEntries() as $referenceMessageEntry) {
        if ($translationEntry->getMsgId() === $referenceMessageEntry->getMsgId()) {
            $translationFileCatalog->removeEntry($translationEntry->getMsgId());
            $removedEntries++;
        }
    }
}
$translationFileHandler->save($compiler->compile($translationFileCatalog));

printf("%d strings removed\n", $removedEntries);