<?php

namespace app\modules\store\controllers;

use Yii;
use yii\rest\ActiveController;
use app\models\User;
use app\models\Status;
use yii\filters\auth\QueryParamAuth;
use yii\helpers\ArrayHelper;
use yii\web\ServerErrorHttpException;

/**u
 * Auth controller for the `store` module
 */
class AuthController extends ActiveController
{

    public $modelClass = User::class;

    // public function behaviors()
    // {
    //     return ArrayHelper::merge(parent::behaviors(), [
    //         'authenticatior' => [
    //             'class' => QueryParamAuth::className(), // Implementing access token authentication
    //             'except' => ['login'], /// There is no need to validate the access token method. Note the distinction between $noAclLogin
    //         ]
    //     ]);
    // }
    // protected function verbs()
    // {
    //     return [
    //         'login' => ['POST'],
    //         'forgot' => ['POST'],
    //         'verify-code' => ['POST'],
    //         'change-password' => ['POST'],
    //     ];
    // }

    public function actions()
    {
        $action = parent::actions(); // TODO: Change the autogenerated stub
        unset($action['index']);
        unset($action['create']);
        unset($action['update']);
        unset($action['delete']);
        unset($action['view']);
    }
    /**
     * Renders the index view for the module
     * @return string
     */
    public function actionLogin()
    {
        $params = Yii::$app->request->post();
        if (empty($params['email']) || empty($params['password'])) return [
            'flag' => false,
            'status' => Status::STATUS_BAD_REQUEST,
            'message' => "Required login detail",
            'data' => ''
        ];

        $user = User::findByUsername($params['email']);
        if ($user == null) {
            return [
                'flag' => false,
                'status' => Status::STATUS_UNAUTHORIZED,
                'message' => 'Invalid login detail',
                'data' => ''
            ];
        }
        if ($user->validatePassword($params['password'])) {
            Yii::$app->response->statusCode = Status::STATUS_OK;
            return [
                'flag' => true,
                'status' => Status::STATUS_OK,
                'message' => 'Login Succeed',
                'data' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'gender' => $user->gender,
                    'contact' => $user->contact,
                    'img' => $user->img,
                    'ads' => $user->ads,
                    'cat' => $user->cat,
                    'uat' => $user->uat,
                    'token' => $user->access_token,
                ]
            ];
        } else {
            Yii::$app->response->statusCode = Status::STATUS_UNAUTHORIZED;
            return [
                'flag' => false,
                'status' => Status::STATUS_UNAUTHORIZED,
                'message' => 'Invalid login detail ',
                // 'data' => [
                //     $user->passw, md5($params["password"])
                // ]
            ];
        }
    }

    public function actionSignup()
    {
        $model = new User();
        if ($model->load(Yii::$app->request->post(), '') && $model->save()) {
            return $model;
        } elseif ($model->hasErrors()) {
            Yii::$app->response->setStatusCode(406);
            return $model->errors;
        } else {
            throw new ServerErrorHttpException('Data not submitted');
        }
    }

    public function actionForgot()
    {
        $params = Yii::$app->request->post();
        if (isset($params['email'])) {
            $model = User::find()->where(["email" => $params['email']])->one();
            if (empty($model)) {
                return [
                    'flag' => false,
                    'status' => Status::STATUS_NOT_FOUND,
                    'message' => "User not found",
                    'data' => ''
                ];
            } else {
                try {
                    $model->f_code = Yii::$app->getSecurity()->generateRandomString(10);
                    $model->f_date = time() + 60 * 60 * 2;
                    $model->f_sts = 1;
                    $model->update(false);
                    if (empty(Yii::$app->params["sitemail"])) {
                    } else {
                        // try {
                        //     Yii::$app->mailer->compose('forgetPassword', ['user' => $model])
                        //         ->setFrom(Yii::$app->params["sitemail"])
                        //         ->setTo($model->email)
                        //         ->setSubject("Forgot your password? - " . Yii::$app->params["sitename"])
                        //         ->send();
                        // } catch (\Swift_TransportException $ex) {
                        // }
                    }
                } catch (\yii\base\Exception $ex) {
                }
            }
            return [
                'flag' => true,
                'status' => Status::STATUS_OK,
                'message' => "If the given mail exist in the system, You will receive a password reset link. Please check your mail!!",
                'data' => [
                    "id" => $model->id,
                    "email" => $model->email
                ]
            ];
        }
        return [
            'flag' => false,
            'status' => Status::STATUS_VALIDATION_ERROR,
            'message' => "Field missing",
            'data' => [
                'field' => 'email',
                'message' => 'Email is required'
            ]
        ];
    }

    public function actionVerifyCode()
    {
        $params = Yii::$app->request->post();
        if (isset($params['verifyCode']) && isset($params['id'])) {
            $user = User::find()->where(["id" => $params['id']])->one();
            if (!empty($user)) {
                if ($user->f_sts == 1 && $user->f_date > time() && $user->f_code === $params['verifyCode']) {
                    return [
                        'flag' => true,
                        'status' => Status::STATUS_OK,
                        'message' => "Code verified",
                        'data' => ''
                    ];
                }
                return [
                    'flag' => false,
                    'status' => Status::STATUS_VALIDATION_ERROR,
                    'message' => "Invalid verification code",
                    'data' => ''
                ];
            }
        }
        return [
            'flag' => false,
            'status' => Status::STATUS_VALIDATION_ERROR,
            'message' => "verifyCode is missing",
            'data' => [
                'field' => 'verifyCode',
                'message' => 'verifyCode is required'
            ],
            'post' => $params
        ];
    }


    public function actionChangePassword()
    {
        $params = Yii::$app->request->post();

        if (isset($params['verifyCode']) && isset($params['id'])) {
            $code = Yii::$app->request->post('verifyCode');
            $pass = Yii::$app->request->post('new_password');
            $user = User::find()->where(["id" => $params['id']])->one();
            if (!empty($user)) {
                if ($user->f_sts == 1 && $user->f_date > time() && $user->f_code === $code) {
                    if (strlen($pass) < 6) {
                        return [
                            'flag' => false,
                            'status' => Status::STATUS_VALIDATION_ERROR,
                            'message' => "Password length must be greater then 6",
                            'data' => ''
                        ];
                    } else {
                        $user->passwd = $pass;
                        $user->f_sts = 0;
                        if ($user->update(false)) {
                            if (empty(Yii::$app->params["sitemail"])) {
                            } else {
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
                    }
                } else {
                    return [
                        'flag' => false,
                        'status' => Status::STATUS_VALIDATION_ERROR,
                        'message' => "Invalid varification code",
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
