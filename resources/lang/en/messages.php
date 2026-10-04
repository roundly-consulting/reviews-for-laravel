<?php

declare(strict_types=1);

return [
    'rating' => [
        'out_of_range' => 'The rating :given must be between :min and :max.',
    ],
    'review' => [
        'empty' => 'A review must have either a rating or content.',
        'duplicate' => 'This author has already reviewed this subject.',
        'banned_word' => 'This review contains language that is not allowed.',
    ],
    'photos' => [
        'too_many' => '{1} A review may have at most :max photo.|[0,*] A review may have at most :max photos.',
        'disabled' => 'Review photos are disabled; enable reviews.photos.enabled to attach photos.',
    ],
];
