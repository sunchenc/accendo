<?php
function gen_claimcorr($filepath,$root_dir, $billingcompany_id,$provider_id,$claim_id,$template){
            $claim_dir = $root_dir . '/' . $billingcompany_id; 
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }
            $claim_dir = $claim_dir . '/' . $provider_id;
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }
            $claim_dir = $claim_dir . '/' . 'claim';
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }
            $claim_dir = $claim_dir . '/' . $claim_id;
            if (!is_dir($claim_dir)) {
                mkdir($claim_dir);
            }
            
            
            
            $time = date("YmdHis");
            $type = 'PCorr_'.$template;
//            $source = 'Patient';
            $user = Zend_Auth::getInstance()->getIdentity();
            $username = $user->user_name;
            $tempfile = $claim_dir . '/'.  $time . '-'.$type.'-Patient-' . $username . '.docx';
            
            
            if(copy($filepath, $tempfile)){
//                 echo 'success';
                 return TRUE;
            }else{
//                 echo 'copy file failed';
                 return FALSE;
            }
            
}
