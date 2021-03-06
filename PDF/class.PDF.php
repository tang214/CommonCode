<?php
namespace PDF;

require(dirname(__FILE__).'/../vendor/autoload.php');

class PDF
{
    private $mpdf;

    function __construct()
    {
        $this->mpdf = new \mPDF('', 'Letter', '', '', 5, 5);
    }

    public function setPDFFromHTML($html)
    {
        $this->mpdf->WriteHTML($html);
    }

    public function toPDFBuffer()
    {
        return $this->mpdf->Output('', 'S');
    }

    public function toPDFFile($filename)
    {
        return $this->mpdf->Output($filename);
    }
}
