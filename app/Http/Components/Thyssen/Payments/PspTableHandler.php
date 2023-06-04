<?php
namespace App\Http\Components\Thyssen\Payments;

use Illuminate\Support\Facades\View;
use App\Models\Payments;
use Porabote\Auth\Auth;

class PspTableHandler
{
    static function renderHtmlTable($paymentId)
    {
        try {
            $payment = Payments::find($paymentId)->toArray();
            $dataJson = json_decode($payment['data_json'], true);

            if (!isset($dataJson['info'])) response()->json(['error' => 'Данные таблицы пусты']);

            $firstElement = $dataJson['info'][array_key_first($dataJson['info'])];

            return View::make('payments.table_signatures', [
                'data' => $dataJson,
                'object_name' => $firstElement['object_name']
            ])->render();

        } catch (\Error $error) {
            debug($error->getMessage());
        }

    }

    static function htmlToPdf($html, $options = [])
    {

        $handler = new \Mpdf\Mpdf;

        $options_default = [
            'pageSize' => [ 'width' => 210, 'height' => $handler->y]
        ];
        $options = array_merge($options_default, $options);

        $handler->WriteHTML($html);

        $handler->page   = 0;
        $handler->state  = 0;
        unset($handler->pages[0]);

        // The $p needs to be passed by reference
        $p = 'P';

        $handler->_setPageSize([$options['pageSize']['width'], $handler->y], $p);

        $handler->addPage();
        $handler->WriteHTML($html);


        $handler->Output($options['path']);//, \Mpdf\Output\Destination::INLINE

    }

    static function pdfToImg($options = [])
    {
        $handler = new \Mpdf\Mpdf;

        $options_default = [
            'pageSize' => [ 'width' => 1000, 'height' => $handler->y]
        ];
        $options = array_merge($options_default, $options);

        $imgsList = self::extractPagesAsImages($options);

        $imgsInfoList = [];
        foreach($imgsList as $imgPath) {
            $imgsInfoList[] = self::addMetaData($imgPath, [
                'title' => '',
                'dscr' => '',
                'label' => (isset($options['label'])) ? $options['label'] : 'imgFromPdf',
                'main' => (isset($options['main'])) ? $options['main'] : 'none',
                'model_alias' => (isset($options['model_alias'])) ? $options['model_alias'] : 'Payments',
                'record_id' => $options['record_id'],
                'parent_id' => (isset($options['parent_id'])) ? $options['parent_id'] : null
            ]);
        }
        return $imgsInfoList;

    }

    static public function addMetaData($path, $info = [])
    {
        list($dirname, $basename, $extension, $filename ) = array_values(pathinfo($path));
        list( $width, $height ) = getimagesize($path);

        $data = [
            'path' => $path,
            'basename' => $basename,
            'ext' => $extension,
            'mime' => mime_content_type($path),
            'size' => filesize($path),
            'uri' => str_replace(storage_path(), '/files', $path),
            'user_id' => Auth::$user->id,
            'flag' => 'on'
        ];

        // Если это не изображение
        if (explode('/',$data['mime'])[0] != 'image') {

            // определяем размеры исходного изображения
            list( $width, $height ) = getimagesize($path);
            $data['width'] = $width;
            $data['height'] = $height;
        }

        return array_merge($data, $info);

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

    function clonePdf($options = [])
    {
        list($dirname, $basename, $extension, $filename ) = array_values(pathinfo($options['path']));

        $pagecount = $this->handler->SetSourceFile($options['path']);


        $this->handler->SetImportUse();


        for ($i=1; $i<=$pagecount; $i++) {
            $import_page = $this->handler->ImportPage();
            $this->handler->UseTemplate($import_page);

            if ($i < $pagecount)
                $this->handler->AddPage();
        }

        //return $this->handler;
        $this->handler->Output('scan_img_'.bin2hex(random_bytes(5)).'_.pdf',\Mpdf\Output\Destination::INLINE);
    }

}
