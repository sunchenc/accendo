<?php

require('fpdf.php');
define('EURO', chr(128));
define('EURO_VAL', 6.55957);

class PDF_Invoice extends FPDF {

// private variables
    var $colonnes;
    var $format;
    var $angle = 0;

// private functions
    function RoundedRect($x, $y, $w, $h, $r, $style = '') {
        $k = $this->k;
        $hp = $this->h;
        if ($style == 'F')
            $op = 'f';
        elseif ($style == 'FD' || $style == 'DF')
            $op = 'B';
        else
            $op = 'S';
        $MyArc = 4 / 3 * (sqrt(2) - 1);
        $this->_out(sprintf('%.2F %.2F m', ($x + $r) * $k, ($hp - $y) * $k));
        $xc = $x + $w - $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - $y) * $k));

        $this->_Arc($xc + $r * $MyArc, $yc - $r, $xc + $r, $yc - $r * $MyArc, $xc + $r, $yc);
        $xc = $x + $w - $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', ($x + $w) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc + $r, $yc + $r * $MyArc, $xc + $r * $MyArc, $yc + $r, $xc, $yc + $r);
        $xc = $x + $r;
        $yc = $y + $h - $r;
        $this->_out(sprintf('%.2F %.2F l', $xc * $k, ($hp - ($y + $h)) * $k));
        $this->_Arc($xc - $r * $MyArc, $yc + $r, $xc - $r, $yc + $r * $MyArc, $xc - $r, $yc);
        $xc = $x + $r;
        $yc = $y + $r;
        $this->_out(sprintf('%.2F %.2F l', ($x) * $k, ($hp - $yc) * $k));
        $this->_Arc($xc - $r, $yc - $r * $MyArc, $xc - $r * $MyArc, $yc - $r, $xc, $yc - $r);
        $this->_out($op);
    }

    function _Arc($x1, $y1, $x2, $y2, $x3, $y3) {
        $h = $this->h;
        $this->_out(sprintf('%.2F %.2F %.2F %.2F %.2F %.2F c ', $x1 * $this->k, ($h - $y1) * $this->k, $x2 * $this->k, ($h - $y2) * $this->k, $x3 * $this->k, ($h - $y3) * $this->k));
    }

    function Rotate($angle, $x = -1, $y = -1) {
        if ($x == -1)
            $x = $this->x;
        if ($y == -1)
            $y = $this->y;
        if ($this->angle != 0)
            $this->_out('Q');
        $this->angle = $angle;
        if ($angle != 0) {
            $angle*=M_PI / 180;
            $c = cos($angle);
            $s = sin($angle);
            $cx = $x * $this->k;
            $cy = ($this->h - $y) * $this->k;
            $this->_out(sprintf('q %.5F %.5F %.5F %.5F %.2F %.2F cm 1 0 0 1 %.2F %.2F cm', $c, $s, -$s, $c, $cx, $cy, -$cx, -$cy));
        }
    }

    function _endpage() {
        if ($this->angle != 0) {
            $this->angle = 0;
            $this->_out('Q');
        }
        parent::_endpage();
    }

// public functions
    function sizeOfText($texte, $largeur) {
        $index = 0;
        $nb_lines = 0;
        $loop = TRUE;
        while ($loop) {
            $pos = strpos($texte, "\n");
            if (!$pos) {
                $loop = FALSE;
                $ligne = $texte;
            } else {
                $ligne = substr($texte, $index, $pos);
                $texte = substr($texte, $pos + 1);
            }
            $length = floor($this->GetStringWidth($ligne));
            $res = 1 + floor($length / $largeur);
            $nb_lines += $res;
        }
        return $nb_lines;
    }

