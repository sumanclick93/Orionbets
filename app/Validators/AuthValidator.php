<?php

declare(strict_types=1);

namespace App\Validators;

use App\Core\Validator;

final class AuthValidator
{
    public static function registration(array $input): Validator
    {
        return Validator::make($input, [
            'first_name' => 'required|max:80',
            'last_name' => 'required|max:80',
            'email' => 'required|email',
            'password' => 'required|min:10|confirmed',
            'age' => 'accepted',
            'terms' => 'accepted',
            'privacy' => 'accepted',
        ]);
    }
}
