<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Porabote\Components\Auth\AuthException;
use App\Http\Components\Mailer\Mailer;
use App\Http\Components\Mailer\Message;
use Porabote\Auth\JWT;
use Porabote\Auth\Auth;
use App\Models\Users;
use App\Models\UsersRequests;
use App\Models\ApiUsers;
use App\Models\AclAcos;
use App\Models\AclAros;
use App\Models\AclPermissions;
use Porabote\FullRestApi\Server\ApiTrait;
use App\Exceptions\ApiException;
use Porabote\Curl\Curl;
use App\Http\Components\AccessLists;
use App\Http\Controllers\PassportsController;

class UsersController extends Controller
{
    use ApiTrait;

    static $authAllows;
    private $authData = [];

    function __construct()
    {
        self::$authAllows = [
            'login',
            'setToken',
            'confirmInvitation',
            'sendInvitationNotification',
            'migrationPosts',
            'removeUserFromObserversLists',
            'tmp_export_workers'
        ];
    }

    function check(Request $request)
    {
        $token = str_replace('JWT ', '', $request->header('Authorization'));

        return response()->json([
            'data' => [
                'account_alias' => 'porabote',
                'token' => $token
            ],
            'meta' => []
        ]);
    }

    function login(Request $request)
    {
        try {
            $data = $request->all();
            $data = [
                'username' => 'maksimov_den@mail.ru',
                'password' => 'z7893727',
                'account_alias' => 'Thyssen',
            ];

            $user = Auth::identify(
                $data['username'],
                $data['password'],
                $data['account_alias'],
            );

            $loginResponse = $this->authInLegasyApp($data);

            return response()->json([
                'data' => $loginResponse,
                'meta' => []
            ]);
            //http_code
            // debug($loginResponse);

            // return response()->json($token);
            //$request->session()->put('test', $loginResponse['session_id']);
            //  $jwtToken = $this->setToken($user);

        } catch (\Porabote\Auth\AuthException $exception) {
            echo $exception->jsonApiError();
        }

    }

//    function authInLegasyApp($data)
//    {
//        $curl = new Curl();
//        $curl->setData([
//            'username' => $data['username'],
//            'password' => $data['password'],
//            'account_alias' => $data['account_alias']
//        ]);
//
//        $response = $curl->post('https://thyssen24.ru/users/login');
//        $response = json_decode($response['response'], true);
//
//        setcookie('dur', $response['session_id'], time()+3600*720, "/", "api.thyssen24.ru", 1);
//
//        return $response;
//    }

    function setToken(Request $request)
    {
        $data = $request->all();
        parse_str($data['data'], $user);

        $token = $this->_setToken($user['data']);

        return response()->json($token);

    }

    function _setToken($userDataExt)
    {
        $userData = [
            'id' => null,
            'email' => null,
            'name' => null,
            'post_id' => null,
            'account_id' => null,
            'account_alias' => null,
            'avatar' => null,
            'post_name' => null,
            'role_id' => null
        ];

        $data = array_intersect_key($userDataExt, $userData);

        return JWT::setToken($data);
    }

    function getAclLists($request)
    {
        $data = $request->all();

        $aro = AclAros::get()
            ->where('foreign_key', $data['user_id'])
            ->where('label', 'User')
            ->first()
            ->toArray();

        $acosList = AclAcos::orderBy('name', 'asc')->get();
        $permissions = collect(AclPermissions::get()
            ->where('aro_id', $aro['id'])
        )->keyBy('aco_id');

        return response()->json([
            'data' => [
                'acosList' => $acosList,
                'permissions' => $permissions,
                'aro' => $aro,
            ],
            'meta' => []
        ]);
    }

    function setPermission($request)
    {
        $user = ApiUsers::find(Auth::$user->id)->toArray();
        if ($user['role_id'] != 1) {
            return response()->json([
                'data' => [
                    'error' => ['Access denied'],
                ],
                'meta' => []
            ]);
        }

        $data = $request->all();

        $status = null;
        if ($data['status']) {
            $this->addAccess($data['aco_id'], $data['aro_id']);
            $status = 'Added';
        } else {
            $this->deleteAccess($data['aco_id'], $data['aro_id']);
            $status = 'Deleted';
        }

        return response()->json([
            'data' => [
                'status' => $status,
            ],
            'meta' => []
        ]);
    }

