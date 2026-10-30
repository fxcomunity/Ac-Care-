<?php
return [
    'public_key' => envValue('VAPID_PUBLIC_KEY'),
    'private_key' => envValue('VAPID_PRIVATE_KEY'),
    'subject' => envValue('VAPID_SUBJECT', 'mailto:admin@afeteknik.local'),
];