<?php

namespace backend\controllers;
use backend\models\User;
use yii\filters\AccessControl;
use yii\helpers\Json;
use yii\web\Controller;

class UserController extends Controller{
    //>>管理员列表展示
    public function actionIndex(){
        $rows = User::find()->where(['>','status',0])->all();
        return $this->render('index',['rows'=>$rows]);
    }
    //>>管理员添加列表
    public function actionAdd(){
        //>>给新增用户添加权限
        $auth = \Yii::$app->authManager;
        $roles = $auth->getRoles();
        $r = [];
        foreach ($roles as $role){
            $r[$role->name]=$role->description;
        }
        $model = new User();
        //>>用户添加场景
        $model->scenario = User::SCENARIO_ADD_USER;
        $request = \Yii::$app->request;

        if($request->isPost){
            $model->load($request->post());
            if($model->validate()){
                if( $model->verify_password == $model->password_hash ){
                    //将密码设为加盐加密
                    $password_hash = \Yii::$app->security->generatePasswordHash($model->password_hash);
                    $model->password_hash = $password_hash;
                }
                $model->auth_key=uniqid();
                $model->created_at=time();
                $model->updated_at=time();

                $model->save(false);
                foreach($model->role as $role){
                    $role = $auth->getRole($role);
                    $auth->assign($role,$model->id);
                }
                \Yii::$app->session->setFlash('success','添加成功');
                return $this->redirect(['user/index']);
            }
        }
        return $this->render('add',['model'=>$model,'r'=>$r]);
    }
    //>>管理员修改
    public function actionEditOwn($id){
        $request = \Yii::$app->request;
        $row = User::find()->where(['id'=>$id])->one();

        $row->password_hash = '';
        if($request->isPost){
            $row->load($request->post());

                if($row->isSelf()){
                //>>是本人修改密码
                if($row->validate()){
                    if( $row->verify_password == $row->password_hash ){
                        //将密码设为加盐加密
                        $password_hash = \Yii::$app->security->generatePasswordHash($row->password_hash);
                        $row->password_hash = $password_hash;
                    }
                    $row->updated_at = time();
                    $row->save(false);
                    \Yii::$app->session->setFlash('success','修改成功');
                    return $this->redirect(['user/index']);
                }
            }

        }
        return $this->render('edit-own',['row'=>$row]);
    }
    //>>管理员删除(禁用)
    public function actionDelete($id){
        $row = User::find()->where(['id'=>$id])->one();
        $row->status = 0;
        $res = $row->save(false);
        if($res){
            return Json::encode(true);
        }else{
            return Json::encode(false);
        }
    }
    //>>管理员修改用户信息
    public function actionAdminEdit($id){
        //修改用户角色
        $auth = \Yii::$app->authManager;
        $roles = $auth->getRoles();
        $r = [];
        foreach ($roles as $role){
            $r[$role->name]=$role->description;
        }
        $row = User::find()->where(['id'=>$id])->one();
        //>>验证场景
        $row->scenario = User::SCENARIO_EDIT_USER;
        //>>获取这个用户的所有角色
        $roles = $auth->getRolesByUser($row->id);
        $v=[];
        foreach($roles as $role){
            $v[]=$role->name;
        }
        $row->role = $v;
        //var_dump($v);die;
        $row->password_hash = '';
        $request = \Yii::$app->request;
        if($request->isPost){
            $row->load($request->post());
            if($row->validate()){
                if( $row->verify_password == $row->password_hash ){
                    //将密码设为加盐加密
                    $password_hash = \Yii::$app->security->generatePasswordHash($row->password_hash);
                    $row->password_hash = $password_hash;
                }
//                var_dump($row->role);die;
                $row->updated_at = time();
                $row->save(false);
                $auth->revokeAll($row->id);
                foreach ($row->role as $role){
                    $role = $auth->getRole($role);
                    $auth->assign($role,$row->id);
                }

                \Yii::$app->session->setFlash('success','修改成功');
                return $this->redirect(['user/index']);
            }
        }
         return  $this->render('admin-edit',['row'=>$row,'r'=>$r]);
    }
    //>>权限
    public function behaviors()
    {
        return [
            'acf'=>[
                'class'=>AccessControl::className(),
                'rules'=>[
                    [
                        'allow'=>true,
                        'actions'=>['index'],
                        'roles'=>['?','@']
                    ],
                    [
                        'allow'=>true,
                        'actions'=>['add','edit-own','delete','admin-edit'],
                        'roles'=>['@'],
                    ]
                ],
            ],
        ];
    }
}