    function addAccess($aco_id, $aro_id)
    {
        $permission = AclPermissions::get()
            ->where('aco_id', $aco_id)
            ->where('aro_id', $aro_id)
            ->first();

        if (!$permission) {
            AclPermissions::create([
                'aco_id' => $aco_id,
                'aro_id' => $aro_id,
                '_create' => 1,
                '_read' => 1,
                '_update' => 1,
                '_delete' => 1,
            ]);
        }
    }

    function deleteAccess($aco_id, $aro_id)
    {
        $permissions = AclPermissions::get()
            ->where('aco_id', $aco_id)
            ->where('aro_id', $aro_id);
        // $perm = AclPermissions::find($permission['id']);

        foreach ($permissions as $permission) {
            $permission->delete();
        }
    }

    function create($request)
    {
        try {
            $data = $request->all();

            if (!$access = AccessLists::_check(11)) {
                throw new ApiException('Извините, Вам не выданы права для добавления пользователя.');
            }
            
            $user = self::_createUser($data);
            $aro = self::_createAro($user->id);

            PassportsController::_addPassport($user->id);
            PassportsController::_addForeignPassport($user->id);

            $this->_setPermissionsByDefault($aro->id);

            return response()->json([
                'data' => $user,
                'meta' => []
            ]);

        } catch (ApiException $e) {
            $e->toJSON();
        }

    }

    private function _setPermissionsByDefault($aroId)
    {
        $acos = [1, 20, 43, 32, 37];
        foreach ($acos as $aco) {
            $this->addAccess($aco, $aroId);
        }
    }

    function edit($request)
    {
        try {

            $data = $request->all();

            if ($data['id'] != Auth::$user->id && !$access = AccessLists::_check(11)) {
                throw new ApiException('Извините, Вам не выданы права для редактирования пользователя.');
            }

            $user = ApiUsers::find($data['id']);

            $allowedList = array_flip(ApiUsers::$allowed_attributes);

            foreach ($data as $field => $value) {
                if (array_key_exists($field, $allowedList)) {
                    $user->$field = $value;
                }
            }

            $user->update();

            if ($user->status == 'fired') {
                $this->_resetPassword($user->id);
            }

            return response()->json([
                'data' => $user->toArray(),
                'meta' => []
            ]);

        } catch (ApiException $e) {
            $e->toJSON();
        }
    }

    private function _resetPassword($id)
    {
        $user = ApiUsers::find($id);
        $user->password = 'fired__' . $user->password . '__' . bin2hex(random_bytes(18));
        $user->update();
    }

    private function _createAro($user_id)
    {
        $aro = AclAros::create([
            'parent_id' => null,
            'label' => 'User',
            'foreign_key' => $user_id,
            'model' => 'App\Models\Users',            
        ]);

        return $aro;
    }

    private static function _createUser($data)
    {
        $user = ApiUsers::get()
            ->where('email', $data['email'])
            ->first();

        if (!$user) {

            $checkOnEmpty = false;
            foreach ($data as $field => $value) {
                if (empty($value) && !in_array($field, ['patronymic'])) {
                    throw new ApiException('Заполнены не все поля' . $field);
                }
            }

            $user = [
                'email' => $data['email'],
                'name' => $data['last_name'] . ' ' . $data['name'],
                'last_name' => $data['last_name'],
                'patronymic' => $data['patronymic'],
                'post_name' => $data['post_name'],
                'department_id' => $data['department_id'],
                'token' => self::createToken(),
                'password' => null,
                'role_id' => 2,
            ];
            return ApiUsers::create($user);
        } else {
            throw new ApiException('Пользователь с логином ' . $data['email'] . ' уже существует');
        }
    }

    function createUserRequest($request)
    {
        $data = $request->all();
        $request = self::_createUserRequest($data['user_id']);

        return response()->json([
            'data' => $request,
            'meta' => []
        ]);
    }

    static function _createUserRequest($user_id)
    {
        return UsersRequests::create([
            'user_id' => $user_id,
            'sender_id' => Auth::$user->id,
            'token' => self::createToken(),
            //'date_request' => \Carbon\Carbon::now(),
            'account_id' => Auth::$user->account_id,
        ]);
    }

    function sendInvitationNotification($Request, $requestId = 1)
    {
        $msgData = UsersRequests::with('user')
            ->with('sender')
            ->find($requestId)
            ->toArray();


//        $user = new \stdClass();
//        $user->account_alias = 'Thyssen';//Thyssen   Solikamsk
//        \Porabote\Auth\Auth::setUser($user);

        $message = new Message();
        $message->setData($msgData)->setTemplateById(9);
        Mailer::setTo($msgData['user']['email']);
        Mailer::send($message);

        return response()->json([
            'data' => $msgData,
            'meta' => []
        ]);
    }

