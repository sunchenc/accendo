<?php

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
require_once 'helper.php';
require_once 'claim.php';
require_once 'fpdf.php';
require_once 'fpdi.php';
class appeal{

    protected $appeal_type;
    protected $provider_db;
    protected $patient_db;
    protected $insured_db;
    protected $insurance_db;
    protected $encounter_db;
    protected $claim_db;
    protected $billingcompany_id;
    protected $sysdoc_path;


    public function appeal($provider_db,$patient_db,$insured_db,$insurance_db,$encounter_db,$claim_db,$billingcompany_id) {
        $this->claim_db=$claim_db;
        $this->encounter_db=$encounter_db;
        $this->insured_db=$insured_db;
        $this->insurance_db=$insurance_db;
        $this->patient_db=$patient_db;
        $this->provider_db=$provider_db;
        $this->billingcompany_id=$billingcompany_id;
    }
    
    //generate PIPAppeal pdf
    public function gen_PIPAppeal($pagecount){
        $pdf = & new FPDI();
        $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
        $this->sysdoc_path = '../../' . $config->dir->document_root;        
        $pdf_dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/appealtemplate';
        
        $pdf->setSourceFile($pdf_dir . '/PIPAppeal.pdf');
        $pdf->AddPage();
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, 0, 14, 210);
        $LargeFontSize = 12;
        $NormalFontSize = 10;
        $SmallFontSize = 8;
        $LineSpacing = 10;
        $left = 60;

        $Offset1 = 1;
        $Offset2 = 1;
        $startline = 88;

    //if-image
        //   $pdf->Image('images/insurance.png', 84, 36, 45);
        $pdf->SetFont('Arial', "B", 26);
        $pdf->SetXY(20, 35);
        //  $pdf->Write(0, $claim->billingcompany_name());
        //$pdf->Multicell(180, 10, $claim->billingcompany_name(), '', 'C');
        $pdf->Multicell(180, 10, $this->provider_db['billing_provider_name'], '', 'C');
        $pdf->SetFont('Arial', "", $NormalFontSize);
        $tmp = $this->provider_db['billing_street_address'] . ' ' . $this->provider_db['billing_city'] . ' '
                . $this->provider_db['billing_state'] . ' ' . zip_format($this->provider_db['billing_zip']);
        $pdf->SetXY(75, 50);
        $pdf->Write(0, $tmp);
        //phone
        $billing_phone = '';
        $billing_fax = '';
        $billing_contact = '';
        if ($this->provider_db['billing_phone_number']) {
            $billing_phone = phone_format($this->provider_db['billing_phone_number']);
            $billing_contact = $billing_contact.'Tel:' . $billing_phone;
        }
        if ($this->provider_db['billing_fax']) {
            $billing_fax = phone_format($this->provider_db['billing_fax']);
            $billing_contact = $billing_contact.' Fax:' . $billing_fax;
        }
        $pdf->SetXY(78, 54);
        $pdf->Write(0, $billing_contact);
        $pdf->SetFont('Arial', "", $NormalFontSize);
        
        $pdf->SetXY($left-18, $startline + 1);
        $pdf->Write(0, date('m/d/Y'));

        $pdf->SetXY($left-5, $startline + $LineSpacing+0.5);
        $pdf->Write(0, $this->patient_db['last_name'].', '.$this->patient_db['first_name']);
        $pdf->SetXY($left+96, $startline + $LineSpacing+0.5);
        $pdf->Write(0, $this->insured_db['ID_number']);
        //$tmp = $claim->patientLastName() . ', ' . $claim->patientFirstName() . '/' . $claim->policyNumber();
        $pdf->SetXY($left+3, $startline + $LineSpacing*2);
        //$pdf->Write(0, $tmp);
        $pdf->Write(0, format($this->encounter_db['start_date_1'], 1));
        $pdf->SetXY($left+95, $startline + $LineSpacing*2);
        $pdf->Write(0, $this->claim_db['total_charge']);
        return $pdf;
    }
    //generate PIPFeeAppeal pdf
    public function gen_PIPFeeAppeal($pagecount){
        $pdf = & new FPDI();
        $config = new Zend_Config_Ini('../application/configs/application.ini', 'staging');
        $this->sysdoc_path = '../../' . $config->dir->document_root;        
        $pdf_dir = $this->sysdoc_path . '/' . $this->billingcompany_id . '/appealtemplate';
        
        $pdf->setSourceFile($pdf_dir . '/PIPFeeAppeal.pdf');
        $pdf->AddPage();
        $tplIdx = $pdf->importPage(1);
        $pdf->useTemplate($tplIdx, 0, 14, 210);
        $LargeFontSize = 12;
        $NormalFontSize = 10;
        $SmallFontSize = 8;
        $LineSpacing = 25;
        $left = 60;

        $startline = 92;

    //if-image
        //   $pdf->Image('images/insurance.png', 84, 36, 45);
        $pdf->SetFont('Arial', "B", 26);
        $pdf->SetXY(20, 35);
        //  $pdf->Write(0, $claim->billingcompany_name());
        //$pdf->Multicell(180, 10, $claim->billingcompany_name(), '', 'C');
        $pdf->Multicell(180, 10, $this->provider_db['billing_provider_name'], '', 'C');
        $pdf->SetFont('Arial', "", $NormalFontSize);
        $tmp = $this->provider_db['billing_street_address'] . ' ' . $this->provider_db['billing_city'] . ' '
                . $this->provider_db['billing_state'] . ' ' . zip_format($this->provider_db['billing_zip']);
        $pdf->SetXY(75, 50);
        $pdf->Write(0, $tmp);
        //phone
        $billing_phone = '';
        $billing_fax = '';
        $billing_contact = '';
        if ($this->provider_db['billing_phone_number']) {
            $billing_phone = phone_format($this->provider_db['billing_phone_number']);
            $billing_contact = $billing_contact.'Tel:' . $billing_phone;
        }
        if ($this->provider_db['billing_fax']) {
            $billing_fax = phone_format($this->provider_db['billing_fax']);
            $billing_contact = $billing_contact.' Fax:' . $billing_fax;
        }
        $pdf->SetXY(78, 54);
        $pdf->Write(0, $billing_contact);
        $pdf->SetFont('Arial', "B", $LargeFontSize);
        
//        $pdf->SetXY($left-10, $startline + 1);
//        $pdf->Write(0, date('m/d/Y'));

        $pdf->SetXY($left+33, $startline+1);
        $pdf->Write(0, $this->patient_db['last_name'].', '.$this->patient_db['first_name']);
        
        $pdf->SetXY($left-15, $startline + $LineSpacing+9);        
        $pdf->Write(0, phone_format($this->insurance_db['fax_number']));
        
        $pdf->SetXY($left+50, $startline + $LineSpacing*2+0.5);
        $pdf->Write(0, format($this->encounter_db['start_date_1'], 1));
        
        $pdf->SetXY($left+98, $startline + $LineSpacing*2+0.5);
        $pdf->Write(0, $this->encounter_db['days_or_units_1']);
        
        $pdf->SetXY($left+10, $startline + $LineSpacing*3+0.5);
        $pdf->Write(0, $this->claim_db['expected_payment']);

        return $pdf;
    }
}

