<?php

return [
    'required' => 'The :attribute field is required.',
    'email' => 'The :attribute must be a valid email address.',
    'string' => 'The :attribute must be a string.',
    'integer' => 'The :attribute must be an integer.',
    'min' => ['string' => 'The :attribute must be at least :min characters.', 'numeric' => 'The :attribute must be at least :min.'],
    'max' => ['string' => 'The :attribute may not be greater than :max characters.', 'numeric' => 'The :attribute may not be greater than :max.'],
    'confirmed' => 'The :attribute confirmation does not match.',
    'unique' => 'The :attribute has already been taken.',
    'date' => 'The :attribute is not a valid date.',
    'after' => 'The :attribute must be a date after :date.',
    'in' => 'The selected :attribute is invalid.',
    'regex' => 'The :attribute format is invalid.',
    'attributes' => ['name' => 'name', 'email' => 'email', 'password' => 'password', 'current_password' => 'current password', 'monthly_quota_tokens' => 'monthly quota', 'expires_at' => 'expiry date', 'amount' => 'amount', 'method' => 'payment method', 'customer_phone' => 'phone number', 'locale' => 'language'],
];
