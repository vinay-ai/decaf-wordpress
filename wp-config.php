<?php
/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the
 * installation. You don't have to use the web site, you can
 * copy this file to "wp-config.php" and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * MySQL settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

// ** MySQL settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'decafDB' );

/** MySQL database username */
define( 'DB_USER', 'wpadmin' );

/** MySQL database password */
define( 'DB_PASSWORD', 'wppass' );

/** MySQL hostname */
define( 'DB_HOST', 'localhost' );

/** Database Charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The Database Collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

/**#@+
 * Authentication Unique Keys and Salts.
 *
 * Change these to different unique phrases!
 * You can generate these using the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}
 * You can change these at any point in time to invalidate all existing cookies. This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define( 'AUTH_KEY',         'h$U9H4Y}/sP;Z::%^];GLrSJd:-N_E0EFKRIK_4g?6AtE$8)Y#X[lCc@;zX1x5;0' );
define( 'SECURE_AUTH_KEY',  '8$}{1gkt4F[o7>p-Pc<UrdY,VE(1fn77VL9E5v$$ Of8k}Fl98Ep?:`#+)[eY1aQ' );
define( 'LOGGED_IN_KEY',    'XXedN;mA*6<`c+GOKm-2OnrHVhciO6s[lv>%$c`Bg<^?JH#UNAM5sR:Or)CX91Rx' );
define( 'NONCE_KEY',        '4^U/YmiZuQN&jQN5 WwIAn]#LOV|ICC|dr<hrgx#vEvP:=v[g&5}@fvb03k3}=|C' );
define( 'AUTH_SALT',        'n4P.ypnN=H,3$yBLRfq30lFJ,kG}U~JBiR59&$5m}I{xt>B0|Ij+v2LU9Kr _%X:' );
define( 'SECURE_AUTH_SALT', 's@f[%yrI(s=!{aWA,vA0m@{LSBCRHuF ^hQm&y|HbZQxE=6<L;?>Id62%v:9#8L*' );
define( 'LOGGED_IN_SALT',   '1zW$epnFggwkc5Lg:w#7 Mjz<[i2IYroL%cr3qTG3V0KUI&<^VC$Q([0L ;p#Ala' );
define( 'NONCE_SALT',       ' 3u>1MfcSS@GnzLMsB{/`VWdT]P_@sp0<g2zPJxB4m<yh<@~BF~w=4`99u,J{T*H' );

/**#@-*/

/**
 * WordPress Database Table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://wordpress.org/support/article/debugging-in-wordpress/
 */
define( 'WP_DEBUG', false );

/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
