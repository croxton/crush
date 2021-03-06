# Crush

* Author: [Mark Croxton](http://hallmark-design.co.uk/)
* Author of CSS Crush: [Pete Boere](https://github.com/peteboere)

## Version 1.0.1

[CSS Crush](https://github.com/peteboere/css-crush) is an extensible PHP based CSS preprocessor that aims to alleviate many of the hacks and workarounds necessary in modern CSS development.

This plugin is a wrapper for CSS Crush that allows you to use it in ExpressionEngine templates.

## Requirements

* ExpressionEngine 2.x
* PHP 5.3 or later
* The PHP function `parse_ini_file` is sometimes disabled by web hosts in the php.ini file. CSS Crush requires this function to be enabled.


## Installation

1. Copy the crush folder to ./system/expressionengine/third_party/
2. Create a folder 'cache' somewhere in your public webroot and make it writable by PHP
3. Open your webroot index.php file, find the "CUSTOM CONFIG VALUES" section and add the following lines:

###
	
	$assign_to_config['crush_output_dir'] = '/path/to/cache'; //no trailing slash


(if you're using a custom config bootstrap file, add the config items there instead)


## Usage:

This plugin supports all the CSS Crush methods as tags, with options passed as parameters. Example:

	{exp:crush:tag 
		filename="/_assets/css/style.css"
		minify="y"
		vars="my_var1=#333|my_var2=20px"
		attributes="media=print|title=monkey"
	} 

##Tags

###{exp:crush:tag}
Process host CSS file and return an HTML link tag with populated href

###{exp:crush:file}
Process host CSS file and return a new compiled file

###{exp:crush:inline}
Process host CSS file and return CSS as text wrapped in html style tags

###{exp:crush:string}
Compile a raw string of CSS string and return it

###{exp:crush:stat}
Retrieve statistics from the most recent compiled file. 
Requires the trace option to be set. 
Current available stats: selector_count, rule_count, compile_time and errors. 
Pass no argument to retrieve all available stats.


##Parameters

###`filename=""`
Root relative or absolute path to the CSS file

###`attributes="attr1=val1|attr2=val2"`
An array of html sttributes.

###`minify="y|n"`
Enable or disable minification of the compiled CSS file.

###`formatter="block|single-line|padded"`
Set the formatting mode for un-minified output. 
Only applies when minify option is set to false.

###`newlines="use-platform|windows/win|unix"`
Set the output style of newlines.

###`boilerplate="y|n|[filepath]"`
Prepend a boilerplate to the output file.

###`versioning="y|n"`
Append a timestamped querystring to output filename.

###`vars="my_var1=#333|my_var2=20px"`
An associative array of variables to pass to the CSS file at runtime.

###`cache="y|n"`
Turn caching on or off.

###`output_dir=""`
Specify an output directory for compiled files. 
Defaults to the same directory as the host file.
Can also be overriden by adding a config item 'crush_output_dir'

###`vendor_target="all|moz|webkit|..."`
Filter aliases to a specific vendor prefix.

###`output_file=""`
Specify an output filename (suffix is added).

###`rewrite_import_urls="y|n|absolute"`
Rewrite relative urls (and data-uris) inside inlined imported files.

###`enable="plugin_1|plugin_2"`
An array of plugin names to enable.

###`disable="plugin_1|plugin_2"`
An array of plugin names to disable.

###`trace="y|n|option1=val1|option2=val2"`
Output SASS debug-info stubs

###`stat_name="selector_count|rule_count|compile_time|errors"`
Retrieve statistics from the most recent compiled file

###`context=""`
Context for importing resources from relative urls

###`doc_root=""`
Specify an alternative server document root for situations where 
the CSS is being served behind an alias or url rewritten path.

###`dir=""`
Specify a directory when clearing the cache
