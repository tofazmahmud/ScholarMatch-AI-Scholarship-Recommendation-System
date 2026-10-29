<?php
function sm_cv_read_document(string $tmpPath,string $filename): string {
    $ext=strtolower(pathinfo($filename,PATHINFO_EXTENSION));
    if($ext==='pdf') {
        $mime=(new finfo(FILEINFO_MIME_TYPE))->file($tmpPath);
        if($mime!=='application/pdf' || file_get_contents($tmpPath,false,null,0,5)!=='%PDF-') throw new RuntimeException('Invalid PDF upload.');
        require_once __DIR__.'/../vendor/autoload.php';
        $config=new \Smalot\PdfParser\Config();
        $config->setDecodeMemoryLimit(32 * 1024 * 1024);
        $parser=new \Smalot\PdfParser\Parser([], $config);
        return trim($parser->parseFile($tmpPath)->getText());
    }
    if($ext==='docx') {
        if(!class_exists('ZipArchive')) throw new RuntimeException('Enable the PHP zip extension for DOCX.');
        $z=new ZipArchive();
        if($z->open($tmpPath)!==true) throw new RuntimeException('Invalid DOCX upload.');
        try {
          if($z->numFiles>300) throw new RuntimeException('DOCX has too many internal files.');
          $meta=$z->statName('word/document.xml');
          if(!$meta || (int)$meta['size']>2500000) throw new RuntimeException('DOCX content is too large or invalid.');
          $xml=$z->getFromName('word/document.xml');
        } finally { $z->close(); }
        if(!is_string($xml)) throw new RuntimeException('DOCX text missing.');
        $dom=new DOMDocument();
        $old=libxml_use_internal_errors(true);
        try {
          if(!$dom->loadXML($xml,LIBXML_NONET)) throw new RuntimeException('Invalid DOCX XML.');
        } finally {libxml_clear_errors();libxml_use_internal_errors($old);}
        $xp=new DOMXPath($dom);
        $xp->registerNamespace('w','http://schemas.openxmlformats.org/wordprocessingml/2006/main');
        $paras=[];
        foreach($xp->query('//w:body//w:p') as $p) {
          $line=''; foreach($xp->query('.//w:t',$p) as $n) $line.=$n->textContent;
          if(trim($line)!=='') $paras[]=trim($line);
        }
        return trim(implode("\n",$paras));
    }
    throw new RuntimeException('Only PDF or DOCX uploads are allowed.');
}
