<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Models\File;
use App\Models\Files;
use App\Models\History;

class FilesController extends Controller
{
    use ApiTrait;

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'importLocalFiles',
            'tmpSetSizes'
        ];
    }
    
    function upload(Request $request)
    {
        $data = $request->all();

        try {

            if (isset($data['files'])) {

                $files = [];

                foreach ($data['files'] as $item) {
                    $File = $item['file'];
                    unset($item['file']);
                    $files[] = self::uploadFile($File, $item);
                }

                return response()->json([
                    'data' => $files,
                    'meta' => []
                ]);

            } elseif (isset($data['file'])) {

                $File = $data['file'];
                unset($data['file']);
                $file = self::uploadFile($File, $data);

                return response()->json([
                    'data' => $file,
                    'meta' => []
                ]);
            }

        } catch (Exception $e) {

        }

    }

    static function uploadFile($file, $fileInfo)
    {
        $file = Uploader::upload($file);

        $file = array_merge($file, $fileInfo);

        File::create($file);

        History::create([
            'model_alias' => $fileInfo['model_alias'],
            'record_id' => $file['record_id'],
            'msg' => 'Загружен файл: ' . $file['basename']
        ]);

        return $file;
    }

    function changeFileInfo(Request $request)
    {
        $data = $request->all();

        $file = File::find($data['id']);
        foreach ($data as $fieldName => $value) {
            $file->$fieldName = $value;
        }
        $file->update();

        return response()->json([
            'data' => $file,
            'meta' => []
        ]);
    }

    function editDescription(Request $request, $id)
    {
        $data = $request->all();

        $file = File::find($id);
        foreach ($data as $fieldName => $value) {
            $file->$fieldName = $value;
        }
        $file->update();

        return response()->json([
            'data' => $file,
            'meta' => []
        ]);
    }
    
    function markToDelete($request, $id)
    {
        $file = File::find($id);
        $file->flag = "to_delete";
        $file->update();

        return response()->json([
            'data' => $file,
            'meta' => []
        ]);
    }
    
    function importLocalFiles()
    {
        $user = new \stdClass();
        $user->account_alias = 'Solikamsk';//Thyssen   Solikamsk
        \Porabote\Auth\Auth::setUser($user);

        $files = Files::where('model_alias', 'App.BusinessRequests')->get()->toArray();

       // echo 89;
        foreach($files as $file) {
            $file['model_alias'] = 'PaymentsRequests';
            $file['account_id'] = 3;

           // File::create($file);
           // debug($file);
        }
    }

    function tmpSetSizes()
    {
        $files = File::where('model_alias', 'PaymentsRequests')->get();
        foreach ($files as $file) {
            if (file_exists($file['path'])) {

//                copy(
//                    $file['path'],
//                    '/var/www/www-root/data/www/api.v2.thyssen24.ru/storage/upload/payments-requests/' . $file['basename']
//                );
                $file['uri'] = '/upload/payments-requests/' . basename($file['uri']);
              //  $file['account_id'] = 4;
               // $file->update();
            }
        }
    }
}