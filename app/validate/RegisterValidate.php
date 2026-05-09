<?php

namespace app\validate;

use think\Validate;

class RegisterValidate extends Validate
{
    protected $rule = [
        'register_type' => 'require|in:username,email,phone',
        'account' => 'require|max:191',
        'code' => 'max:16',
        'password' => 'require|min:6|max:255',
        'display_name' => 'max:100',
    ];

    protected $message = [
        'register_type.require' => '请选择注册方式',
        'register_type.in' => '注册方式不正确',
        'account.require' => '请输入账号',
        'account.max' => '账号长度不能超过191个字符',
        'code.max' => '验证码长度不能超过16个字符',
        'password.require' => '请输入密码',
        'password.min' => '密码长度不能少于6个字符',
        'password.max' => '密码长度不能超过255个字符',
        'display_name.max' => '显示名长度不能超过100个字符',
    ];
}
