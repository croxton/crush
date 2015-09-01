<?php if ( ! defined('BASEPATH')) exit('No direct script access allowed');

require_once PATH_THIRD . 'crush/libraries/css-crush/CssCrush.php';

$plugin_info = array(
  'pi_name' => 'Crush',
  'pi_version' =>'1.0.1',
  'pi_author' =>'Mark Croxton',
  'pi_author_url' => 'http://www.hallmark-design.co.uk/',
  'pi_description' => 'CSS Crush for ExpressionEngine',
  'pi_usage' => Crush::usage()
  );

class Crush {

	// --------------------------------------------------------------------
	// PROPERTIES
	// --------------------------------------------------------------------

	/**
	 * The CSS file.
	 *
	 * @var        string
	 * @access     public
	 */
	public $filename = '';

	/**
	 * An array of html sttributes.
	 *
	 * @var        array
	 * @access     public
	 */
	public $attributes = array();

	/**
	 * Enable or disable minification of the compiled CSS file.
	 *
	 * @var        boolean
	 * @access     public
	 */
	public $minify = TRUE;

	/**
	 * Set the formatting mode for un-minified output. 
	 * Only applies when minify option is set to false.
	 *
	 * @var        string 	block | single-line | padded
	 * @access     public  
	 */
	public $formatter = '';

	/**
	 * Set the output style of newlines.
	 *
	 * @var        string 	use-platform | windows/win | unix
	 * @access     public  
	 */
	public $newlines = '';

	/* Prepend a boilerplate to the output file.
	 *
	 * @var        mixed 	true | false | filepath
	 * @access     public  
	 */
	public $boilerplate = '';

	/* Append a timestamped querystring to output filename
	 *
	 * @var        boolean
	 * @access     public  
	 */
	public $versioning = TRUE;

	/**
	 * An associative array of variables to pass to the CSS file at runtime.
	 *
	 * @var        array	
	 * @access     public
	 */
	public $vars = array();

	/**
	 * Turn caching on or off.
	 *
	 * @var        boolean	
	 * @access     public
	 */
	public $cache = TRUE;

	/* Specify an output directory for compiled files. 
	 * Defaults to the same directory as the host file.
	 *
	 * @var        string
	 * @access     public  
	 */
	public $output_dir = '';

	/* Specify an output filename (suffix is added).
	 *
	 * @var        string
	 * @access     public  
	 */
	public $output_file = '';

	/* Filter aliases to a specific vendor prefix.
	 *
	 * @var        string 	"all" | "moz" | "webkit" | ...
	 * @access     public  
	 */
	public $vendor_target = '';

	/* Rewrite relative urls (and data-uris) inside inlined imported files.
	 *
	 * @var        mixed 	true | false | "absolute"
	 * @access     public  
	 */
	public $rewrite_import_urls = '';

	/* An array of plugin names to enable.
	 *
	 * @var        array
	 * @access     public  
	 */
	public $enable = array();

	/* An array of plugin names to disable.
	 *
	 * @var        array
	 * @access     public  
	 */
	public $disable = array();

	/* Output SASS debug-info stubs
	 *
	 * @var        boolean/array 	true | false
	 * @access     public  
	 */
	public $trace = FALSE;

	/* Retrieve statistics from the most recent compiled file
	 *
	 * @var        string 	selector_count | rule_count | compile_time | errors
	 * @access     public  
	 */
	public $stat_name = '';

	/* Context for importing resources from relative urls
	 *
	 * @var        string
	 * @access     public  
	 */
	public $context = '';

	/* Specify an alternative server document root for situations where 
	 * the CSS is being served behind an alias or url rewritten path.
	 *
	 * @var        string
	 * @access     public  
	 */
	public $doc_root = '';

	/* Specify a directory when clearing the cache
	 *
	 * @var        string
	 * @access     public  
	 */
	public $dir = '';


	/** 
	 * Constructor
	 *
	 * @access public
	 * @return void
	 */
	public function __construct() 
	{
		$this->EE =& get_instance();

		// set parameters
		$this->set_params();

		// output directory
		if ( $this->EE->config->item('crush_output_dir') && empty($this->output_dir))
		{
			$this->output_dir = $this->EE->config->item('crush_output_dir');
		}
	}

