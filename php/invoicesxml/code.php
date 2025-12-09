<?php
// Enter your code here, enjoy!

$xml=file_get_contents("63bcf261544054.90905546.daemons.483.xml");

$doc = new DOMDocument();
$doc->loadXML($xml);
echo $doc->getElementsByTagName('CbteTipo')->item(0)->nodeValue;
die;