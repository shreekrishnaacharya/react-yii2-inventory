<?php

namespace app\modules\store\controllers;

use Yii;
use yii\rest\ActiveController;
use app\models\User;
use app\models\Status;

/**u
 * Auth controller for the `store` module
 */
class AccountController extends ActiveController
{

    public $modelClass = User::class;


    public function actions()
    {
        $action = parent::actions(); // TODO: Change the autogenerated stub
        unset($action["create"], $action["delete"]);
    }


    protected function verbs()
    {
        $verbs = parent::verbs();
        $verbs["change-password"] = ["POST"];
        $verbs["view"] = ["POST"];
        $verbs["update"] = ["POST"];
        $verbs["delete"] = ["POST"];
        return $verbs;
    }

    /**
     * Renders the index view for the module
     * @return string
     */

    public function actionView()
    {
        return Yii::$app->user->identity;
    }

    public function actionChangePassword()
    {
        $params = Yii::$app->request->post();

        if (isset($params['new_password']) && isset($params['old_password'])) {
            $opass = Yii::$app->request->post('old_password');
            $pass = Yii::$app->request->post('new_password');
            $user = Yii::$app->user->identity;
            if (!empty($user)) {
                if (strlen($pass) < 6) {
                    return [
                        'flag' => false,
                        'status' => Status::STATUS_VALIDATION_ERROR,
                        'message' => "Password length must be greater then 6",
                        'data' => ''
                    ];
                } elseif ($user->validateOldPassword($opass)) {
                    $user->passwd = $pass;
                    if ($user->update(false)) {
                        if (!empty(Yii::$app->params["sitemail"])) {
                            try {
                                Yii::$app->mailer->compose('resetPassword', ['user' => $user])
                                    ->setFrom(Yii::$app->params["sitemail"])
                                    ->setTo($user->email)
                                    ->setSubject("Password changed - " . Yii::$app->params["sitename"])
                                    ->send();
                            } catch (\Swift_TransportException $ex) {
                            }
                        }
                        return [
                            'flag' => true,
                            'status' => Status::STATUS_OK,
                            'message' => "Password change success",
                            'data' => ''
                        ];
                    } else {
                        return [
                            'flag' => false,
                            'status' => Status::STATUS_VALIDATION_ERROR,
                            'message' => "Password not changed",
                            'data' => ''
                        ];
                    }
                } else {
                    return [
                        'flag' => false,
                        'status' => Status::STATUS_VALIDATION_ERROR,
                        'message' => "Old password does not match",
                        'data' => ''
                    ];
                }
            }
        }
        return [
            'flag' => false,
            'status' => Status::STATUS_VALIDATION_ERROR,
            'message' => "Invalid varification code",
            'data' => ''
        ];
    }
}
