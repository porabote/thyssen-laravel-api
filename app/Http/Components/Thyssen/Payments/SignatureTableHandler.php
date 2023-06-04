<?php

namespace App\Http\Components\Thyssen\Payments;

use Illuminate\Support\Facades\View;
use App\Models\Payments;
use App\Models\ApiUsers;
use App\Models\File;
use Porabote\Auth\Auth;
use Illuminate\Support\Facades\Storage;
use File as FileSystem;

class SignatureTableHandler
{
//    static function renderHtmlTable($paymentId)
//    {
//        try {
//
//        } catch (\App\Exceptions\ApiException $error) {
//            debug($error->getMessage());
//        }
//    }

    static function setCloneFacsimile($userId, $paymentId)
    {
        $sourceImg = self::getFacsimile($userId, 'random');

        if (!file_exists($sourceImg['path'])) {
            throw new \App\Exceptions\ApiException("Facsimile file was deleted.");
        };

        $directoryPath = storage_path() . '/payments/psps/';
        if (!FileSystem::exists($directoryPath)) {
            FileSystem::makeDirectory($directoryPath, 0777);
        }
        $destinationPath = $directoryPath . 'sign_' . $paymentId . '.png';
        FileSystem::copy($sourceImg['path'], $destinationPath);

        list($facsimile_w, $facsimile_h) = getimagesize($sourceImg['path']);

        if ($facsimile_h > 180) {
            exec('convert ' . $destinationPath . ' -resize 114 -background none   -quality 190 ' . $destinationPath);
        }
        
        return $destinationPath;

    }

    static function getFacsimile($id, $random = null)
    {
        $user = ApiUsers::with('facsimiles')->find($id)->toArray();

        $facsimiles = $user['facsimiles'];

        if (empty($facsimiles)) {
            throw new \App\Exceptions\ApiException("Facsimile wasn't loaded. Для выбранного лица факсимилье не загружены.");
        }

        if ($random == 'random') return $facsimiles[rand(0, count($facsimiles) - 1)];

        return $facsimiles;

    }

    static public function renderHtmlTable($userId, $data)
    {
        $user = ApiUsers::find($userId);
        $user['fio_en'] = \Porabote\Stringer\Stringer::transcript($user['name']);

        return View::make('payments.table_user_signatures', [
            'user' => $user,
            'status_name' => $data['status'],
            'date_accept' => $data['date'],
        ])->render();
    }

    static function createPdfImages($paymentId, $html, $data)
    {
        $directoryPath = storage_path() . '/payments/psps/pdf/';
        if (!FileSystem::exists($directoryPath)) {
            FileSystem::makeDirectory($directoryPath, 0777);
        }

        $pdfPath = $directoryPath . $paymentId . '_' . rand() .  '.pdf';

        self::htmlToPdf($html, [
            'path' => $pdfPath,
            'pageSize' => [
                'width' => 90
            ]
        ]);

        $firstImgInPdf = self::pdfToImg([
            'path' => $pdfPath,
            'ext' => 'png',
//            'record_id' => $data['paymentId'],
//            'parent_id' => $data['scanId'],
//            'label' => 'signInTable',
//            'module_alias' => 'Payments',
//            'plugin' => 'App',
            'pageSize' => [
                'width' => 400,
                'height' => ''
            ]
        ]);
        unlink($pdfPath);

        return $firstImgInPdf;
    }

    static function htmlToPdf($html, $options = [])
    {

        $pdfHandler = new \Mpdf\Mpdf;

        $options_default = [
            'pageSize' => [ 'width' => 210, 'height' => $pdfHandler->y]
        ];
        $options = array_merge($options_default, $options);

        $pdfHandler->WriteHTML($html);

        $pdfHandler->page   = 0;
        $pdfHandler->state  = 0;
        unset($pdfHandler->pages[0]);

        // The $p needs to be passed by reference
        $p = 'P';
        // debug($pdfHandler->y);

        $pdfHandler->_setPageSize([$options['pageSize']['width'], $pdfHandler->y], $p);

        $pdfHandler->addPage();
        $pdfHandler->WriteHTML($html);


        $pdfHandler->Output($options['path']);//, \Mpdf\Output\Destination::INLINE

    }

    static function pdfToImg($options = [])
    {
        $pdfHandler = new \Mpdf\Mpdf;
        //if($pdfHandler->y == 0) $this->_outputJSON(['error' => 'Данные пусты']);

        $options_default = [
            'pageSize' => [ 'width' => 1000, 'height' => $pdfHandler->y]
        ];
        $options = array_merge($options_default, $options);

        $imgsList = self::extractPagesAsImages($options);

        return $imgsList[0];
//        $imgsInfoList = [];
//        foreach($imgsList as $imgPath) {
//            $imgsInfoList[] = $filesObj->addMetaData($imgPath, [
//                'title' => '',
//                'dscr' => '',
//                'label' => (isset($options['label'])) ? $options['label'] : 'imgFromPdf',
//                'main' => (isset($options['main'])) ? $options['main'] : 'none',
//                'model_alias' => (isset($options['model_alias'])) ? $options['model_alias'] : 'Files',
//                'record_id' => $options['record_id'],
//                'parent_id' => (isset($options['parent_id'])) ? $options['parent_id'] : null
//            ]);
//        }
//        return $imgsInfoList;

    }

    static function extractPagesAsImages($options)
    {
        $options_default = [
            'pageSize' => [ 'width' => 1200, 'height' => 0]
        ];
        $options = array_merge($options_default, $options);

        list($dirname, $basename, $extension, $filename ) = array_values(pathinfo($options['path']));

        $date = new \DateTime();

        $imagick = new \Imagick($options['path']);
        $pages_count = $imagick->getNumberImages();

        $ext = (isset($options['ext'])) ? $options['ext'] : 'jpg';

        // создаем изображения из страниц PDF файла
        $imgsList = [];
        for ($pageNumber = 0; $pageNumber < $pages_count; $pageNumber++) {

            $newFilePath = $dirname . '/page_' . $pageNumber . '__' . $date->getTimestamp() . '.' . $ext . '';

            // создаем изображения из страниц PDF файла
            exec('convert \
                -density 400 \
                -colorspace CMYK \
                ' .$options['path']. '[' .$pageNumber. '] \
                -scale '.$options['pageSize']['width'].'x'.$options['pageSize']['height'].' \
                -quality 75  \
                -resize 100% '. $newFilePath  .'', $out, $error);

            $imgsList[$pageNumber] = $newFilePath;

        }

        return $imgsList;
    }
}
