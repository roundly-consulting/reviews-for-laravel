<?php

declare(strict_types=1);

return [
    'status' => [
        'pending' => 'Pending',
        'approved' => 'Approved',
        'rejected' => 'Rejected',
    ],
    'rating' => [
        'out_of_range' => 'The rating :given must be between :min and :max.',
    ],
    'review' => [
        'empty' => 'A review must have either a rating or content.',
        'duplicate' => 'This author has already reviewed this subject.',
        'banned_word' => 'This review contains language that is not allowed.',
    ],
];
