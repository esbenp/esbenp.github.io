<?php

class CreateUserRequest extends Request
{
    public function authorize()
    {
        return true;
    }

    public function rules()
    {
        return [
            'user' => 'required|array',
            'user.email' => 'required|email'
        ];
    }
}
