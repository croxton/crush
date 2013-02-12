<?php
/**
 *
 * Main script. Includes core public API.
 *
 */
class CssCrush
{
    const VERSION = '1.9';

    // Global settings.
    static public $config;

    // The current active process.
    static public $process;

    // Init called once manually post class definition.
    static public function init ()
    {
        self::$config = new stdclass();

        // Path to the project root folder.
        self::$config->location = dirname(dirname(dirname(__FILE__)));

        // Establish version id.
        self::$config->version = new CssCrush_Version( self::VERSION );

        // Set the docRoot reference.
        self::setDocRoot();

        // Set the default IO handler.
        self::$config->io = 'CssCrush_IO';

        // Shared resources.
        self::$config->vars = array();
        self::$config->aliases = array();
        self::$config->selectorAliases = array();
        self::$config->plugins = array();

        // Default options.
        self::$config->options = new CssCrush_Options( array(

            // Minify. Set false for formatting and comments.
            'minify' => true,

            // Alternative formatter to use for un-minified output.
            'formatter' => null,

            // Append 'checksum' to output file name.
            'versioning' => true,

            // Use the template boilerplate.
            'boilerplate' => true,

            // Variables passed in at runtime.
            'vars' => array(),

            // Enable/disable the cache.
            'cache' => true,

            // Output filename. Defaults the host-filename.
            'output_file' => null,

            // Output directory. Defaults to the same directory as the host file.
            'output_dir' => null,

            // Alternative document_root may be used to workaround server aliases and rewrites.
            'doc_root' => null,

            // Vendor target. Only apply prefixes for a specific vendor, set to 'none' for no prefixes.
            'vendor_target' => 'all',

            // Whether to rewrite the url references inside imported files.
            'rewrite_import_urls' => true,

            // List of plugins to enable (as Array of names).
            'enable' => null,

            // List of plugins to disable (as Array of names).
            'disable' => null,

            // Debugging options.
            // Set true to output sass debug-info stubs that work with development tools like FireSass.
            'trace' => array(),

            // Force newline type on output files. Defaults to the current platform newline.
            // Options: 'windows' (or 'win'), 'unix', 'use-platform'
            'newlines' => 'use-platform',
        ));

        // Include and register stock formatters.
        require_once self::$config->location . '/misc/formatters.php';
    }

    static protected function setDocRoot ( $doc_root = null )
    {
        // Get document_root reference
        // $_SERVER['DOCUMENT_ROOT'] is unreliable in certain CGI/Apache/IIS setups

        if ( ! $doc_root ) {

            $script_filename = $_SERVER[ 'SCRIPT_FILENAME' ];
            $script_name = $_SERVER[ 'SCRIPT_NAME' ];

            if ( $script_filename && $script_name ) {

                $len_diff = strlen( $script_filename ) - strlen( $script_name );

                // We're comparing the two strings so normalize OS directory separators
                $script_filename = str_replace( '\\', '/', $script_filename );
                $script_name = str_replace( '\\', '/', $script_name );

                // Check $script_filename ends with $script_name
                if ( substr( $script_filename, $len_diff ) === $script_name ) {

                    $doc_root = realpath( substr( $script_filename, 0, $len_diff ) );
                }
            }

            if ( ! $doc_root ) {

                // If doc_root is still falsy, fallback to DOCUMENT_ROOT
                $doc_root = realpath( $_SERVER[ 'DOCUMENT_ROOT' ] );
            }

            if ( ! $doc_root ) {

                // If doc_root is still falsy, log an error
                $error = "Could not get a document_root reference.";
                CssCrush::logError( $error );
                trigger_error( __METHOD__ . ": $error\n", E_USER_NOTICE );
            }
        }

        self::$config->docRoot = CssCrush_Util::normalizePath( $doc_root );
    }

    // Aliases and macros loader.
    static public function loadAssets ()
    {
        static $called;
        if ( $called ) {
            return;
        }

        // Find an aliases file in the root directory
        // a local file overrides the default
        $aliases_file = CssCrush_Util::find( 'Aliases-local.ini', 'Aliases.ini' );

        // Load aliases file if it exists
        if ( $aliases_file ) {

            if ( $result = @parse_ini_file( $aliases_file, true ) ) {

                self::$config->aliases = $result;

                // Declaration aliases require a little preprocessing.
                if ( isset( self::$config->aliases[ 'declarations' ] ) ) {
                    $store = array();
                    foreach ( self::$config->aliases[ 'declarations' ] as $prop_val => $aliases ) {
                        list( $prop, $value ) = array_map( 'trim', explode( ':', $prop_val ) );
                        foreach ( $aliases as &$alias ) {
                            $alias = explode( ':', $alias );
                        }
                        $store[ $prop ][ $value ] = $aliases;
                    }
                    self::$config->aliases[ 'declarations' ] = $store;
                }

                // Ensure all alias groups are at least set (issue #34)
                self::$config->bareAliasGroups = array(
                    'properties' => array(),
                    'functions' => array(),
                    'declarations' => array(),
                    'at-rules' => array(),
                );
                self::$config->aliases += self::$config->bareAliasGroups;
            }
            else {
                trigger_error( __METHOD__ . ": Aliases file could not be parsed.\n", E_USER_NOTICE );
            }
        }
        else {
            trigger_error( __METHOD__ . ": Aliases file not found.\n", E_USER_NOTICE );
        }

        // Find a plugins file in the root directory,
        // a local file overrides the default
        $plugins_file = CssCrush_Util::find( 'Plugins-local.ini', 'Plugins.ini' );

        // Load plugins
        if ( $plugins_file ) {
            if ( $result = @parse_ini_file( $plugins_file ) ) {
                foreach ( $result[ 'plugins' ] as $plugin_name ) {
                    // Backwards compat.
                    $plugin_name = basename( $plugin_name, '.php' );
                    if ( CssCrush_Plugin::load( $plugin_name ) ) {
                        self::$config->plugins[ $plugin_name ] = true;
                    }
                }
            }
            else {
                trigger_error( __METHOD__ . ": Plugin file could not be parsed.\n", E_USER_NOTICE );
            }
        }

        $called = true;
    }