// Provider
    function addProvider($nom, $adresse) {
        $x1 = 10;
        $y1 = 14;
        $this->SetXY($x1, $y1);
        $this->SetFont('Arial', 'B', 12);
        $length = $this->GetStringWidth($nom);
        $this->Cell($length, 2, $nom);
        $this->SetXY($x1, $y1 + 4);
        $this->SetFont('Arial', '', 10);
        $length = $this->GetStringWidth($adresse);
        //Coordonn�es de la soci�t�
        $lignes = $this->sizeOfText($adresse, $length);
        $this->MultiCell($length, 4, $adresse);
    }

    function Notice($notice) {
        $r1 = $this->w - 120;
        $r2 = $r1 + 110;
        $y1 = 14;
        $y2 = $y1 + 8;
        $mid = ($r1 + $r2 ) / 2;

        $this->SetLineWidth(0.1);
        $this->SetFillColor(192);
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'DF');
        $this->SetXY($r1 + 1, $y1 + 2);
        $this->SetFont("Arial", "B", 14);
        $this->Cell($r2 - $r1 - 1, 5, $notice, 0, 0, "C");
    }

    function addStatementDate($page) {
        $r1 = $this->w - 120;
        $r2 = $r1 + 38;
        $y1 = 24;
        $y2 = $y1 + 12;
        $mid = ($y1 + $y2) / 2;
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 3.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 2);
        $this->SetFont("Arial", "B", 10);
        $this->Cell(10, 5, "STATEMENT DATE", 0, 0, "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 7);
        $this->SetFont("Arial", "", 10);
        $this->Cell(10, 5, $page, 0, 0, "C");
    }

    function addDueDate($date) {
        $r1 = $this->w - 82;
        $r2 = $r1 + 38;
        $y1 = 24;
        $y2 = $y1 + 12;
        $mid = ($y1 + $y2) / 2;
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 3.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 2);
        $this->SetFont("Arial", "B", 10);
        $this->Cell(10, 5, "DUE DATE", 0, 0, "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 7);
        $this->SetFont("Arial", "", 10);
        $this->Cell(10, 5, $date, 0, 0, "C");
    }

    function addAcctNum($ref) {
        $r1 = $this->w - 44;
        $r2 = $r1 + 34;
        $y1 = 24;
        $y2 = $y1 + 12;
        $mid = ($y1 + $y2) / 2;
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 3.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 2);
        $this->SetFont("Arial", "B", 10);
        $this->Cell(10, 5, "ACCT NUMBER", 0, 0, "C");
        $this->SetXY($r1 + ($r2 - $r1) / 2 - 5, $y1 + 7);
        $this->SetFont("Arial", "", 10);
        $this->Cell(10, 5, $ref, 0, 0, "C");
    }

    function addClientAdresse($adresse) {
        $r1 = 20;
        $r2 = $r1 + 68;
        $y1 = 62;
        $this->SetXY($r1, $y1);
        $this->MultiCell(60, 4, $adresse);
    }

    function addSendTo($tva) {
        $this->SetFont("Arial", "B", 10);
        $r1 = $this->w - 103;
        $r2 = $r1 + 75;
        $y1 = 38;
        $y2 = $y1 + 38;
        $mid = $y1 + 8;
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + 16, $y1 + 2);
        $this->Cell(40, 5, "Make Check Payable To", '', '', "C");
        $this->SetFont("Arial", "", 10);
        $this->SetXY($r1+10, $y1 + 2);
        $this->MultiCell(80, 5, $tva);
    }

    function addVertLine($x1, $y1, $x2, $y2) {
        $this->SetLineWidth(0.4);
        $this->line($x1, $y1, $x2, $y2);
    }

    function addRemark($notes, $addnotes) {
        $this->SetFont("Arial", "B", 12);
        $r1 = 10;
        $r2 = $r1 + 140;
        $y1 = 224;
        $y2 = $y1 + 50;
        $mid = $y1 + 6;
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + 6, $y1 + 1);
        $this->Cell(40, 4, "Remark", '', '', "L");
        $this->SetFont("Arial", "", 10);
        $this->SetXY($r1 + 6, $y1 + 8);
        $this->MultiCell(130, 4, $notes);
        $this->SetXY($r1 + 6, $y1 + 34);
        $this->Cell(130, 1, $addnotes);
    }

    function addAmountDue($due) {
        $this->SetFont("Arial", "B", 10);
        $r1 = $this->w - 50;
        $r2 = $r1 + 40;
        $y1 = 224;
        $y2 = $y1 + 10;
        $mid = $y1 + 5;
        $this->RoundedRect($r1, $y1, ($r2 - $r1), ($y2 - $y1), 2.5, 'D');
        $this->Line($r1, $mid, $r2, $mid);
        $this->SetXY($r1 + 16, $y1 + 1);
        $this->Cell(10, 4, "Amount Due", '', '', "C");
        $this->SetFont("Arial", "", 10);
        $this->SetXY($r1 + 16, $y1 + 5);
        $this->Cell(10, 5, $due, 0, 0, "C");
    }

