<?php

namespace App\Support;

use Illuminate\Validation\Rules\Password;

class PasswordPolicy
{
    /** @return array<int, mixed> */
    public static function rules(string $presence = 'required'): array
    {
        return [
            $presence,
            'string',
            Password::min(8)->mixedCase()->numbers()->symbols(),
            'confirmed',
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'password.mixed' => 'A senha deve conter ao menos uma letra maiúscula e uma minúscula.',
            'password.numbers' => 'A senha deve conter ao menos um número.',
            'password.symbols' => 'A senha deve conter ao menos um caractere especial.',
            'password.confirmed' => 'A confirmação da senha não confere.',
        ];
    }
}
