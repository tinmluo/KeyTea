<?php

/**
 * @copyright Copyright &copy; Kartik Visweswaran, Krajee.com, 2015 - 2018
 * @package yii2-export
 * @version 1.3.4
 */

namespace kartik\export;

use kartik\mpdf\Pdf;
use PhpOffice\PhpSpreadsheet\Writer\Pdf\Mpdf;
use PhpOffice\PhpSpreadsheet\Worksheet\PageSetup;
use PhpOffice\PhpSpreadsheet\Exception as PhpSpreadsheetException;

/**
 * Krajee custom PDF Writer library based on MPdf
 *
 * @author Kartik Visweswaran <kartikv2@gmail.com>
 * @since 1.0
 */
class ExportWriterPdf extends Mpdf
{
    /**
     * @var string the exported output file name. Defaults to 'grid-export';
     */
    public $filename;

    /**
     * @var array kartik\mpdf\Pdf component configuration settings
     */
    public $pdfConfig = [];

    /**
     * @inheritdoc
     */
    protected function createExternalWriterInstance($config = [])
    {
        $config = array_replace_recursive($config, $this->pdfConfig);
        return new Pdf($config);
    }

    /**
     * Save Spreadsheet to file.
     *
     * @param string $pFilename Name of the file to save as
     *
     * @throws \PhpOffice\PhpSpreadsheet\Writer\Exception
     * @throws PhpSpreadsheetException
     */
    public function save($pFilename)
    {
        $fileHandle = parent::prepareForSave($pFilename);

        //  Default PDF paper size
        $paperSize = 'LETTER'; //    Letter    (8.5 in. by 11 in.)

        //  Check for paper size and page orientation
        if (null === $this->getSheetIndex()) {
            $orientation = ($this->spreadsheet->getSheet(0)->getPageSetup()->getOrientation()
                == PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
            $printPaperSize = $this->spreadsheet->getSheet(0)->getPageSetup()->getPaperSize();
        } else {
            $orientation = ($this->spreadsheet->getSheet($this->getSheetIndex())->getPageSetup()->getOrientation()
                == PageSetup::ORIENTATION_LANDSCAPE) ? 'L' : 'P';
            $printPaperSize = $this->spreadsheet->getSheet($this->getSheetIndex())->getPageSetup()->getPaperSize();
        }
        $this->setOrientation($orientation);

        //  Override Page Orientation
        if (null !== $this->getOrientation()) {
            $orientation = ($this->getOrientation() == PageSetup::ORIENTATION_DEFAULT)
                ? PageSetup::ORIENTATION_PORTRAIT
                : $this->getOrientation();
        }
        $orientation = strtoupper($orientation);

        //  Override Paper Size
        if (null !== $this->getPaperSize()) {
            $printPaperSize = $this->getPaperSize();
        }

        if (isset(self::$paperSizes[$printPaperSize])) {
            $paperSize = self::$paperSizes[$printPaperSize];
        }

        $properties = $this->spreadsheet->getProperties();

        //  Create PDF
        $pdf = $this->createExternalWriterInstance([
            'orientation' => $orientation,
            'methods' => [
                'SetTitle' => $properties->getTitle(),
                'SetAuthor' => $properties->getCreator(),
                'SetSubject' => $properties->getSubject(),
                'SetKeywords' => $properties->getKeywords(),
                'SetCreator' => $properties->getCreator(),
            ],
        ]);
        $ortmp = $orientation;
        $lib = $pdf->getApi();
        /** @noinspection PhpUndefinedMethodInspection */
        $lib->_setPageSize(strtoupper($paperSize), $ortmp);
        $lib->DefOrientation = $orientation;
        /** @noinspection PhpUndefinedMethodInspection */
        $lib->AddPage($orientation);
        $content = strtr($this->generateHTMLHeader(false) . $this->generateSheetData() . $this->generateHTMLFooter(), [
           '@page { margin-left: 0.7in; margin-right: 0.7in; margin-top: 0.75in; margin-bottom: 0.75in; }' => '',
           'body { margin-left: 0.7in; margin-right: 0.7in; margin-top: 0.75in; margin-bottom: 0.75in; }' => '',
        ]);
        //  Write to file
        fwrite($fileHandle, $pdf->Output($content, $this->filename, Pdf::DEST_STRING));
        parent::restoreStateAfterSave($fileHandle);
    }
}