<?php
require('makefont/makefont.php');

$fontLibDir = implode( DIRECTORY_SEPARATOR,[__DIR__,'..','..','lib','fpdfFonts','']);
if (!is_dir($fontLibDir)) {
    if (mkdir($fontLibDir)) {
        echo "Created directory for fpdf Fonts: $fontLibDir\n";
    } else {
        echo "Couldn't create directory for fpdf Fonts: $fontLibDir\n";
        exit;
    }
}

$fontDir = __DIR__.DIRECTORY_SEPARATOR.'ttfFonts';
if (!is_dir($fontDir)) {
    echo "Couldn't open font directory: $fontDir\n";
    exit;
}

$fontDirHandle = opendir($fontDir);
$ttfFiles = [];
while ($subDir = readdir($fontDirHandle)) {
    $subDirPath = $fontDir.DIRECTORY_SEPARATOR.$subDir;
    if (substr($subDir,0,1)=='.') continue;
    if (!is_dir($subDirPath)) continue;
    echo "Opening directory: $subDir\n";
    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($subDirPath));

    foreach ($files as $file) {
        if ($file->isFile() && pathinfo($file->getFilename(), PATHINFO_EXTENSION) === 'ttf') {
            // Ignore Variable Weight fonts
            if (strpos($file->getFilename(),'VariableFont_wght')) continue;
            $ttfFiles[] = $file->getRealPath();
        }
    }

}
echo "Found the following font files for processing:\n";
print_r($ttfFiles);

foreach( $ttfFiles as $ttfFile ) {
    // See http://www.fpdf.org/en/tutorial/tuto7.htm
    // The cp1252 here is a bit of a guess!
    MakeFont($ttfFile,'cp1252',true);
    $fontFile = basename($ttfFile);
    $fontFile = preg_replace('/\\.ttf$/i','',$fontFile);
    rename($fontFile.'.php',$fontLibDir.$fontFile.'.php');
    rename($fontFile.'.z',$fontLibDir.$fontFile.'.z');
}
?>
