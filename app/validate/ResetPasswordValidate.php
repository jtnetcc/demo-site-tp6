<?php

namespace app\validate;

use think\Validate;

class ResetPasswordValidate extends Validate
{
    protected $rule = [
        'password' => 'require|min:6|max:255',
        'password_confirm' => 'require|max:255',
    ];

    protected $message = [
        'password.require' => '请输入新密码',
        'password.min' => '密码长度不能少于6个字符',
        'password.max' => '密码长度不能超过255个字符',
        'password_confirm.require' => '请确认新密码',
        'password_confirm.max' => '确认密码长度不能超过255个字符',
    ];
}
