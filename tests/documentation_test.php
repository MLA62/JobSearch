<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$count=0; $links=0;
$iterator=new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.'/docs',FilesystemIterator::SKIP_DOTS));
$files=[$root.'/README.md',$root.'/AGENTS.md',$root.'/LICENSE.md'];
foreach ($iterator as $entry) {
    if ($entry->isFile() && $entry->getExtension()==='md') { $files[]=$entry->getPathname(); }
}
foreach ($files as $file) {
    $text=file_get_contents($file);
    if (str_contains(basename($file),'1.15.8') || in_array(basename($file),['RELEASE-1.18.0.md','1.17.1.md','1.17.0.md','1.16.3.md','1.16.2.md'],true)) {
        if (!str_contains($text,'Historischer Stand.')) { throw new RuntimeException('Missing archive notice '.$file); }
    }
    preg_match_all('/\\[[^\\]]+\\]\\(([^)\\n]+)\\)/',$text,$matches);
    foreach ($matches[1] as $link) {
        if (preg_match('~^[a-z]+:|^#~i',$link)) { continue; }
        $target=rawurldecode(explode('#',trim($link,'<>'),2)[0]);
        if (!is_file(dirname($file).'/'.$target)) { throw new RuntimeException('Broken link '.$file.' -> '.$target); }
        $links++;
    }
    $count++;
}
foreach (['DATA_MODEL','INTERFACES','REBUILD','PROGRAMMDOKUMENTATION','WORKFLOW','REQUIREMENTS','TESTING','DOCUMENTATION_AUDIT'] as $name) {
    if (!is_file($root.'/docs/jobsearch/'.$name.'.md')) { throw new RuntimeException('Missing rebuilding document '.$name); }
}
echo $count.' Markdown files and '.$links." local links checked\n";
