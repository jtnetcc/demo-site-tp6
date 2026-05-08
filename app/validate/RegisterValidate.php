<?php

namespace app\validate;

use think\Validate;

class RegisterValidate extends Validate
{
    protected $rule = [
        'username' => 'require|regex:^[A-Za-z0-9_-]{3,64}$',
        'email' => 'email|max:191',
        'phone' => 'regex:^\+?[0-9]{6,20}$|max:32',
        'password' => 'require|min:6|max:255',
        'display_name' => 'max:100',
    ];

    protected $message = [
        'username.require' => '请输入用户名',
        'username.regex' => '用户名只能包含字母、数字、下划线或短横线，长度为3到64位',
        'email.email' => '邮箱格式不正确',
        'email.max' => '邮箱长度不能超过191个字符',
        'phone.regex' => '手机号格式不正确',
        'phone.max' => '手机号长度不能超过32个字符',
        'password.require' => '请输入密码',
        'password.min' => '密码长度不能少于6个字符',
        'password.max' => '密码长度不能超过255个字符',
        'display_name.max' => '显示名长度不能超过100个字符',
    ];
}
