<?php
 
/**************** Settings begin *************

 
$username          = 'ebs';  // Insert your InterFAX username here
$password          = 'ebs12345';  // Insert your InterFAX password here
$faxnumber         = '+16463951911';  // Enter the destination fax number here, e.g. +497116589658
$filename          = 'BlankCMS1500.pdf'; // A file in your filesystem
$filetype          = 'PDF'; // File format 
                   
 
/**************** Settings end ****************
 
// Open File
if( !($fp = fopen($filename, "r"))){
    // Error opening file
    echo "Error opening file";
    exit;
}

// Read data from the file into $data
$data = "";
while (!feof($fp)) $data .= fread($fp,1024);
fclose($fp);
 
$client = new SoapClient("http://ws.interfax.net/dfs.asmx?WSDL");
 
$params->Username  = $username;
$params->Password  = $password;
$params->FaxNumber = $faxnumber;
$params->FileData  = $data;
$params->FileType  = $filetype;
 
$result = $client->Sendfax($params);
echo $result->SendfaxResult; // returns the transactionID if successful
                             // or a negative number if otherwise
 * 
 * 
 */

function send_fax($file_name, $fax_number)
{
    $fax_number        =   '+1'.$fax_number; 
    //$fax_number        =  '+16463951911';
    
    $username          = 'ebs';  // Insert your InterFAX username here
    $password          = 'ebs12345';  // Insert your InterFAX password here
    $faxnumber         = $fax_number;  // Enter the destination fax number here, e.g. +497116589658
    $filename          = $file_name; // A file in your filesystem
    $filetype          = 'PDF'; // File format 
    
    
    // Open File
if( !($fp = fopen($filename, "r"))){
    // Error opening file
    echo "Error opening file";
    exit;
}

// Read data from the file into $data
$data = "";
while (!feof($fp))
{
    $temp_data = fread($fp,1024);
    $data = $data."".$temp_data;
}
fclose($fp);
 
$client = new SoapClient("http://ws.interfax.net/dfs.asmx?WSDL");
 
$params->Username  = $username;
$params->Password  = $password;
$params->FaxNumber = $faxnumber;
$params->FileData  = $data;
$params->FileType  = $filetype;
 
$result = $client->Sendfax($params);
//echo $result->SendfaxResult; // returns the transactionID if successful
                             // or a negative number if otherwise
return $result->SendfaxResult;
}
?>