<?php

require_once 'fpdf.php';
require_once 'fpdi.php';

//$split_options:
//array(array('sub_pdf_name','startpage','pagenumber'))
function splitpdf($sourcefile, $save_dir, $split_options) {
    for ($i = 0; $i < count($split_options); $i++) {
        $pdf = & new FPDI();
        // set  sourcefile
        $pdf->setSourceFile($sourcefile);
        $pagenumber = $split_options[$i][2];
        if ($pagenumber == null)
            $pagenumber = $pdf->current_parser->page_count - $split_options[$i][1] + 1;
        for ($j = 0; $j <= $pagenumber - 1; $j++) {
            $pdf->AddPage();
            $tplIdx = $pdf->importPage($j + $split_options[$i][1]);
            $pdf->useTemplate($tplIdx/* , 0, 14, 210 */);
        }
        $pdf->Output($save_dir . '/' . $split_options[$i][0]);
        $pdf->Close();
    }
}

function get_page_count($sourcefile) {
    $pdf = & new FPDI();
    $pdf->setSourceFile($sourcefile);
    $page_count = $pdf->current_parser->page_count;
    $pdf->Close();
    return $page_count;
}

//function download($sourcefile) {
//    header('Content-type: application/pdf');
//    header('Content-Disposition: attachment;filename=a.pdf');
//    $pdf = & new FPDI();
//    // set  sourcefile
//    $pdf->setSourceFile($sourcefile);
//    $aa = $pdf->current_parser->page_count;
//    for ($i = 1; $i <= $pdf->current_parser->page_count; $i++) {
//        $pdf->AddPage();
//        $tplIdx = $pdf->importPage($i);
//        $pdf->useTemplate($tplIdx);
//    }
//    $pdf->Output('download.pdf', true);
//    $pdf->Close();
//}

?>