    function removeUserFromObserversLists()
    {

    }

    function confirmInvitation($request)
    {
        try {
            $data = $request->all();

            $request = UsersRequests::with('user')
                ->with('sender')
                ->find($data['requestId']);

            if (!isset($request->token) || $request->token != $data['token']) {
                throw new ApiException('Извините, токен уже был использован.');
            }

            if ($request->user->status == 'fired') {
                throw new ApiException('Извините, Вы отмечены как уволенный сотрудник, изменение пароля невозможно.');
            }

            $userRequest = $request->toArray();

            if(!isset($data['password'])) {

                self::_checkRequestDate($userRequest['date_request']);

                return response()->json([
                    'data' => $userRequest,
                    'meta' => []
                ]);
            } else {
                self::_changePassword($userRequest['user_id'], $data['password'], $data['password_confirm']);

                $request->date_confirm = \Carbon\Carbon::now();
                $request->token = null;
                $request->update();
            }
        } catch (ApiException $e) {
            $e->toJSON();
        }
    }

    static function _changePassword($user_id, $password, $password_confirm)
    {
        self::_checkPassword($password, $password_confirm);

        $hash = Hash::make($password);

        $user = ApiUsers::find($user_id);

        if (!$user) throw new ApiException('Пользователь не задан или не найден.');

        $user->password = $hash;
        $user->status = 'active';
        $user->update();
    }

    static function _checkPassword($password, $password_confirm)
    {
        if ($password != $password_confirm) {
            throw new ApiException('Пароли не совпадают.');
        } elseif (strlen($password) < 4) {
            throw new ApiException('Пароль не может быть менее 4 символов.');
        }
    }

    static function _checkRequestDate($date)
    {
        $dateDeadline = (new \DateTime($date))->modify('+3 day');
        $dateNow = new \DateTime();

        if ($dateDeadline < $dateNow) {
            throw new ApiException('Извините, с момента приглашения прошло более 3х дней, срок токена истек.');
        }
    }

    static function createToken()
    {
        $token = openssl_random_pseudo_bytes(16);
        $token = bin2hex($token);
        return $token;
    }













    function migrationPosts()
    {
        $user = new \stdClass();
        $user->account_alias = 'Thyssen';//Thyssen   Solikamsk
        \Porabote\Auth\Auth::setUser($user);


        $apiUsers = \App\Models\ApiUsers::get()->toArray();
        $apiUsersList = [];
        $apiUsersListFull = [];
        $apiUsersListFullByOld = [];
        foreach ($apiUsers as $apiUser) {

            if($apiUser['local_id']) $apiUsersListFullByOld[$apiUser['local_id']] = $apiUser['id'];

            $apiUsersList[$apiUser['email']] = $apiUser['id'];
            $apiUsersListFull[$apiUser['id']] = $apiUser;

        }

        $posts = \App\Models\Posts::get()->toArray();
        $postsList = [];
        foreach ($posts as $post) {
            if (!isset($apiUsersList[$post['email']])) debug($post['email']);
            $newId = (isset($apiUsersList[$post['email']])) ? $apiUsersList[$post['email']] : '';
            $postsList[$post['id']] = $newId;
        }

//        $users = \App\Models\ApiUsers::get();
//        foreach($users as $user) {
//            if (isset($postsList[$user['email']])) {
//                $user->phone = $postsList[$user['email']];
//                $user->update();
//            }
//        }


//          $files = \App\Models\Files::where('date_created', '<', '2022-05-14 11:51:01')
//              ->orderBy('id', 'desc')
//              ->with('user')
//            //  ->limit(10)
//              ->get();
//       // ini_set('memory_limit', '1256M');
//foreach ($files as $file) {
//
//    if(isset($apiUsersList[$file['user']['username']])) {
//        $userId = $apiUsersList[$file['user']['username']];
//        $file->user_id = $userId;//debug($userId);
//       // $file->save();
//    }
//    //$file->user_id =
//}
//        foreach ($payments as $payment) {
//
//           // $payment['post_id'] = $postsList[$payment['post_id']];
//            $payment['sender_id'] = $postsList[$payment['sender_id']];
//           // debug($payment['id']);
//           // debug($payment['acceptor_id']);
//           // $payment->update();
//        }

//        $payments = \App\Models\PaymentsSets::where('id', '<', 376)->get();//9108 //9042
//        foreach ($payments as $payment) {
//
//           // $payment['post_id'] = $postsList[$payment['post_id']];
//            $payment['sender_id'] = $postsList[$payment['sender_id']];
//           // debug($payment['id']);
//           // debug($payment['acceptor_id']);
//           // $payment->update();
//        }


//        $nomencl = \App\Models\PurchaseNomenclatures::get();
//        foreach($nomencl as $nmcl) {
//            if (!$nmcl['manager_id']) continue;
//            if(!isset($postsList[$nmcl['manager_id']])) echo $nmcl['id'];
//            $nmcl['manager_id'] = $postsList[$nmcl['manager_id']];
//            //$nmcl->update();
//        }

//        $configs = \App\Models\Configs::find(12);
//        $value = unserialize($configs['value']);
//        $newValues = [];
//
//        foreach ($value as $val) {
//            if (!isset($postsList[$val])) continue;
//            $newValues[$postsList[$val]] = $postsList[$val];
//        }
//       // debug($newValues);
//        $configs->value = serialize($newValues);
//       // $configs->update();

    }

