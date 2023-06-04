<?php

namespace App\Observers;

use Porabote\Auth\Auth;


class AccountObserver
{
    /**
     * Handle the Auth "created" event.
     *
     * @param  \App\Models\Auth  $auth
     * @return void
     */
    public function creating($model)
    {
        $attrs = $model->getOriginal();
        $model->account_id = Auth::getUser('account_id');
    }
}