    #############################
    #  External API.

    /**
     * Process host CSS file and return a new compiled file.
     *
     * @param string $file  URL or System path to the host CSS file.
     * @param mixed $options  An array of options or null.
     * @return string  The public path to the compiled file or an empty string.
     */
    static public function file ( $file, $options = null )
    {
        self::$process = new CssCrush_Process( $options );

        $config = self::$config;
        $process = self::$process;
        $options = $process->options;
        $doc_root = $process->docRoot;

        // Since we're comparing strings, we need to iron out OS differences.
        $file = str_replace( '\\', '/', $file );

        // Finding the system path of the input file and validating it.
        $pathtest = true;
        if ( strpos( $file, $doc_root ) === 0 ) {
            // System path.
            $pathtest = $process->setContext( dirname( $file ) );
        }
        else if ( strpos( $file, '/' ) === 0 ) {
            // WWW root path.
            $pathtest = $process->setContext( dirname( $doc_root . $file ) );
        }
        else {
            // Relative path.
            $pathtest = $process->setContext( dirname( dirname( __FILE__ ) . '/' . $file ) );
        }

        if ( ! $pathtest ) {
            // Main directory not found or is not writable return an empty string.
            return '';
        }

        // Validate file input.
        if ( ! CssCrush_IO::registerInputFile( $file ) ) {
            return '';
        }

        // Create a filename that will be used later
        // Used in validateCache, and writing to filesystem
        $process->output->filename = $process->ioCall( 'getOutputFileName' );

        // Caching.
        if ( $options->cache ) {

            // Load the cache data.
            $process->cacheData = $process->ioCall( 'getCacheData' );

            // If cache is enabled check for a valid compiled file.
            $valid_compliled_file = $process->ioCall( 'validateExistingOutput' );

            if ( is_string( $valid_compliled_file ) ) {
                return $valid_compliled_file;
            }
        }

        // Compile.
        $stream = $process->compile();

        // Create file and return url. Return empty string on failure.
        if ( $url = $process->ioCall( 'write', $stream ) ) {
            $timestamp = $options->versioning ? '?' . time() : '';
            return "$url$timestamp";
        }
        else {
            return '';
        }
    }

    /**
     * Process host CSS file and return an HTML link tag with populated href.
     *
     * @param string $file  Absolute or relative path to the host CSS file.
     * @param mixed $options  An array of options or null.
     * @param array $attributes  An array of HTML attributes.
     * @return string  HTML link tag or error message inside HTML comment.
     */
    static public function tag ( $file, $options = null, $attributes = array() )
    {
        $file = self::file( $file, $options );

        if ( ! empty( $file ) ) {

            // On success return the tag with any custom attributes
            $attributes[ 'rel' ] = 'stylesheet';
            $attributes[ 'href' ] = $file;

            // Should media type be forced to 'all'?
            if ( ! isset( $attributes[ 'media' ] ) ) {
                $attributes[ 'media' ] = 'all';
            }
            $attr_string = CssCrush_Util::htmlAttributes( $attributes );
            return "<link$attr_string />\n";
        }
        else {

            // Return an HTML comment with message on failure
            $class = __CLASS__;
            $errors = implode( "\n", self::$process->errors );
            return "<!-- $class: $errors -->\n";
        }
    }

