<?php

namespace app\models;

use Yii;
use yii\helpers\Inflector;
use yii\web\IdentityInterface;
use yii\base\Security;
/**
 * This is the model class for table "user".
 *
 * @property int $id
 * @property string $first_name
 * @property string $last_name
 * @property string $family
 * @property string $password_hash
 * @property string $new_password_hash
 * @property string $token
 * @property string $phone
 * @property string $email
 * @property int $status
 * @property int $code_sms
 * @property int $faled_code_sms
 * @property string $created_at
 * @property string $updated_at
 * @property string $pasport_data
 * @property string $helper
 * @property string $address
 * @property string $zipcode
 * @property string $type
 */
 
class User extends \yii\db\ActiveRecord implements IdentityInterface
{
	public $repeat_password;
    public $password;
    public $agree_rules;
    public $is_login = false;   

    const STATUS_DELETED = 0;
    const STATUS_CREATE = 10;
    const STATUS_CONFIRM = 20;

	private $aFieldsProfile = [
		'first_name',
		'last_name',
		'family',
		'phone',
		'pasport_data',
        'status',
        'type'
	];
	
    private static $errorsText = [
        'required' => 'Поле обязательно для заполнения.',
        'email' => 'В поле введен некорректый электронный адрес.',
        'email_unique' => 'Электронный адрес зарегестирован в системе.',
        'password_comparison' => 'Пароли не совпадают.',
        'password_check' => 'Пароль неверный.',
        'radio' => 'Необходимо выбрать значение.',
        'checkbox' => 'Необходимо согласие.',
        'bad_symbol' => 'Неверная пара.',
        'login' => 'Неверная пара.',
        'phone_unique' => 'Номер телефона зарегестирован в системе.',
        'type' => 'Тип пользователя.',
    ];
	
    /**
     * @inheritdoc
     */
    public static function tableName()
    {
        return 'user';
    }

    /**
     * @inheritdoc
     */
    public function rules()
    {
         return [           
			['password', 'string', 'min' => 6, 'tooShort' => 'Пароль не менее 6 символов'],
            ['email', 'email', 'message' => User::$errorsText['email']],
            ['email', 'required'],
            [['status'], 'integer'],
            [['created_at', 'updated_at'], 'safe'],
            [['first_name', 'last_name'], 'string', 'max' => 150],           
            ['email', 'unique', 'targetClass' => User::className(), 'message' => User::$errorsText['email_unique']],
            [['family', 'pasport_data'], 'string', 'max' => 250],
            [['password_hash', 'email'], 'string', 'max' => 256],
            [['address'], 'string', 'max' => 450],
            [['token'], 'string', 'max' => 128],
            [['phone'], 'string', 'max' => 15],           
            [['zipcode'], 'string', 'max' => 10],
            [['type'], 'integer'],
        ];
    }

    /**
     * @inheritdoc
     */
    public function attributeLabels()
    {
        return [
            'id' => 'ID',
            'first_name' => 'Имя',
            'last_name' => 'Отчество',
            'family' => 'Фамилия',
            'password_hash' => 'Password Hash',
            'token' => 'Token',
            'phone' => 'Телефон',
            'email' => 'Email',
            'status' => 'Статус',
            'created_at' => 'Дата регистрации',
            'updated_at' => 'Updated At',
            'pasport_data' => 'Паспортные данные',
            'type' => 'Тип пользователя',
        ];
    }

    /**
     * @param $id
     * @return User|null|IdentityInterface
     */
    public static function findIdentity($id)
    {
        return static::findOne(['id' => $id]);
    }

    /**
     * @inheritdoc
     */
    public static function findIdentityByAccessToken($token, $type = null)
    {
        throw new NotSupportedException('"findIdentityByAccessToken" is not implemented.');
    }

    /**
     * @param $name
     * @return User|null
     */
    public static function findByToken($name)
    {
        return static::findOne(['token' => $name]);
    }


    /**
     * @param $phone
     * @return User|null
     */
    public static function findByPhone($phone)
    {
        return static::findOne(['phone' => $phone]);
    }


    /**
     * @return int|mixed|string
     */
    public function getId()
    {
        return $this->getPrimaryKey();
    }

    /**
     * @inheritdoc
     */
    public function getAuthKey()
    {
        return $this->auth_key;
    }

    /**
     * @inheritdoc
     */
    public function validateAuthKey($authKey)
    {
        return $this->getAuthKey() === $authKey;
    }


    /**
     * @param $password
     * @return bool
     */
    public function validatePassword($password)
    {
        return Yii::$app->security->validatePassword($password, $this->password_hash);
    }

    /**
     * Generate password hash from password.
     * @param string $password
     */
    public function setPassword($password)
    {
        $this->password_hash = Yii::$app->security->generatePasswordHash($password);
    }

    /**
     * @param $password
     * @throws \yii\base\Exception
     */
    public function setNewPassword($password)
    {
        $this->new_password_hash = Yii::$app->security->generatePasswordHash($password);
    }


    public function generateToken()
    {
        $this->token = $this->generateRandomString();
    }

    public function removeToken()
    {
        $this->token = null;
    }

    /**
     * Generates "remember me" authentication key
     */
    public function generateAuthKey()
    {
        $this->auth_key = Yii::$app->security->generateRandomString();
    }

    /**
     * @param int $length
     * @return string
     */
    public function generateRandomString($length = 128) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    /**
     * @param int $length
     * @return string
     */
    public function generateRandomNumberString($length = 6) {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }


