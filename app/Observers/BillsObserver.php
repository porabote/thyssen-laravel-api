<?php

namespace App\Observers;

use App\Http\Controllers\PaymentsController;

class BillsObserver
{

    public function updated($record)
    {
        $record->summa = 50;
        PaymentsController::updateAfterBillUpdated(null, $record->id);
    }

//    public function updating($model)
//    {
//        $model->summa = 50;
//        PaymentsController::updateAfterBillUpdated($model->id);
//    }


}