    /**
     * Process host CSS file and return CSS as text wrapped in html style tags.
     *
     * @param string $file  Absolute or relative path to the host CSS file.
     * @param mixed $options  An array of options or null.
     * @param array $attributes  An array of HTML attributes, set false to return CSS text without tag.
     * @return string  HTML link tag or error message inside HTML comment.
     */
    static public function inline ( $file, $options = null, $attributes = array() )
    {
        // For inline output set boilerplate to not display by default
        if ( ! is_array( $options ) ) {
            $options = array();
        }
        if ( ! isset( $options[ 'boilerplate' ] ) ) {
            $options[ 'boilerplate' ] = false;
        }

        $file = self::file( $file, $options );

        if ( ! empty( $file ) ) {

            // On success fetch the CSS text
            $content = file_get_contents( self::$process->output->dir . '/'
                . self::$process->output->filename );
            $tag_open = '';
            $tag_close = '';

            if ( is_array( $attributes ) ) {
                $attr_string = CssCrush_Util::htmlAttributes( $attributes );
                $tag_open = "<style$attr_string>";
                $tag_close = '</style>';
            }
            return "$tag_open{$content}$tag_close\n";
        }
        else {

            // Return an HTML comment with message on failure
            $class = __CLASS__;
            $errors = implode( "\n", self::$process->errors );
            return "<!-- $class: $errors -->\n";
        }
    }

    /**
     * Compile a raw string of CSS string and return it.
     *
     * @param string $string  CSS text.
     * @param mixed $options  An array of options or null.
     * @return string  CSS text.
     */
    static public function string ( $string, $options = null )
    {
        // For strings set boilerplate to not display by default
        if ( ! isset( $options[ 'boilerplate' ] ) ) {
            $options[ 'boilerplate' ] = false;
        }

        self::$process = new CssCrush_Process( $options );

        $config = self::$config;
        $process = self::$process;
        $options = $process->options;

        // Set the path context if one is given.
        // Fallback to document root.
        if ( ! empty( $options->context ) ) {
            $process->setContext( $options->context, false );
        }
        else {
            $process->setContext( $process->docRoot, false );
        }

        // Set the string on the input object.
        $process->input->string = $string;

        // Import files may be ignored.
        if ( isset( $options->no_import ) ) {
            $process->input->importIgnore = true;
        }

        // Compile and return.
        return $process->compile();
    }

    /**
     * Add variables globally.
     *
     * @param mixed $var  Assoc array of variable names and values, a php ini filename or null.
     */
    static public function globalVars ( $vars )
    {
        $config = self::$config;

        // Merge into the stack, overrides existing variables of the same name
        if ( is_array( $vars ) ) {
            $config->vars = array_merge( $config->vars, $vars );
        }
        // Test for a file. If it is attempt to parse it
        elseif ( is_string( $vars ) && file_exists( $vars ) ) {
            if ( $result = @parse_ini_file( $vars ) ) {
                $config->vars = array_merge( $config->vars, $result );
            }
        }
        // Clear the stack if the argument is explicitly null
        elseif ( is_null( $vars ) ) {
            $config->vars = array();
        }
    }

    /**
     * Clear config file and compiled files for the specified directory.
     *
     * @param string $dir  System path to the directory.
     */
    static public function clearCache ( $dir = '' )
    {
        return $process->ioCall( 'clearCache', $dir );
    }

    /**
     * Get debug info.
     * Depends on arguments passed to the trace option.
     *
     * @param string $name  Name of stat to retrieve. Leave blank to retrieve all.
     */
    static public function stat ( $name = null )
    {
        $process = CssCrush::$process;
        $stat = $process->stat;

        // Get logged errors as late as possible.
        if ( in_array( 'errors', $process->options->trace ) && ( ! $name || 'errors' === $name ) ) {
            $stat[ 'errors' ] = $process->errors;
        }

        if ( $name && array_key_exists( $name, $stat ) ) {
            return array( $name => $stat[ $name ] );
        }

        // Lose stats that are only useful internally.
        unset( $stat[ 'compile_start_time' ] );

        return $stat;
    }


    #############################
    #  Internal development.

    static public $logging = false;

    static public function log ( $arg = null, $label = '' )
    {
        if ( ! self::$logging ) {
            return;
        }
        static $log = '';

        $args = func_get_args();
        if ( ! count( $args ) ) {
            // No arguments, return the log
            return $log;
        }

        if ( $label ) {
            $log .= "<h4>$label</h4>";
        }

        if ( is_string( $arg ) ) {
            $log .= htmlspecialchars( $arg ) . '<hr>';
        }
        else {
            $out = '<pre>';
            ob_start();
            print_r( $arg );
            $out .= ob_get_clean();
            $out .= '</pre>';
            $log .= $out . '<hr>';
        }
    }

    static public function logError ( $msg )
    {
        self::$process->errors[] = $msg;
        self::log( $msg );
    }

    static public function runStat ( $name )
    {
        $process = CssCrush::$process;
        $trace = $process->options->trace;

        if ( ! $trace || ! in_array( $name, $trace ) ) {
            return;
        }

        switch ( $name ) {

            case 'selector_count':
                $process->stat[ 'selector_count' ] = 0;
                foreach ( $process->tokens->r as $rule ) {
                    $process->stat[ 'selector_count' ] += count( $rule->selectors );
                }
                break;

            case 'rule_count':
                $process->stat[ 'rule_count' ] = count( $process->tokens->r );
                break;

            case 'compile_time':
                $time = microtime( true );
                $process->stat[ 'compile_time' ] = $time - $process->stat[ 'compile_start_time' ];
                break;
        }
    }
}

CssCrush::init();
