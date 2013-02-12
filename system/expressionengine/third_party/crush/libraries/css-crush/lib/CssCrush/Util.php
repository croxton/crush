<?php
/**
 *
 *  General utilities.
 *
 */
class CssCrush_Util
{
    // Create html attribute string from array.
    static public function htmlAttributes ( array $attributes )
    {
        $attr_string = '';
        foreach ( $attributes as $name => $value ) {
            $value = htmlspecialchars( $value, ENT_COMPAT, 'UTF-8', false );
            $attr_string .= " $name=\"$value\"";
        }
        return $attr_string;
    }

    static public function normalizePath ( $path, $strip_drive_letter = false )
    {
        if ( $strip_drive_letter ) {
            $path = preg_replace( '!^[a-z]\:!i', '', $path );
        }
        // Backslashes and repeat slashes to a single forward slash.
        $path = rtrim( preg_replace( '![\\\\/]+!', '/', $path ), '/' );

        // Removing redundant './'.
        $path = str_replace( '/./', '/', $path );
        if ( strpos( $path, './' ) === 0 ) {
            $path = substr( $path, 2 );
        }

        return CssCrush_Util::simplifyPath( $path );
    }

    static public function simplifyPath ( $path )
    {
        // Reduce redundant path segments (issue #32):
        // e.g 'foo/../bar' => 'bar'
        $patt = '~[^/.]+/\.\./~S';
        while ( preg_match( $patt, $path ) ) {
            $path = preg_replace( $patt, '', $path );
        }
        return $path;
    }

    static public function find ()
    {
        foreach ( func_get_args() as $file ) {
            $file_path = CssCrush::$config->location . '/' . $file;
            if ( file_exists( $file_path ) ) {
                return $file_path;
            }
        }
        return false;
    }

    static public function stripCommentTokens ( $str )
    {
        return preg_replace( CssCrush_Regex::$patt->cToken, '', $str );
    }

    static public function normalizeWhiteSpace ( $str )
    {
        $replacements = array(
            // Convert all whitespace sequences to a single space.
            '!\s+!S' => ' ',
            // Trim bracket whitespace where it's safe to do it.
            '!([\[(]) | ([\])])| ?([{}]) ?!S' => '${1}${2}${3}',
            // Trim whitespace around delimiters and special characters.
            '! ?([;,]) ?!S' => '$1',
        );
        return preg_replace(
            array_keys( $replacements ), array_values( $replacements ), $str );
    }

    static public function splitDelimList ( $str, $delim = ',', $trim = true )
    {
        $do_preg_split = strlen( $delim ) > 1 ? true : false;

        if ( ! $do_preg_split && strpos( $str, $delim ) === false ) {
            if ( $trim ) {
                $str = trim( $str );
            }
            return array( $str );
        }

        if ( strpos( $str, '(' ) !== false ) {
            $match_count
                = preg_match_all( CssCrush_Regex::$patt->balancedParens, $str, $matches );
        }
        else {
            $match_count = 0;
        }

        if ( $match_count ) {
            $keys = array();
            foreach ( $matches[0] as $index => &$value ) {
                $keys[] = "?$index?";
            }
            $str = str_replace( $matches[0], $keys, $str );
        }

        if ( $do_preg_split ) {
            $list = preg_split( '!' . $delim . '!', $str );
        }
        else {
            $list = explode( $delim, $str );
        }

        if ( $match_count ) {
            foreach ( $list as &$value ) {
                $value = str_replace( $keys, $matches[0], $value );
            }
        }

        if ( $trim ) {
            $list = array_map( 'trim', $list );
        }

        return $list;
    }

    static public function getLinkBetweenDirs ( $dir1, $dir2 )
    {
        // Normalise the paths.
        $dir1 = trim( CssCrush_Util::normalizePath( $dir1, true ), '/' );
        $dir2 = trim( CssCrush_Util::normalizePath( $dir2, true ), '/' );

        // The link between.
        $link = '';

        if ( $dir1 != $dir2 ) {

            // Split the directory paths into arrays so we can compare segment by segment.
            $dir1_segs = explode( '/', $dir1 );
            $dir2_segs = explode( '/', $dir2 );

            // Shift the segments until they are on different branches.
            while ( isset( $dir1_segs[0] ) && isset( $dir2_segs[0] ) && ( $dir1_segs[0] === $dir2_segs[0] ) ) {
                array_shift( $dir1_segs );
                array_shift( $dir2_segs );
            }

            $link = str_repeat( '../', count( $dir1_segs ) ) . implode( '/', $dir2_segs );
        }

        // Add closing slash.
        return $link !== '' ? rtrim( $link, '/' ) . '/' : '';
    }
}
