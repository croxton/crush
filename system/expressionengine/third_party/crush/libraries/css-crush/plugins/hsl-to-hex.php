<?php
/**
 * HSL shim
 * Converts HSL values into hex code that works in all browsers
 * 
 * @before
 *     color: hsl( 100, 50%, 50% )
 * 
 * @after
 *    color: #6abf40
 */

CssCrush_Plugin::register( 'hsl-to-hex', array(
    'enable' => 'csscrush__enable_hsl_to_hex',
    'disable' => 'csscrush__disable_hsl_to_hex',
));

function csscrush__enable_hsl_to_hex () {
    CssCrush_Hook::add( 'rule_postalias', 'csscrush__hsl_to_hex' );
}

function csscrush__disable_hsl_to_hex () {
    CssCrush_Hook::remove( 'rule_postalias', 'csscrush__hsl_to_hex' );
}

function csscrush__hsl_to_hex ( CssCrush_Rule $rule ) {

    foreach ( $rule as &$declaration ) {

        if ( ! $declaration->skip && isset( $declaration->functions[ 'hsl' ] ) ) {
            while ( preg_match( '!hsl(\?p\d+\?)!', $declaration->value, $m ) ) {
                $token = $m[1];
                $color = new CssCrush_Color( 'hsl' . CssCrush::$process->fetchToken( $token ) );
                CssCrush::$process->releaseToken( $token );
                $declaration->value = str_replace( $m[0], $color->getHex(), $declaration->value );
            }
        }
    }
}
