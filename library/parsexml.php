
<?php

    function parsexml($success_trans_files)
    {
        $error_info_list = array();
        $success_info_list = array();
        
        foreach($success_trans_files as $xml_file)
        {
            
        }
       
         return $claims_info;    
    }
 
   
    /*
$sql_parameter = array();

$xml = simplexml_load_file('sample5.xml');
$trac_xml = $xml->interchange->group->transaction;


$first_flag = 0;

foreach($trac_xml->loop as $a)
{
	if($a['Id'] == "1000" && $first_flag == 0)
	{
		$first_loop_1000 = $a;
		$first_flag = 1;
	}
	else if($a['Id'] == "1000" && $first_flag == 1)
     {
		$sec_loop_1000 = $a;
		$first_flag = 2;
	}
	else if($a['Id'] == "2000" )
		$loop_2000 = $a;
}


foreach($sec_loop_1000->segment as $a)
{
	if($a['Id'] == "REF")
		$tmp_seg = $a;
}

foreach($tmp_seg->element as $a)
{
	if($a['Id'] == "REF02")
		$tmp_element = $a;
}

$sql_provider_taxid = $tmp_element;


$index = 0;
foreach($loop_2000->loop as $a)
{
	if($a['Id'] == "2100")
	{
		$sql_parameter[$index]['provider_taxid'] = $sql_provider_taxid;
		foreach($a->segment as $b)
		{
			if($b['Id'] == "CLP")
			{
				foreach($b->element as $c)
				{
					if($c['Id'] == "CLP01")
					{
						$sql_parameter[$index]['patient_mrn'] = $c;						
					}
					
					if($c['Id'] == "CLP03")
					{
						$sql_parameter[$index]['total_charge'] = $c;
						break;
					}				
				}
				break;
			}
			
		}
		
		foreach($a->loop as $b)
		{
			if($b['Id'] == "2110")
			{
				foreach($b->segment as $c)
				{
					if($c['Id'] == "SVC")
					{
						foreach($c->element as $d)
						{
							if($d['Id'] = "SVC01")
							{
								$sql_parameter[$index]['code_set'] = $d;
								break;
							}
						}
					}
					
					
					if($c['Id'] == "DTM")
					{
						foreach($c->element as $d)
						{
							if($d['Id'] = "DTM03")
							{
								$sql_parameter[$index]['date_of_service'] = $d;
								break;
							}
						}
						break;
					}
					
				}
				break;
			}
		}
		$index = $index + 1;	
	}
}

echo $sql_parameter[0]['provider_taxid']."<br/>";
echo $sql_parameter[0]['date_of_service']."<br/>";
echo $sql_parameter[0]['code_set']."<br/>";
echo $sql_parameter[0]['patient_mrn']."<br/>";
echo $sql_parameter[0]['total_charge']."<br/>";

return;

/*
foreach($tmp_seg->segment as $a)
{
	if($a->attributes() == "REF02")
	{
		$provider_taxid = $a;
		print_r($a);
		return;
	}
}

*/
    
 
    ?>