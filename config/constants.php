<?php
/**
 * Constantes métier de M'trix
 */

// Réservation
define('RESERVATION_DURATION_MINUTES', 5);
define('QUEUE_EXPIRY_MINUTES', 30);

// Couleurs disponibles
define('AVAILABLE_COLORS', [
    'noir' => 'Noir',
    'rouge' => 'Rouge',
    'blanc' => 'Blanc',
    'marron' => 'Marron',
    'noir-fume' => 'Noir Fumé',
]);

// Statuts de commande
define('ORDER_STATUS_ATTENTE', 'attente');
define('ORDER_STATUS_A_CONFIRMER', 'a_confirmer');
define('ORDER_STATUS_PAYEE', 'payee');
define('ORDER_STATUS_LIVREE', 'livree');
define('ORDER_STATUS_ANNULEE', 'annulee');
define('ORDER_STATUS_EXPIREE', 'expiree');

// Statuts de paiement
define('PAYMENT_STATUS_INIT', 'initiée');
define('PAYMENT_STATUS_PENDING', 'en_cours');
define('PAYMENT_STATUS_SUCCESS', 'reussie');
define('PAYMENT_STATUS_FAILED', 'refusee');
define('PAYMENT_STATUS_TIMEOUT', 'timeout');

// Devise
define('DEFAULT_CURRENCY', 'XOF');
define('CURRENCY_SYMBOL', 'F');

// Paliers de prix
define('PALIERS_CONFIG', [
    [
        'numero' => 1,
        'prix' => 3500,
        'places' => 1,
    ],
    [
        'numero' => 2,
        'prix' => 4500,
        'places' => 2,
    ],
    [
        'numero' => 3,
        'prix' => 5000,
        'places' => 2,
    ],
    [
        'numero' => 4,
        'prix' => 6000,
        'places' => 5,
    ],
    [
        'numero' => 5,
        'prix' => 7000,
        'places' => 5,
    ],
]);

// Réseaux sociaux
define('SOCIAL_LINKS', [
    'whatsapp' => 'https://wa.me/' . (getenv('WHATSAPP') ?: '22958779933'),
    'instagram' => 'https://instagram.com/' . (getenv('INSTAGRAM') ?: 'mtrix229'),
    'tiktok' => 'https://tiktok.com/@' . (getenv('TIKTOK') ?: 'mtrix2290'),
]);

// Timing
define('MAIL_RESEND_DELAY_HOURS', 1);
define('STATS_CACHE_MINUTES', 5);

// Admin
define('ADMIN_INACTIVITY_TIMEOUT_MINUTES', 60);

// Pages par défaut
define('ITEMS_PER_PAGE', 50);