    function tmp_export_workers0()
    {
//        $path = '/var/www/www-root/data/www/api.v2.thyssen24.ru/storage/export/shifts.xlsx';
//        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
//        $sheet = $spreadsheet->getActiveSheet();
//
//        for ($i = 3; $i <= 218; $i++) {
//
//            $fio = explode(' ', $sheet->getCell('B' . $i)->getValue());
//            if (!isset($fio[2])) $fio[2] = '';
//            $shifts = [
//                1 => 32,
//                2 => 33,
//                3 => 34
//            ];
//
//            $cities = \App\Models\Cities::get()->toArray();
//            foreach($cities as $city) {
//                $cit[$city['name_ru']] = $city['id'];
//            }
//
//            $user = [
//                'email' => \Porabote\Stringer\Stringer::transcript("fake_$fio[0]@@@@@@email.ru"),
//                'name' => $fio[0] . ' ' . $fio[1],
//                'last_name' => $fio[0],
//                'patronymic' => $fio[2],
//                'city_id' => (isset($cit[$sheet->getCell('E' . $i)->getValue()])) ? $cit[$sheet->getCell('E' . $i)->getValue()] : null,
//                'shift_id' => $shifts[$sheet->getCell('D' . $i)->getValue()],
//                'post_name' => $sheet->getCell('C' . $i)->getValue(),
//                'department_id' => 94,
//               // 'token' => self::createToken(),
//                'password' => null,
//                'role_id' => 2,
//            ];
           // ApiUsers::create($user);
//            $passport = [
//                'type' => 'foreign',
//            ];
           // debug( $user);
            //return ApiUsers::create($user);
       // }
//        $users = ApiUsers::with('passport')->get()->toArray();
//        foreach ($users as $user) {
//            if(empty($user['passport'])) {
//
//                $fi = explode(' ', $user['name']);
//             //   debug($fi);
//                $passport = [
//                    'type' => 'russian',
//                    'user_id' => $user['id'],
//                    'name' => $fi[1],
//                    'last_name' => $fi[0],
//                    'patronymic' => $user['patronymic'],
//                ];
//                //debug($passport);
//               // \App\Models\Passport::create($passport);
//            }
//        }
    }

    function tmp_export_workers()
    {


        $users = ApiUsers::with('passport')->get()->toArray();
        $us_pass = [];
        foreach ($users as $user) {
            if ($user['passport']) {
                $us_pass[$user['name'] . ' '. $user['patronymic']] = $user['passport']['id'];
            }
        }
      //  debug($us_pass);

        $path = '/var/www/www-root/data/www/api.v2.thyssen24.ru/storage/export/pass.xlsx';
        $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path);
        $sheet = $spreadsheet->getActiveSheet();

        $passports = [];
        for ($i = 2; $i <= 349; $i++) {
            $date_issue = $sheet->getCell('F' . $i)->getValue();
            $date_issue = new \DateTime($date_issue);
            $date_issue = $date_issue->format('Y-m-d');

            $passports[$sheet->getCell('B' . $i)->getValue()] = [
                'sery' => str_replace(' ', '', $sheet->getCell('D' . $i)->getValue()),
                'number' => $sheet->getCell('E' . $i)->getValue(),
                //'date_birth' => '',
                'date_of_issue' => $date_issue,
            ];
        }

        foreach ($passports as $userName => $passport) {

            if (isset($us_pass[$userName])) {

                $passportU = \App\Models\Passport::find($us_pass[$userName]);
                foreach ($passport as $field => $value) {
                    $passportU->$field = $value;
                }
               // $passportU->update();
            }
        }


    }
}
