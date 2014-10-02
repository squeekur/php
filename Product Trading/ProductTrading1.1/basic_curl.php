<?php

# intialize a session with URL you want to scrape
# $ch is the  handle of the cURL object and you will need it to setup the necessary parameters according
# to your task
$ch = curl_init("http://example.com");

# you probably want to save the scraped web page.
# here we want to save the page to a file named "example_homepage.html". 
# The first argument of fopen is the file name and the second is the operation mode which could be:
# "r" : read only mode, file must exist
# "w": writing mode, file will be created if not exist, otherwise, overwrite the content of existing file!
$fp = fopen("example_homepage.html", "w");

# tell curl to save the page to the file we created
curl_setopt($ch, CURLOPT_FILE, $fp);

# indicate whether only the header of the http response is desired. 0 means no and 1 for yes.
# As we want to get the whole page, so 0 is supplied.
curl_setopt($ch, CURLOPT_HEADER, 0);

# do the request!
curl_exec($ch);

# release the resource
curl_close($ch);

# close the file
fclose($fp);

?>

