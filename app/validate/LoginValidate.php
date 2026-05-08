<?php

namespace app\validate;

use think\Validate;

class LoginValidate extends Validate
{
    protected $rule = [
        'account' => 'require|max:191',
        'password' => 'require|min:6|max:255',
    ];

    protected $message = [
        'account.require' => '请输入用户名、邮箱或手机号',
        'account.max' => '账号长度不能超过191个字符',
        'password.require' => '请输入密码',
        'password.min' => '密码长度不能少于6个字符',
        'password.max' => '密码长度不能超过255个字符',
    ];
}
