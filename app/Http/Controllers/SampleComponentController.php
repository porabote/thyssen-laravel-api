<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\SampleComponent;
use App\Models\File;
use Porabote\FullRestApi\Server\ApiTrait;
use Porabote\Uploader\Uploader;
use App\Models\History;
use App\Models\Comment;
use App\Models\Config;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use App\Http\Controllers\ObserversController;

class SampleComponentController extends Controller
{
    use ApiTrait;

    function add(Request $request)
    {
        $data = $request->all();

        $record = SampleComponent::create($data);

        ObserversController::subscribeByDefaultList([1, 2, 3], $record->id);
        //ObserversController::subscribe($report->user_id, [1, 2, 3], $record->id);

        History::create([
            'model_alias' => 'sample_component',
            'record_id' => $record->id,
            'msg' => 'Добавлена новая запись'
        ]);

        $msgData = $data;
        $msgData['data'] = $this->getById($record->id);
        $message = new Message();
        $message->setData($msgData)->setTemplateById(2);

        Mailer::setToByEventId(3, $record->id);
        //Mailer::send($message);

        return response()->json([
            'data' => $record,
            'meta' => []
        ]);
    }

    function uploadReportFile(Request $request)
    {

        $data = $request->all();

        if (isset($data['files'])) {

            $files = [];

            foreach ($data['files'] as $item) {
                $File = $item['file'];
                unset($item['file']);
                $files[] = $this->uploadFile($File, $item);
            }

            $msgData = $files[0];
            $msgData['data'] = $this->getById($data['files'][0]['record_id']);
            $message = new Message();
            $message->setData($msgData)->setTemplateById(3);

            Mailer::setToByEventId([2], $msgData['record_id']);
            Mailer::send($message);

            return response()->json([
                'data' => $files,
                'meta' => []
            ]);

        }

    }

    function uploadFile($file, $fileInfo)
    {
        $file = Uploader::upload($file);

        $file = array_merge($file, $fileInfo);

        File::create($file);

        History::create([
            'model_alias' => 'sample-component',
            'record_id' => $file['record_id'],
            'msg' => 'Загружен файл: ' . $file['basename']
        ]);

        return $file;
    }


    function addComment(Request $request)
    {
        $data = $request->all();

        Comment::create($data);

//        $msgData = $data;
//        $msgData['data'] = $this->getById($data['record_id']);
//
//        $message = new Message();
//        $message->setData($msgData)->setTemplateById(1);
//
//        Mailer::setToByEventId([1], $data['record_id']);
//        Mailer::send($message);

        return response()->json([
            'data' => $data,
            'meta' => []
        ]);
    }

    function getById($id)
    {
        $record = SampleComponent::find($id);
        $data = $record->getAttributes();
        $data['user'] = $record->user->getAttributes();
        $data['history'] = $record->history->toArray();
        $data['comments'] = $record->comments->toArray();
        $data['files'] = $record->files->toArray();
        return $data;
    }

}
