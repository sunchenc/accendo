<?php

require_once('fpdf.php');

class PDF_MySQL_Table extends FPDF {

    var $ProcessingTable = false;
    var $aCols = array();
    var $TableX;
    var $HeaderColor;
    var $RowColors;
    var $ColorIndex;

    function Header() {
        //Print the table header if necessary
        if ($this->ProcessingTable)
            $this->TableHeader();
    }

    function TableHeader($header, $font_size) {
        $this->SetFont('Arial', 'B', $font_size);
        $this->SetX($this->TableX);
        $fill = !empty($this->HeaderColor);
        if ($fill)
            $this->SetFillColor($this->HeaderColor[0], $this->HeaderColor[1], $this->HeaderColor[2]);
        //muti rows
        $row_count = 1;
        foreach ($this->aCols as $col) {
            $tmp = substr_count($header[$col['c']], '<br>') + 1;
            if ($row_count < $tmp)
                $row_count = $tmp;
        }
        if ($row_count == 1) {
            foreach ($this->aCols as $col)
                $this->Cell($col['w'], 6, $header[$col['c']], 1, 0, 'C', $fill);
            $this->Ln();
        }
        if ($row_count > 1) {
            $headers = array();
            foreach ($this->aCols as $col) {
                $headers[$col['c']] = explode('<br>', $header[$col['c']]);
            }
            for ($i = 0; $i < $row_count; $i++) {
                $this->SetX($this->TableX);
                foreach ($this->aCols as $col) {
                    $str = $headers[$col['c']][$i];
                    if (is_null($str))
                        $str = '';
                    $border='LTR';
                    switch ($i) {
                        case 0:
                          $border='LTR';
                            break;
                        case $row_count - 1:
                           $border='LBR';
                            break;
                        default:
                            $border='LR';
                            break;
                    }
                     $this->Cell($col['w'], 4, $str, $border, 0, 'C', $fill);
                }
                $this->Ln();
            }
        }
    }

    function Row($data) {
        $this->SetX($this->TableX);
        $ci = $this->ColorIndex;
        $fill = !empty($this->RowColors[$ci]);
        if ($fill)
            $this->SetFillColor($this->RowColors[$ci][0], $this->RowColors[$ci][1], $this->RowColors[$ci][2]);
        foreach ($this->aCols as $col) {
            $this->Cell($col['w'], 5, $data[$col['c']], 1, 0, $col['a'], $fill);
        }
        $this->Ln();
        $this->ColorIndex = 1 - $ci;
    }

    function CalcWidths($width, $align) {
        //Compute the widths of the columns
        $TableWidth = 0;
        foreach ($this->aCols as $i => $col) {
            $w = $col['w'];
            if ($w == -1)
                $w = $width / count($this->aCols);
            elseif (substr($w, -1) == '%')
                $w = $w / 100 * $width;
            $this->aCols[$i]['w'] = $w;
            $TableWidth+=$w;
        }
        //Compute the abscissa of the table
        if ($align == 'C')
            $this->TableX = max(($this->w - $TableWidth) / 2, 0);
        elseif ($align == 'R')
            $this->TableX = max($this->w - $this->rMargin - $TableWidth, 0);
        else
            $this->TableX = $this->lMargin;
    }

    function AddCol($width=-1, $field=-1, $caption='', $align='C') {
        //Add a column to the table
        if ($field == -1)
            $field = count($this->aCols);
        $this->aCols[] = array('f' => $field, 'c' => $caption, 'w' => $width, 'a' => $align);
    }

    function Table($rows, $options) {
        $fields = $options['fields'];
        $header = $options['header'];
        $font_size = $options['font_size'];
        $width = $options['width'];
        //Add all columns if none was specified
        if (count($this->aCols) == 0) {
            $nb = count($fields);
            for ($i = 0; $i < $nb; $i++)
                $this->AddCol($width[$i]);
        }
        //Retrieve column names when not specified
        foreach ($this->aCols as $i => $col) {
            if ($col['c'] == '') {
                if (is_string($col['f']))
                    $this->aCols[$i]['c'] = ($col['f']);
                else
                    $this->aCols[$i]['c'] = ($fields[$col['f']]);
            }
        }
        //Handle properties
        if (!isset($prop['width']))
            $prop['width'] = 0;
        if ($prop['width'] == 0)
            $prop['width'] = $this->w - $this->lMargin - $this->rMargin;
        if (!isset($prop['align']))
            $prop['align'] = 'C';
        if (!isset($prop['padding']))
            $prop['padding'] = $this->cMargin;
        $cMargin = $this->cMargin;
        $this->cMargin = $prop['padding'];
        if (!isset($prop['HeaderColor']))
            $prop['HeaderColor'] = array();
        $this->HeaderColor = $prop['HeaderColor'];
        if (!isset($prop['color1']))
            $prop['color1'] = array();
        if (!isset($prop['color2']))
            $prop['color2'] = array();
        $this->RowColors = array($prop['color1'], $prop['color2']);
        //Compute column widths
        $this->CalcWidths($prop['width'], $prop['align']);
        //Print header
        $this->TableHeader($header, $font_size['header']);
        //Print rows
        $this->SetFont('Arial', '', $font_size['body']);
        $this->ColorIndex = 0;
        $this->ProcessingTable = true;
        foreach ($rows as $row) {
            $this->Row($row);
        }
//	while($row=mysql_fetch_array($res))
//		$this->Row($row);
        $this->ProcessingTable = false;
        $this->cMargin = $cMargin;
        $this->aCols = array();
    }

}

?>