    /**
     * @param $attributes
     * @param bool $is_save_value
     * @throws \yii\base\Exception
     */
    public function setAttributesCustom($attributes, $is_save_value = false)
    {
        if(isset($attributes['password_hash'])){
            unset($attributes['password_hash']);
        }

        if(isset($attributes['agree'])){
            unset($attributes['agree']);
        }

        if(isset($attributes['password_hash'])){
            unset($attributes['password_hash']);
        }
		if(isset($attributes['is_login'])){
			$this->is_login = $attributes['is_login'];
            unset($attributes['is_login']);
        }

        if(isset($attributes['new_password'])){
            $attributes['password_hash'] = (new Security())->generatePasswordHash($attributes['new_password']);
            unset($attributes['password']);
            unset($attributes['new_password']);
        }

        if(isset($attributes['password']) && $attributes['password']){
            $attributes['password_hash'] = (new Security())->generatePasswordHash($attributes['password']);
        }

        if($is_save_value){
            foreach($attributes as $key => $value){
                $this->$key = $value;
            }
        } else{
            $this->setAttributes($attributes);
        }
    }

    /**
     * @param null $aAttributes
     * @param bool $clearErrors
     * @return bool
     */
    public function validate($aAttributes = null, $clearErrors = true)
    {
        $isValidate = parent::validate();
        if(isset($aAttributes['repeat_password']) && $aAttributes['repeat_password'] == ""){
            $this->addError('repeat_password',User::$errorsText['required']);
            $isValidate = false;
        }
        if(isset($aAttributes['password']) && isset($aAttributes['repeat_password']))
        {
            if($aAttributes['password'] && $aAttributes['repeat_password'] && $aAttributes['password'] != $aAttributes['repeat_password'])
            {
                $this->addError('repeat_password',User::$errorsText['password_comparison']);
                $isValidate = false;
            }
        }
        return $isValidate;
    }

    /**
     * @param $aAttributes
     * @return bool
     */
    public function login($aAttributes)
    {
        if (!$this->validatePassword($aAttributes['password']))
        {
            $this->addError('password', User::$errorsText['login']);
            return false;
        }
		$this->generateToken();
		if($this->save()) {
			return true;
		} else {
			return false;
		}
    }

    /**
     * @param $aAttributes
     * @return bool
     * @throws \yii\base\Exception
     */
    public function registration($aAttributes)
    {
		if(!isset($aAttributes['password'])){
            $this->addError('password', User::$errorsText['required']);
		    return false;
        }
        if(empty($oUser))
        {		
			$this->setAttributesCustom($aAttributes, true);	
			$this->setPassword($aAttributes['password']);
			if($this->validate($aAttributes)){
			   $this->save(false);			  
			   if($this->is_login){
				   $this->login([
					   'password' => $aAttributes['password'], 
					   'phone' => $aAttributes['phone'],
					]);					
				}
				return true;
		   }
        } else {
			$this->addError('phone', User::$errorsText['phone_unique']);
			return false;
		}		
    }

    /**
     * @param $aAttributes
     * @return bool
     */
    public function customUpdate($aAttributes){
		foreach($this->aFieldsProfile as $field){
			if(array_key_exists($field, $aAttributes)){
					$this->$field = $aAttributes[$field];
			}
		}
		return $this->save(false);		
	}

    /**
     * @return array
     */
    public function getInfo()
	{
		
		$result = [];
		foreach($this->aFieldsProfile as $field){
			$result[$field] = [
				'label' => $this->getAttributeLabel($field),
				'value' => $this->$field,
			];
		}
		return $result;
	}

    /**
     * @param null $id
     * @return array
     */
    public function getHomes($id = null)
	{
	    $q = Homes::find()->where(["user_id"=>$this->id]);
	    if($id != 0) $q->andWhere(['id' => $id]);
		$homes = $q->all();

		$result = [];
		foreach ($homes as $h){
            $result[] = $h->info;
        }
        return $result;
	}

    /**
     * @param $aAttributes
     * @return array|mixed
     */
    public function addHome($aAttributes){
	    $home = new Homes();
	    $aAttributes['user_id'] = $this->id;
	    $home->setAttributesCustom($aAttributes);
	    if($home->save()){
	        return $home['id'];
        } else {
	        return $home->errors;
        }
    }

    /**
     * @param $aAttributes
     * @return array|bool
     */
    public function updateHome($aAttributes){
        if(isset($aAttributes['id'])) {
            $home = Homes::findOne($aAttributes['id']);
            if ($home) {
                $home->setAttributes($aAttributes);
                if ($home->save()) {
                    return false;
                } else {
                    return $home->errors;
                }
            }
        } else {
            return ['home' => ['дом не найден']];
        }
        return false;
    }

    /**
     * @return string
     */
    public function getShortName(){
		$result = "";				
		if($this->first_name != "") $result .= substr($this->first_name, 0, 2) . ".";
		if($this->last_name != "") $result .= substr($this->last_name, 0, 2) . ".";
		if($this->family != "") $result .= $this->family;
		return $result;
	}

    /**
     * @return mixed
     */
    public function getError()
    {
        if(count($this->errors)){
            foreach ($this->errors as $key => $errors){
                if(is_array($errors)){
                    foreach ($errors as $k => $e){
                        return $e;
                    }
                } else {
                    return $errors;
                }
            }
        }
    }
}
