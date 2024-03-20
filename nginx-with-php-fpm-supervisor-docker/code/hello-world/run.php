<?php
 
$i = 0;
$file = fopen('/var/www/html/hello-world/hello.log', 'w');
 
while (1) {
    $output = $i.' - Hello @ '.date('H:i:s').PHP_EOL;
    
    // Write to file
    fwrite($file, $output);


    $output = $i.' - Hello STDOUT @ '.date('H:i:s').PHP_EOL;

    // Write to stdout
    fwrite(STDOUT, $output);

    sleep(2);
 
    $i++;
}
