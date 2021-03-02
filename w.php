<?php
require __DIR__ . '/vendor/autoload.php';
use Mike42\Wikitext\WikitextParser;

$wiki = $_GET["w"];

$wiki_apis = json_decode(file_get_contents("https://wiki.riverside.rocks/api.php?action=query&prop=revisions&titles=${wiki}&rvslots=*&rvprop=content&format=json"), true);

$r = 0;
while(true)
{
    if($wiki_apis["query"]["pages"][$r]["revisions"]["0"]["slots"]["main"]["*"] == "")
    {
        $r = $r +1;
    }
    else
    {
        break;
    }
}
$wiki_content = $wiki_apis["query"]["pages"][$r]["revisions"]["0"]["slots"]["main"]["*"];

/* The most rudimentary way to invoke the parser */
$parser = new WikitextParser($wiki_content);
$output = $parser -> result;

echo $output;