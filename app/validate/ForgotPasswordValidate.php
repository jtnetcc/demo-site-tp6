<?php

namespace app\validate;

use think\Validate;

class ForgotPasswordValidate extends Validate
{
    protected $rule = [
        'account' => 'require|max:191',
        'channel' => 'require|in:email,phone',
    ];

    protected $message = [
        'account.require' => '请输入邮箱或手机号',
        'account.max' => '账号长度不能超过191个字符',
        'channel.require' => '请选择找回方式',
        'channel.in' => '找回方式不正确',
    ];
}
