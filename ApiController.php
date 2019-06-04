<?php

namespace app\controllers;

use app\models\Address;
use app\models\Calculator;
use app\models\ChatCategory;
use app\models\Chat;
use app\models\Order;
use app\models\Claim;
use app\models\HomeQuestion;
use app\models\Homes;
use app\models\Question;
use app\models\Support;
use Yii;
use yii\helpers\Json;
use app\components\FrontController;
use app\models\User;

class ApiController extends FrontController
{
    public function actions()
    {
        header('Access-Control-Allow-Origin: *');

        return [
            'error' => [
                'class' => 'app\components\ErrorAction',
            ],
        ];
    }


    /**
     * We return errors.
     * @param $errors
     * @return mixed
     */
    private function getError($errors)
    {
        if(count($errors)){
            foreach ($errors as $key => $error){
                if(is_array($error)){
                    foreach ($error as $k => $e){
                        return $e;
                    }
                } else {
                    return $error;
                }
            }
        }
    }


    /**
     * User Token Check
     * We return information on the user.
     * @param $attr
     * @return User|null|void
     */
    private function checkUserByToken($attr){
        if (!empty($attr) && isset($attr['token'])) {
            return $this->getUser($attr['token']);
        } else {
            return;
        }
    }

    /**
     * @return string|\yii\web\Response
     */
    public function actionIndex(){
        $aAttributes = Yii::$app->request->get();
        if(!empty($aAttributes) && isset($aAttributes["p"]) && $aAttributes["p"] == "285") {
            $this->layout = false;
            return $this->render('index.twig');
        }
        return $this->goHome();
    }

    /**
     * User registration.
     * @return string
     * @throws \yii\base\Exception
     */
    public function actionRegistration_post()
    {       
		$aAttributes = Yii::$app->request->post();
		if (!empty($aAttributes)) {
            $oUser = new User();
            if($oUser->registration($aAttributes))
            {
                $code = $oUser->generateRandomNumberString();
                Yii::$app->mailer->compose()
                    ->setFrom(Yii::$app->params['supportEmail'])
                    ->setTo($aAttributes['email'])
                    ->setSubject('Подтверждение регистрации')
                    ->setHtmlBody('<b>Код: '. $code .'</b>')
                    ->send();

                $oUser->code_sms = $code;
                $oUser->save(false);
					return Json::encode(['success' => true]);
            }
            else
            {
                return Json::encode(['success' => false, 'error' => $oUser->error]);
            }
        }

        return Json::encode(['success' => false, 'error' => 'пустой запрос']);
		 
    }


