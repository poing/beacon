<?php

return [
    // Salt used for hashing
    'salt'   => env('BEACON_SALT', env('APP_KEY')),

    // Base URL for invitation links
    'domain' => env('BEACON_DOMAIN', env('APP_URL')),

#    'algorithms' => [
#        6  => 'md5',
#        9  => 'sha1',
#       // …
#    ],
    'aliases'    => [
        'md5'    => 'blue44',
        'sha256' => 'yellow256',
        // …
    ],

];