	// --------------------------------------------------------------------
	// EE TAGS
	// --------------------------------------------------------------------

	/** 
	 * Process host CSS file and return a new compiled file
	 *
	 * @access public
	 * @return string
	 */
	public function file()
	{
		return csscrush_file(
			$this->filename, 
			$this->get_params(FALSE, array('filename', 'attributes'))
		);
	}

	/** 
	 * Process host CSS file and return an HTML link tag with populated href
	 *
	 * @access public
	 * @return string
	 */
	public function tag()
	{
		/* Example usage:
		{exp:crush:tag 
			filename="/_assets/css/style.css"
			minify="y"
			vars="my_var1=#333|my_var2=20px"
			attributes="media=print|title=monkey"
		} 
		*/
		return csscrush_tag(
			$this->filename, 
			$this->get_params(FALSE, array('filename', 'attributes')),
			$this->attributes
		);

	}

	/** 
	 * Process host CSS file and return CSS as text wrapped in html style tags
	 *
	 * @access public
	 * @return string
	 */
	public function inline()
	{
		/* Example usage:
		{exp:crush:tag filename="/_assets/css/style.css"} 
		*/
		return csscrush_inline(
			$this->filename, 
			$this->get_params(FALSE, array('filename', 'attributes')),
			$this->attributes
		);
	}

	/** 
	 * Compile a raw string of CSS string and return it
	 *
	 * @access public
	 * @return string
	 */
	public function string()
	{
		return csscrush_string(
			$this->filename, 
			$this->get_params(FALSE, array('filename', 'attributes'))
		);
	}

	/** 
	 * Clear config file and compiled files for the specified directory
	 *
	 * @access public
	 * @return string
	 */
	public function stat()
	{
		return csscrush_stat(
			$this->get_params('stat_name')
		);
	}

	// --------------------------------------------------------------------
	// UTILITY
	// --------------------------------------------------------------------

	/** 
	 * Set publically accessible properties of this object to specified parameter values
	 *
	 * @access protected
	 * @param  mixed 	An indexed array of specific parameters to set, or FALSE
	 * @return void
	 */
	protected function set_params($limit = FALSE)
	{
		$public_vars = get_class_vars(__CLASS__);

		// restrict to specified keys
		if ($limit !== FALSE)
		{
			$public_vars = array_intersect_key($public_vars, array_flip($limit));
		}

		// prep parameters
		foreach ($this->EE->TMPL->tagparams as $key => $var)
		{
			if ( array_key_exists($key, $public_vars) )
			{
				// the final parameter value
				$value = '';

				// booleans
				if ( preg_match('/^[1|on|yes|y|0|off|no|n]/i', $var))
				{
					$value = (bool) preg_match('/1|on|yes|y/i', $var);
				}

				// arrays
				elseif ( preg_match('/\||=/', $var))
				{
					// indexed array
					$value = explode('|', $var);

					// remove whitepace from array values
					$value = array_filter(array_map('trim', $value));

					// associative arrays
					if ( preg_match('/=/', $var))
					{
						$param = array();

						foreach ($value as $v)
						{
							$nested = explode('=', $v);
							if (isset($nested[1]))
							{
								$param[trim($nested[0])] = trim($nested[1]);
							}
						}
						$value = $param;
					}
				}

				// integers
				elseif( preg_match('/^\d+/', $var))
				{
					$value = (int) $var;
				}

				// strings
				else
				{
					$value = (string) $var;
				}

				// Check that the types match and assign.
				// Commented out because some properties have mixed types.
				/*
				if ( gettype($this->$key) === gettype($value) )
				{
					$this->$key = $value;
				}
				*/
				$this->$key = $value;
			}
		}

	}