// add a watermark (temporary estimate, DUPLICATA...)
// call this method first
    function WaterMark($texte) {
        $this->SetFont('Arial', 'B', 50);
        $this->SetTextColor(203, 203, 203);
        $this->Rotate(45, 55, 190);
        $this->Text(55, 190, $texte);
        $this->Rotate(0);
        $this->SetTextColor(0, 0, 0);
    }

    function addCols($tab) {
        global $colonnes;
        $r1 = 10;
        $r2 = $this->w - ($r1 * 2);
        $y1 = 90;
        $y2 = $this->h - 60 - $y1;
        $this->SetXY($r1, $y1);
        $this->Rect($r1, $y1, $r2, $y2, "D");
        $this->Line($r1, $y1 + 6, $r1 + $r2, $y1 + 6);

        $colX = $r1;
        $colonnes = $tab;
        while (list( $lib, $pos ) = each($tab)) {
            $this->SetXY($colX, $y1 + 2);
            $this->Cell($pos, 1, $lib, 0, 0, "C");
            if($colX+$pos<$r2){
                $colX += $pos;
            }
            
            $this->Line($colX, $y1, $colX, $y1 + $y2);
        }
    }

    /*
      function addFollowPageCols($tab);
     * for the more ledger data;add another page,to refine the format of the table just adjust the variable $y1 and $y2
     * and also set the Font.
     */

    function addFollowPageCols($tab) {
        global $colonnes;

        $this->SetFont("Arial", "", 10);
        $r1 = 10;
        $r2 = $this->w - ($r1 * 2);
        $y1 = 20;
        $y2 = $this->h - 20 - $y1;
        $this->SetXY($r1, $y1);
        $this->Rect($r1, $y1, $r2, $y2, "D");
        $this->Line($r1, $y1 + 6, $r1 + $r2, $y1 + 6);

        $colX = $r1;
        $colonnes = $tab;
        while (list( $lib, $pos ) = each($tab)) {
            $this->SetXY($colX, $y1 + 2);
            $this->Cell($pos, 1, $lib, 0, 0, "C");
            $colX += $pos;
            $this->Line($colX, $y1, $colX, $y1 + $y2);
        }
    }

    function addLineFormat($tab) {
        global $format, $colonnes;

        while (list( $lib, $pos ) = each($colonnes)) {
            if (isset($tab["$lib"]))
                $format[$lib] = $tab["$lib"];
        }
    }

    function lineVert($tab) {
        global $colonnes;

        reset($colonnes);
        $maxSize = 0;
        while (list( $lib, $pos ) = each($colonnes)) {
            $texte = $tab[$lib];
            $longCell = $pos - 2;
            $size = $this->sizeOfText($texte, $longCell);
            if ($size > $maxSize)
                $maxSize = $size;
        }
        return $maxSize;
    }

    function addLine($ligne, $tab) {
        global $colonnes, $format;

        $ordonnee = 10;
        $maxSize = $ligne;

        reset($colonnes);
        while (list( $lib, $pos ) = each($colonnes)) {
            $longCell = $pos - 2;
            $texte = $tab[$lib];
            $length = $this->GetStringWidth($texte);
            //$tailleTexte = $this->sizeOfText( $texte, $length );
            $formText = $format[$lib];
            $this->SetXY($ordonnee, $ligne - 1);
            $this->MultiCell($longCell, 4, $texte, 0, $formText);
            if ($maxSize < ($this->GetY() ))
                $maxSize = $this->GetY();
            $ordonnee += $pos;
        }
        return ( $maxSize - $ligne );
    }

}

?>