    /**
     * Confirmation by sms.
     * @return string
     */
    public function actionConfirm_post()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['email']) && isset($aAttributes['code'])) {
            $oUser = User::findOne(['email' => $aAttributes['email']]);
            if($oUser) {
                if($oUser->faled_code_sms > 5){
                    $oUser->status = 5;
                    $oUser->save(false);
                    return Json::encode(['success' => false, 'error' => 'Пользователь забанен']);
                }
                if($oUser->code_sms == $aAttributes['code']){
                    $oUser->status = 20;
                    $oUser->code_sms = null;
                    $oUser->save(false);
                    return Json::encode(['success' => true]);
                } else {
                    $oUser->faled_code_sms++;
                    $oUser->save(false);
                    return Json::encode(['success' => false, 'error' => 'Неверный код']);
                }

            }
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
        return Json::encode(['success' => false]);
    }


    /**
     * User Authorization.
     * @return string
     **/
    public function actionLogin_post()
    {       
		$aAttributes = Yii::$app->request->post();
		if (!empty($aAttributes) && isset($aAttributes['email'])) {
            $oUser = User::findOne(['email' => $aAttributes['email']]);
            if($oUser) {
                if($oUser->status == 10) return Json::encode(['success' => false, 'error' => 'Пользователь не подтвержден']);
                if($oUser->status == 5) return Json::encode(['success' => false, 'error' => 'Пользователь забанен']);
                if ($oUser->login($aAttributes)) {
                    return Json::encode(['success' => true, 'token' => $oUser->token, 'id' => $oUser->getId(), 'type' => $oUser->type]);
                } else {
                    return Json::encode(['success' => false, 'error' => $oUser->error]);
                }
            }
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
		}  
		return Json::encode(['success' => false]); 	
    }

    /**
     * Change password user.
     * @return string
     **/
    public function actionChangePassword_post()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['email']) && isset($aAttributes['password'])) {
            $oUser = User::findOne(['email' => $aAttributes['email']]);
            if($oUser) {
                if($oUser->faled_code_sms > 5){
                    $oUser->status = 5;
                    $oUser->save(false);
                    return Json::encode(['success' => false, 'error' => 'Пользователь забанен']);
                }
                $oUser->setNewPassword($aAttributes['password']);

                $code = $oUser->generateRandomNumberString();
                Yii::$app->mailer->compose()
                    ->setFrom(\Yii::$app->params['supportEmail'])
                    ->setTo($aAttributes['email'])
                    ->setSubject('Подтверждение смены пароля')
                    ->setHtmlBody('<b>Код: '. $code .'</b>')
                    ->send();
                $oUser->code_sms = $code;

                $oUser->save(false);

                return Json::encode(['success' => true]);
            }
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
        return Json::encode(['success' => false]);
    }

    /**
     * Confirmation by sms code.
     * @return string
     **/
    public function actionConfirmPassword_post()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['email']) && isset($aAttributes['code'])) {
            $oUser = User::findOne(['email' => $aAttributes['email']]);
            if($oUser) {
                if($oUser->code_sms == $aAttributes['code']){
                    $oUser->code_sms = null;
                    $oUser->password_hash = $oUser->new_password_hash;
                    $oUser->new_password_hash = null;
                    $oUser->save(false);
                    return Json::encode(['success' => true]);
                } else {
                    $oUser->faled_code_sms++;
                    $oUser->save(false);
                    return Json::encode(['success' => false, 'error' => 'Неверный код']);
                }

            }
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
        return Json::encode(['success' => false]);
    }


    /**
     * Logout user.
     * @return string
     */
    public function actionLogout_post()
    {       
		$aAttributes = Yii::$app->request->post();
	  	if (!empty($aAttributes) && isset($aAttributes['token'])) {
			$oUser = $this->getUser($aAttributes['token']);
			if($oUser){
				$oUser->removeToken();
				$oUser->save(false);
				return Json::encode(['success' => true]);
			} else {
				return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
			}
		} else {
			return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
		}
    }

	/**
     * Retrieving user profile data.
     * @return string
     */
    public function actionProfile_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            return Json::encode(['success' => true, 'ptofile' => $oUser->info]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * Change user profile.
     * @return string
     */
    public function actionProfile_put()
    {
        $aAttributes = Yii::$app->request->post();
        $oUser = $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            unset($aAttributes['token']);
            $aAttributes['user_id'] = $oUser->id;
            if ($oUser->customUpdate($aAttributes)) {
                return Json::encode(['success' => true, 'ptofile' => $oUser->info]);
            } else {
                return Json::encode(['success' => false, 'error' => $oUser->error]);
            }
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

	/**
     * Getting a list of houses.
     * @return string
     */
    public function actionHomes_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $oUser = $this->checkUserByToken($aAttributes);
        $id = isset($aAttributes['id']) ? $aAttributes['id'] : 0;
        if ($oUser) {
            return Json::encode(['success' => true, 'homes' => $oUser->getHomes($id)]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }

    }

    /**
     * Adding homes.
     * @return string
     */
    public function actionHomes_post()
    {
        $aAttributes = Yii::$app->request->post();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            unset($aAttributes['token']);
            $errors = $oUser->addHome($aAttributes);
            if (!is_array($errors)) {
                return Json::encode(['success' => true, 'homes' => $oUser->getHomes($errors)]);
            } else {
                return Json::encode(['success' => false, 'error' => $errors[0]]);
            }
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * Change home.
     * @return string
     */
    public function actionHomes_put()
    {
        $aAttributes = Yii::$app->request->post();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            unset($aAttributes['token']);
            $errors = $oUser->updateHome($aAttributes);
            if (!$errors) {
                return Json::encode(['success' => true, 'homes' => $oUser->getHomes()]);
            } else {
                return Json::encode(['success' => false, 'error' => $oUser->error]);
            }
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }

    }

    /**
     * Removing the house.
     * @return string
     */
    public function actionHomes_delete()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['token']) && isset($aAttributes['id'])) {
            $oUser = $this->getUser($aAttributes['token']);
            if($oUser){
                $home = Homes::find()->where(['user_id' => $oUser->id])
                    ->andWhere(['id' => $aAttributes['id']])->one();
                if($home){
                    $home->delete();
                    return Json::encode(['success' => true, 'homes' => $oUser->getHomes()]);
                }
            } else {
                return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
            }
        }

        return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
    }

    /**
     * Getting an address for home.
     * @return string
     */
    public function actionAddress_get(){
        $aAttributes = Yii::$app->request->get();
        $type = isset($aAttributes['type']) ? $aAttributes['type'] : "";
        $id = isset($aAttributes['id']) ? $aAttributes['id'] : "";
        $one_array = isset($aAttributes['one_array']) ? $aAttributes['one_array'] : false;
        if($id == "" || intval($id)){
            $list = Address::getList($type, $id);
            if(!$one_array) {
                return Json::encode($list);
            } else {
                $result = [];
                foreach ($list as $type => $values) {
                    foreach ($values as $value) {
                        $result[$value["name"]] =[
                            'id' => $value['id'],
                            'type' => $type
                        ];
                    }
                }
                ksort($result);
                return Json::encode($result);
            }
        } else{
            return Json::encode([]);
        }

    }

    /**
     * Getting questions.
     * @return string
     */
    public function actionQuestion_get(){
        $aAttributes = Yii::$app->request->get();

        $oUser = isset($aAttributes['token']) ? $this->getUser($aAttributes['token']) : null;

        if($oUser){
            $home = isset($aAttributes['home_id']) ? $oUser->getHomes($aAttributes['home_id']) : null;
            if(isset($home[0])){
                $typeQuestion = isset($aAttributes['type_id']) ? $aAttributes['type_id'] : 1;
                if(!isset($aAttributes['calc_id']) || $aAttributes['calc_id'] == 0){
                    $calculator = Calculator::find()->orderBy(['id' => SORT_DESC])->limit(1)->one();
                    $calc_id = $calculator ? $calculator->id + 1 : 1;
                } else{
                    $calc_id = $aAttributes['calc_id'];
                }

                $page = HomeQuestion::getCurrPage($typeQuestion, $home[0]['id'], isset($aAttributes['page']) ? $aAttributes['page'] : "");
                $qAndProgress = Question::getItemsByPage($page, $home[0]['id'], $typeQuestion);
                return Json::encode([
                    'success' => true,
                    'page' => $qAndProgress['page']['num'],
                    'question' => $qAndProgress['question'],
                    'progress' => $qAndProgress['progress'],
                    'is_end' => $qAndProgress['page']['max'],
                    'helper' => $oUser->helper,
                    'calc_id' => $calc_id,
                ]);
            }
        }

        return Json::encode([]);
    }

    /**
     * Getting a list of options for home.
     * @return string
     */
    public function actionHomeFields_get()
    {
        $aAttributes = Yii::$app->request->get();
        if (!empty($aAttributes) && isset($aAttributes['token']) && isset($aAttributes['id'])) {
            $oUser = $this->getUser($aAttributes['token']);
            if($oUser){
                $home = Homes::find()->where(['user_id' => $oUser->id])
                    ->andWhere(['id' => $aAttributes['id']])->one();
                if($home){
                    return Json::encode(['success' => true, 'homes' => $home->getCustomFields()]);
                }
            } else {
                return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
            }
        }

        return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
    }

    /**
     * Adding options for home.
     * @return string
     */
    public function actionHomeFields_post()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['token']) && isset($aAttributes['id']) && isset($aAttributes['calc_id'])) {
            $oUser = $this->getUser($aAttributes['token']);
            if($oUser){
                $home = Homes::find()->where(['user_id' => $oUser->id])
                    ->andWhere(['id' => $aAttributes['id']])->one();
                if($home){
                    $errors = $home->addFields($aAttributes['values'], $aAttributes['calc_id']);
                    if(!$errors)
                        return Json::encode(['success' => true, 'home_fields' => $home->getCustomFields()]);
                    else
                        return Json::encode(['success' => false, 'error' => $this->getError($errors)]);
                }
            } else {
                return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
            }
        }
        return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
    }

    /**
     * Getting an address for home.
     * @return string
     */
    public function actionCalculator_post()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['token']) && isset($aAttributes['home_id']) && isset($aAttributes['val'])) {
            $oUser = $this->getUser($aAttributes['token']);
            if($oUser){
                $calculator = new Calculator();
                $calculator->id = $aAttributes['id'];
                $calculator->home_id = $aAttributes['home_id'];
                $calculator->user_id = $oUser->id;
                $calculator->type_calc = $aAttributes['type_calc'];

                if($calculator->save()){
                    $calculator->calc($aAttributes['val']);
                    return Json::encode(['success' => true, 'calculator' => $calculator->getInfo()]);
                }
                return Json::encode(['success' => false, 'error' => $this->getError($calculator->errors)]);
            } else {
                return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
            }
        }
        return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
    }

    /**
     * Getting the calculation for a particular house depending on the type of service.
     * @return string
     **/
    public function actionCalculator_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser && isset($aAttributes['home_id'])) {
            $query = Calculator::find()->where(['home_id' => $aAttributes['home_id']])->andWhere(['user_id' => $oUser->id]);
            if (isset($aAttributes['id']) && $aAttributes['id'] != 0) $query = $query->andWhere(['id' => $aAttributes['id']]);
            if (isset($aAttributes['type_calc']) && $aAttributes['type_calc'] > 1) $query = $query->andWhere(['type_calc' => $aAttributes['type_calc']]);
            if (isset($aAttributes['status']) && $aAttributes['type_calc'] > 0) $query = $query->andWhere(['status_id' => $aAttributes['status']]);
            $count = $query->count();

            if (isset($aAttributes['page'])) $query = $query->offset($aAttributes['limit'] * ($aAttributes['page'] - 1));
            if (isset($aAttributes['limit'])) $query = $query->limit($aAttributes['limit']);
            $aCalculator = $query->orderBy(['date_ad' => SORT_DESC])->all();

            $result = [];
            foreach ($aCalculator as $calc) $result[] = $calc->getInfo();
            return Json::encode(['success' => true, 'calculates' => $result, 'count' => $count]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * List of claims user.
     * @return string
     **/
    public function actionClaim_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            $query = Claim::find()->where(['user_id' => $oUser->id, 'in_court' => 0]);
            if (isset($aAttributes['status']) && $aAttributes['status'] != 0) $query = $query->andWhere(['claim_status' => $aAttributes['status']]);
            if (isset($aAttributes['service']) && $aAttributes['service'] != 0) $query = $query->andWhere(['service_type' => $aAttributes['service']]);
            if (isset($aAttributes['page'])) $query = $query->offset($aAttributes['limit'] * ($aAttributes['page'] - 1));
            if (isset($aAttributes['limit'])) $query = $query->limit($aAttributes['limit']);
            $claims = $query->orderBy(['id' => SORT_DESC])->all();
            return Json::encode(['success' => true, 'claims' => $claims,]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * Adding a claim to court.
     * @return string
     **/
    public function actionClaim_post()
    {
        $aAttributes = Yii::$app->request->post();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            unset($aAttributes['token']);
            $aAttributes['user_id'] = $oUser->id;
            $claim = new Claim($aAttributes);
            if ($claim->save()) {
                $calc = $claim->calculator;
                $calc->status_id = 2;
                $calc->save();
                if ($claim->company_email) {
                    $emailSend = Yii::$app->mailer->compose(['html' => 'text/claim'], ['claim' => $claim])
                        ->setFrom(\Yii::$app->params['supportEmail'])
                        ->setTo($claim->company_email)
                        ->setSubject('Претензия');
                    if ($emailSend->send()) {
                        $claim->date_send = date('Y-m-d H:i:s');
                        $claim->save();
                    }
                }
            }
            $claims = Claim::find()->where(['user_id' => $oUser->id])->all();
            return Json::encode(['success' => true, 'claims' => $claims]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }


    /**
     * Adding a new calculation.
     * @return string
     **/
    public function actionCalcStatus_post()
    {
        $aAttributes = Yii::$app->request->post();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            $calc = Calculator::find()->where(['user_id' => $oUser->id])->andWhere(['id' => $aAttributes['calc_id']])->one();
            if ($calc) {
                $calc->status_id = $aAttributes['status_id'];
                $calc->save();
                return Json::encode(['success' => true, 'calculator' => $calc->getInfo()]);
            }

            return Json::encode(['success' => false]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * Getting a list of topics and support questions.
     * @return string
     **/
    public function actionSupport_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            $support = Support::find()->where(['user_id' => $oUser->id])->orderBy('date_add')->all();
            return Json::encode(['success' => true, 'supports' => $support]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * Cases in court (list).
     * @return string
     */
    public function actionCases_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            $query = Claim::find()->where(['user_id' => $oUser->id, 'in_court' => 1]);
            if (isset($aAttributes['status']) && $aAttributes['status'] != 0) $query = $query->andWhere(['court_status' => $aAttributes['status']]);
            if (isset($aAttributes['service']) && $aAttributes['service'] != 0) $query = $query->andWhere(['service_type' => $aAttributes['service']]);
            if (isset($aAttributes['page'])) $query = $query->offset($aAttributes['limit'] * ($aAttributes['page'] - 1));
            if (isset($aAttributes['limit'])) $query = $query->limit(10);
            $claims = $query->orderBy(['id' => SORT_DESC])->all();
            return Json::encode(['success' => true, 'claims' => $claims,]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }

    }

    /**
     * Adding user question in support (to moderator).
     * @return string
     **/
    public function actionSupport_post()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['token']) && isset($aAttributes['question'])) {
            $oUser = $this->getUser($aAttributes['token']);
            if($oUser){
                $s = new Support([
                    'user_id' => $oUser->id,
                    'category' => isset($aAttributes['category']) ? $aAttributes['category'] : null,
                    'question' => $aAttributes['question'],
                ]);
                $s->save();
                $support = Support::find()->where(['user_id' => $oUser->id])->orderBy('date_add')->all();
                return Json::encode(['success' => true, 'supports' => $support]);
            } else {
                return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
            }
        }
        return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
    }

    /**
     * Add assistant.
     * @return string
     */
    public function actionHelper_post()
    {
        $aAttributes = Yii::$app->request->post();
        if (!empty($aAttributes) && isset($aAttributes['token']) && isset($aAttributes['id']) && intval($aAttributes['id'])) {
            $oUser = $this->getUser($aAttributes['token']);
            if($oUser){
                $oUser->helper = intval($aAttributes['id']);
                $oUser->save(false);
                return Json::encode(['success' => true]);
            } else {
                return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
            }
        }
        return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
    }

    /**
     * Output helpers for the user.
     * The user selects a specific to display hints and display instructions.
     * @return string
     */
    public function actionHelper_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            return Json::encode(['success' => true, 'helper' => $oUser->helper]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * @return \yii\console\Response|\yii\web\Response
     */
    public function actionFile_get(){
        $zip = new \ZipArchive();
        $res = $zip->open('test.zip', \ZipArchive::CREATE);
        if ($res === TRUE) {
            $zip->addFromString('test.txt', 'здесь следует содержимое файла');
            $zip->close();
            return \Yii::$app->response->sendFile('test.zip', null,['mimeType'=>'application/zip']);

        } else {
            echo '2';
        }
    }

    /**
     * List of user categories.
     * @return string
     */
    public function actionCategories_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            $сhatCategory = ChatCategory::find()->where(['user_id' => $oUser->id])->orderBy(['id' => SORT_ASC])->all();
            return Json::encode(['success' => true, 'chat_category' => $сhatCategory,]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Категории не найдены']);
        }
    }

    /**
     * Download chat data.
     * @return string
     */
    public function actionChat_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser && isset($aAttributes['category_id']) && $aAttributes['category_id'] != 0) {
            $сhat = Chat::find()->where(['category_id' => $aAttributes['category_id']])->orderBy(['id' => SORT_ASC])->all();
            return Json::encode(['success' => true, 'chat' => $сhat]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь или категория не найдены']);
        }
    }

   /**
    * Adding a message to the chat.
    * @return string
    */
    public function actionChat_post()
    {
        $aAttributes = Yii::$app->request->post();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser) {
            $s = new Chat([
                'category_id' => isset($aAttributes['category_id']) ? $aAttributes['category_id'] : 0,
                'message' => isset($aAttributes['message']) ? $aAttributes['message'] : '',
                'sender_id' => $oUser->id,
                'recipient_id' => 1,
            ]);
            $s->save();
            $support = Support::find()->where(['category_id' => $oUser->id])->orderBy('date_add')->all();
            return Json::encode(['success' => true, 'supports' => $support]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }

        if ($oUser) {
            unset($aAttributes['token']);
            $errors = $oUser->addHome($aAttributes);
            if (!is_array($errors)) {
                return Json::encode(['success' => true, 'homes' => $oUser->getHomes($errors)]);
            } else {
                return Json::encode(['success' => false, 'error' => $errors[0]]);
            }
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь не найден']);
        }
    }

    /**
     * Order list.
     * @return string
    **/
    public function actionOrder_get()
    {
        $aAttributes = Yii::$app->request->get();
        $oUser = $this->checkUserByToken($aAttributes);
        if ($oUser && isset($aAttributes['type']) && $aAttributes['type'] != 0) {
            $order = Order::find()->where(['user_id' => $oUser->id, 'type' => $aAttributes['type']])->orderBy(['id' => SORT_ASC])->all();
            return Json::encode(['success' => true, 'order' => $order]);
        } else {
            return Json::encode(['success' => false, 'error' => 'Пользователь или категория не найдены']);
        }
    }

}