	/** 
	 * Get publically accessible properties
	 *
	 * @access protected
	 * @param  mixed 	An indexed array/string of specific parameters to get, or FALSE
	 * @param  mixed 	An indexed array/string of specific parameters to remove, or FALSE
	 * @return void
	 */
	protected function get_params($limit = FALSE, $remove = FALSE) 
	{
		// untangle the God object from this object instance, 
		// to prevent recursion when we get object vars
		$EE2 = $this->EE;
		unset($this->EE);

		$public_vars = get_object_vars($this);

		// restore EE to Godhood
		$this->EE = $EE2;
		unset($EE2);

		// limit to specified keys
		if ( $limit !== FALSE )
		{
			if ( is_string($limit))
			{
				$limit = array($limit);
			}
			if ( is_array($limit))
			{
				$public_vars = array_intersect_key($public_vars, array_flip($limit));
			}
		}

		// remove specified keys
		if ( $remove !== FALSE )
		{
			if ( is_string($remove))
			{
				$remove = array($remove);
			}
			if ( is_array($remove))
			{
				foreach( $remove as $key)
				{
					if (isset($public_vars[$key]))
					{
						unset($public_vars[$key]);
					}
				}
			}
		}

		// remove empty string/array values
		foreach ($public_vars as $key => $var)
		{
			if ( is_string($var) || is_array($var))
			{
				if (empty($var))
				{
					unset($public_vars[$key]);
				}
			}
		}
		return $public_vars;
    }

    // usage instructions
	public static function usage() 
	{
  		ob_start();
?>

CSS Crush is an extensible PHP based CSS preprocessor that aims to alleviate many of the hacks and workarounds necessary in modern CSS development.

----------------------------------------------------------------------------
Documentation:

https://github.com/peteboere/css-crush

----------------------------------------------------------------------------
API:

https://github.com/peteboere/css-crush/wiki/PHP-API#options


----------------------------------------------------------------------------
Usage:

This plugin supports the main CSS Crush API functions as tags. Example:

{exp:crush:tag 
	filename="/_assets/css/style.css"
	minify="y"
	vars="my_var1=#333|my_var2=20px"
	attributes="media=print|title=monkey"
} 

Tags:
----------------------------------------------------------------------------

{exp:crush:tag}
Process host CSS file and return a new compiled file

{exp:crush:file}
Process host CSS file and return an HTML link tag with populated href

{exp:crush:inline}
Process host CSS file and return CSS as text wrapped in html style tags

{exp:crush:string}
Compile a raw string of CSS string and return it

{exp:crush:stat}
Retrieve statistics from the most recent compiled file. 
Requires the trace option to be set. 
Current available stats: selector_count, rule_count, compile_time and errors. 
Pass no argument to retrieve all available stats.

----------------------------------------------------------------------------
Parameters:

filename=""
Root relative or absolute path to the CSS file

attributes="attr1=val1|attr2=val2"
An array of html sttributes.

minify="y|n"
Enable or disable minification of the compiled CSS file.

formatter="block|single-line|padded"
Set the formatting mode for un-minified output. 
Only applies when minify option is set to false.

newlines="use-platform | windows/win | unix"
Set the output style of newlines.

boilerplate="y|n|[filepath]"
Prepend a boilerplate to the output file.
use-platform | windows/win | unix

versioning="y|n"
Append a timestamped querystring to output filename.

vars="my_var1=#333|my_var2=20px"
An associative array of variables to pass to the CSS file at runtime.

cache="y|n"
Turn caching on or off.

output_dir=""
Specify an output directory for compiled files. 
Defaults to the same directory as the host file.
Can also be overriden by adding a config item 'crush_output_dir'

vendor_target="all|moz|webkit|..."
Filter aliases to a specific vendor prefix.

output_file=""
Specify an output filename (suffix is added).

rewrite_import_urls="y|n|absolute"
Rewrite relative urls (and data-uris) inside inlined imported files.

enable="plugin_1|plugin_2"
An array of plugin names to enable.

disable="plugin_1|plugin_2"
An array of plugin names to disable.

trace="y|n|option1=val1|option2=val2"
Output SASS debug-info stubs

stat_name="selector_count|rule_count|compile_time|errors"
Retrieve statistics from the most recent compiled file

context=""
Context for importing resources from relative urls

doc_root=""
Specify an alternative server document root for situations where 
the CSS is being served behind an alias or url rewritten path.

dir=""
Specify a directory when clearing the cache


	<?php
		$buffer = ob_get_contents();
		ob_end_clean();
		return $buffer;
	}	
}
