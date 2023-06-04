<?php

namespace App\Observers;

use App\Models\History;
use Carbon\Carbon;

class PaymentObserver
{
    public function creating($model)
    {
//        $attributes = $model->getAttributes();
//
//        $splFileInfo = $splFileInfo = new \SplFileInfo($attributes['path']);
//
//        $model->basename = $splFileInfo->getBasename();
    }

    public function updating($model) {
        if (!empty($model->date_payment)) {
            if (is_string($model->date_payment)) {
                $model->pay_week = Carbon::create($model->date_payment)->weekOfYear;
            } else {
                $model->pay_week = $model->date_payment->weekOfYear;
            }
        }
    }

}
