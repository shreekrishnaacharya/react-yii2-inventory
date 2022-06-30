<?php

namespace app\modules\store\controllers;

use Yii;
use app\modules\store\models\Sales;
use app\modules\store\models\SalesItem;
use app\modules\store\models\SalesPay;
use app\modules\store\models\SalesSearch;
use yii\filters\auth\HttpBearerAuth;
use yii\rest\ActiveController;
use yii\web\NotFoundHttpException;
use yii\filters\VerbFilter;
use yii\web\ServerErrorHttpException;

/**
 * SalesController implements the CRUD actions for Sales model.
 */
class SalesController extends ActiveController
{

    public $modelClass = Sales::class;
    /**
     * {@inheritdoc}
     */
    public function behaviors()
    {
        $behaviors = parent::behaviors();
        $behaviors['authenticator'] = [
            'class' => HttpBearerAuth::class,
        ];
        return $behaviors;
    }
    public function actions()
    {
        $actions = parent::actions();
        $actions['index']['prepareDataProvider'] = [$this, 'prepareDataProvider'];
        unset($actions['create']);
        return $actions;
    }

    protected function verbs()
    {
        $verbs = parent::verbs();
        $verbs["update"] = ["POST"];
        $verbs["delete"] = ["POST"];
        return $verbs;
    }

    /**
     * Lists all Customers models.
     * @return mixed
     */
    public function prepareDataProvider()
    {
        $searchModel = new SalesSearch();
        return $searchModel->search(Yii::$app->request->queryParams);
    }
    public function actionStatus()
    {
        return Sales::getStatusList();
    }

    public function actionCreate()
    {
        $model = new Sales();
        if ($model = $this->formProcess($model)) {
            return $model;
        } else {
            throw new ServerErrorHttpException('Data not submitted');
        }
    }

    public function actionReturn($id)
    {
        $pmodel = $this->findModel($id);
        if ($pmodel->sts == 3) {
            Yii::$app->response->statusCode = 422;
            return "Already return";
        }
        $rmodel = new Sales();
        $rmodel->attributes = $pmodel->attributes;
        $rmodel->sts = 3;
        $rmodel->pdate = null;
        $rmodel->pym_term = 0;
        if ($rmodel = $this->formProcess($rmodel, $pmodel)) {
            return $rmodel;
        } else {
            throw new ServerErrorHttpException('Data not submitted');
        }
    }

    public function actionAddpayment($id)
    {
        $model = $this->findModel($id);
        $amount = Yii::$app->request->post("amount");
        if (!is_numeric($amount)) {
            Yii::$app->response->statusCode = 422;
            return "Invalid Amount";
        }
        $addPay = new SalesPay();
        $addPay->fusr = Yii::$app->user->id;
        $addPay->amt = $amount;
        $addPay->fsal = $id;
        $addPay->save(false);
        $model->pay_amt += $amount;
        $model->save(false);
        return $addPay;
    }

    private function formProcess($model, $pmodel = null)
    {
        Yii::$app->response->statusCode = 422;
        $isReturn = $model->sts == 3 ? true : false;
        if ($model->load(Yii::$app->request->post(), '')) {
            $transaction = Yii::$app->db->beginTransaction();
            try {
                $model->attachfile = \yii\web\UploadedFile::getInstanceByName("attachfile");
                $fileName = "";
                if ($model->attachfile != null) {
                    $fileName = "sales_" . time() . $model->attachfile->extension;
                    $model->attach = $fileName;
                }
                $model->pdate = strtotime($model->ptdate);
                $model->fusr = Yii::$app->user->id;
                if (empty($model->items)) {
                    Yii::$app->response->statusCode = 422;
                    $model->validate();
                    return $model->errors;
                }
                if ($model->isNewRecord && !$model->save(false)) {
                    return false;
                }
                if ($isReturn) {
                    $model->sts = 3;
                    if ($model->ref_no == "") {
                        $model->ref_no = $pmodel->ref_no;
                    }
                } else {
                    if ($model->ref_no == "") {
                        $model->ref_no = "SALE/" . date("Y/m/") . ($model->id * 10000);
                    }
                }
                $totalAmount = 0;
                foreach ($model->items as $itm) {
                    $pitem = new SalesItem();
                    $pitem->fsal = $model->id;
                    $pitem->fitm = $itm["itemid"];
                    $pitem->qty = $itm["qty"];
                    $pitem->rate = $itm["cost"];
                    $pitem->save(false);
                    $totalAmount += round($itm["qty"] * $itm["cost"], 2);
                }

                if ($isReturn) {
                    $model->pay_amt = 0;
                    $model->amt = $totalAmount * -1;
                    $model->dis_per = round((100 / $totalAmount) * $model->dis_amt, 2);
                    $totalAmount = $totalAmount + $model->dis_amt;
                    $model->vat_amt = round(($model->vat_rate / 100) * $totalAmount, 2) * -1;
                    $model->total_amt = $model->vat_amt + $model->amt + $model->rchg;
                    $pmodel->has_return = 1;
                    $model->tid = $pmodel->id;
                    $pmodel->tid = $model->id;
                    $pmodel->save(false);
                } else {
                    $model->amt = $totalAmount;
                    $model->dis_per = round((100 / $totalAmount) * $model->dis_amt, 2);
                    $totalAmount = $totalAmount - $model->dis_amt;
                    $model->vat_amt = round(($model->vat_rate / 100) * $totalAmount, 2);
                    $model->total_amt = $model->vat_amt + $totalAmount;
                }
                if ($model->attachfile != null) {
                    $location = PATH_TO_RESOURCE . '/sales/' . $model->id . '/';
                    $imageHelper = new \app\Helpers\ImageHelper();
                    $imageHelper->mkdir($location);
                    $model->attachfile->saveAs($location . $fileName);
                }
                if (!$model->save()) {
                    Yii::$app->response->statusCode = 422;
                    return $model->errors;
                }
                $response = Yii::$app->getResponse();
                $response->setStatusCode(201);
                $transaction->commit();
                return $model;
            } catch (\Exception $ex) {
                var_dump($ex->getMessage());
                die;
            }
            $transaction->rollBack();
        }
        return false;
    }

    /**
     * Finds the Sales model based on its primary key value.
     * If the model is not found, a 404 HTTP exception will be thrown.
     * @param integer $id
     * @return Sales the loaded model
     * @throws NotFoundHttpException if the model cannot be found
     */
    protected function findModel($id)
    {
        if (($model = Sales::findOne($id)) !== null) {
            return $model;
        }

        throw new NotFoundHttpException('The requested page does not exist.');
    }
}
