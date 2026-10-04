<?php

declare(strict_types=1);

return [
    'rating' => [
        'out_of_range' => 'Hodnotenie :given musí byť v rozsahu od :min do :max.',
    ],
    'review' => [
        'empty' => 'Recenzia musí obsahovať hodnotenie alebo text.',
        'duplicate' => 'Tento autor už k tejto položke pridal recenziu.',
        'banned_word' => 'Táto recenzia obsahuje nepovolené výrazy.',
    ],
    'photos' => [
        'too_many' => '{1} Recenzia môže mať najviac :max fotografiu.|[2,4] Recenzia môže mať najviac :max fotografie.|[5,*] Recenzia môže mať najviac :max fotografií.',
        'disabled' => 'Fotografie v recenziách sú vypnuté; ak ich chcete pripájať, zapnite reviews.photos.enabled.',
    ],
];
