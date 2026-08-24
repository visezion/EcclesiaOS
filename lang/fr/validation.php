<?php

return [
    'accepted' => 'Le champ :attribute doit être accepté.',
    'array' => 'Le champ :attribute doit être un tableau.',
    'before_or_equal' => 'Le champ :attribute doit être une date antérieure ou égale au :date.',
    'confirmed' => 'La confirmation du champ :attribute ne correspond pas.',
    'date' => 'Le champ :attribute doit être une date valide.',
    'email' => 'Le champ :attribute doit être une adresse e-mail valide.',
    'in' => 'La valeur sélectionnée pour :attribute est invalide.',
    'max' => ['array' => 'Le champ :attribute ne doit pas contenir plus de :max éléments.', 'string' => 'Le champ :attribute ne doit pas dépasser :max caractères.'],
    'prohibited' => 'Le champ :attribute est interdit.',
    'required' => 'Le champ :attribute est obligatoire.',
    'required_if' => 'Le champ :attribute est obligatoire lorsque :other vaut :value.',
    'required_without' => 'Le champ :attribute est obligatoire lorsque :values n’est pas présent.',
    'string' => 'Le champ :attribute doit être du texte.',
    'unique' => 'La valeur du champ :attribute est déjà utilisée.',
    'attributes' => [
        'first_name' => 'prénom', 'last_name' => 'nom', 'email' => 'adresse e-mail', 'phone' => 'numéro de téléphone',
        'campus_id' => 'branche ou campus', 'ministry_id' => 'département ou ministère', 'preferred_contact' => 'moyen de contact préféré',
        'password' => 'mot de passe', 'privacy_consent' => 'consentement relatif à la confidentialité',
    ],
];
