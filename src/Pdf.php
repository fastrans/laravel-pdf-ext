<?php
namespace Fastrans\LaravelPdfExt;

use App;
use Storage;

class Pdf
{
    private $sourceBinData;

    private $sourceLocalFilePath;

    public $id;

    public function __construct()
    {

    }

    public function __debugInfo()
    {
        return [
            'sourceBinData' => ($this->sourceBinData) ? strlen($this->sourceBinData).' bytes.' : false,
            'sourceLocalFilePath' => $this->sourceLocalFilePath,
            'id' => $this->id,
        ];
    }

    // Input options
    public static function fromBinary($data)
    {
        $p = new self();
        $p->setBinary($data);

        return $p;
    }

    public function setBinary($data)
    {
        $this->sourceBinData = $data;
    }

    public static function fromFilePath($filepath)
    {
        $p = new self();
        $p->setFilePath($filepath);

        return $p;
    }

    public function setFilePath($filepath)
    {
        $this->sourceBinData = file_get_contents($filepath);
        $this->sourceLocalFilePath = $filepath;
    }

    public static function fromDiskAndPath($disk, $path)
    {
        $p = new self();
        $p->setDiskAndPath($disk, $path);

        return $p;
    }

    public function setDiskAndPath($disk, $path)
    {
        if (! Storage::disk($disk)->exists($path) && App::environment(['local', 'dev'])) {
            $this->sourceBinData = file_get_contents(resource_path('donkey.pdf'));
        } else {
            $this->sourceBinData = Storage::disk($disk)->get($path);
        }

    }

    public static function fromHtml($html)
    {
        $p = new self();
        $p->setHtml($html);

        return $p;
    }

    public function setHtml($html)
    {
        $resolveUri = \Typesetsh\UriResolver::all(null, getcwd());

        $pdf = \Typesetsh\createPdf($html, $resolveUri);
        //dd($pdf->issues);

        $this->sourceBinData = $pdf->asString();
    }

    public static function fromView($viewname, $data = [])
    {
        $p = new self();
        $p->setView($viewname, $data);

        return $p;
    }

    public function setView($viewname, $data = [])
    {

        $ret = view($viewname, $data);
        $html = $ret->render();
        $this->setHtml($html);

    }

    public static function fromMerge($pdf_arr)
    {
        if (! $pdf_arr) {
            throw new \Exception('Nothing passed in to merge.');
        }

        if (count($pdf_arr) < 1) {
            throw new \Exception('Nothing passed in to merge.');
        }

        $p = new self();
        $p->setMerge($pdf_arr);

        return $p;
    }

    public function setMerge($pdf_arr)
    {
        $input_files = [];
        foreach ($pdf_arr as $pdf) {
            if (is_string($pdf)) {
                $input_files[] = $pdf;
            } elseif ($pdf instanceof \App\Extensions\Pdf) {
                $input_files[] = $pdf->getLocalFilePath();
            } else {
                throw new \Exception('Unsupported pdf property passed: '.$pdf);
            }

        }

        $merged_pdf = new \mikehaertl\pdftk\Pdf($input_files);

        $outputfile = $this->getTempFile();
        $result = $merged_pdf->saveAs($outputfile);

        if (! $result) {
            throw new \Exception('Error saving merged PDF: '.$merged_pdf->getError());
        }

        $this->setFilePath($outputfile);

        return $this;
    }

    //Output options

    public function binary()
    {
        return $this->sourceBinData;
    }

    public function save($filepath)
    {
        return file_put_contents($filepath, $this->sourceBinData);
    }

    public function saveToDiskAndPath($disk, $path)
    {
        return Storage::disk($disk)->put($path, $this->sourceBinData);
    }

    public function stream()
    {
        return response($this->sourceBinData)->withHeaders([
            'Content-Type' => 'application/pdf',
        ]);
    }

    public function download($filename = null)
    {
        if (! $filename) {
            $filename = clean_uuid().'.pdf';
        }

        return response()->streamDownload(function () {
            echo $this->sourceBinData;
        }, $filename);

    }

    public function toTempFile($filename = null)
    {
        if (! $filename) {
            $filename = clean_uuid().'.pdf';
        }
        $filepath = config('app.pubtmp').DIRECTORY_SEPARATOR.$filename;
        $fileurl = config('app.pubtmp_url').DIRECTORY_SEPARATOR.$filename;

        $this->save($filepath);

        return [
            'path' => $filepath,
            'url' => $fileurl,
        ];
    }

    // Misc

    public function setID($val)
    {
        $this->id = $val;

        return $this;
    }

    public function getID()
    {
        return $this->id;
    }

    public function hasLocalFilePath()
    {
        if ($this->sourceLocalFilePath) {
            if (file_exists($this->sourceLocalFilePath)) {
                return true;
            }
        }

        return false;
    }

    public function getLocalFilePath()
    {
        if (! $this->hasLocalFilePath()) {
            $this->writeTempFile();
        }

        return $this->sourceLocalFilePath;
    }

    public function writeTempFile()
    {
        $tmp_fullpath = $this->getTempFile();
        $res = file_put_contents($tmp_fullpath, $this->sourceBinData);
        $this->sourceLocalFilePath = $tmp_fullpath;

        return $this;
    }

    private function getTempFile()
    {
        return tempnam(config('app.pubtmp'), 'pdftmp_').'.pdf';
    }

    public function addPDFPages($pdf_array)
    {

        if (! $this->hasLocalFilePath()) {
            $this->writeTempFile();
        }
        $input_files[] = $this->getLocalFilePath();

        if (! is_array($pdf_array)) {
            $pdf_array = [$pdf_array];
        }

        foreach ($pdf_array as $cur_pdf) {
            if ($cur_pdf instanceof self) {
                if (! $cur_pdf->hasLocalFilePath()) {
                    $cur_pdf->writeTempFile();
                }
                $input_files[] = $cur_pdf->getLocalFilePath();
            } elseif (is_string($cur_pdf)) {
                if (! file_exists($cur_pdf)) {
                    throw new \Exception("File could not be found at $cur_pdf");
                } else {
                    //string filepath
                    $input_files[] = $cur_pdf;
                }
            } else {
                throw new \Exception("added PDF must be either a string filepath, or an \App\Extensions\Pdf instance.");
            }
        }

        if (count($input_files) > 1) {

            $merged_pdf = new \mikehaertl\pdftk\Pdf($input_files);
            $outputfile = $this->getTempFile();
            $result = $merged_pdf->saveAs($outputfile);

            if (! $result) {
                var_dump($input_files);
                throw new \Exception('Error saving merged PDF: '.$merged_pdf->getError());
            }

            $this->setFilePath($outputfile);
        }

    }

    public function countPages()
    {
        if (! $this->hasLocalFilePath()) {
            $this->writeTempFile();
        }
        $pdf_path = $this->getLocalFilePath();

        $pdf = new \mikehaertl\pdftk\Pdf($pdf_path);
        $info = (array) $pdf->getData();

        return $info['NumberOfPages'];
    }
}
