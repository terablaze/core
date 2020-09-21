<?php
/**
 * Created by TeraBoxX.
 * User: tommy
 * Date: 2/23/2017
 * Time: 1:15 PM
 */

namespace {
	function __($lang_key, $default = "")
	{
		$lang = TeraBlaze\Libraries\Language::$language_strings;

		if (@is_string($lang["{$lang_key}"])) {
			return $lang["{$lang_key}"];
		}
		if (!empty($default)) {
			return $default;
		}
        return $lang_key;
	}
}

namespace TeraBlaze\Libraries {
	
	use TeraBlaze\Base as Base;
	use TeraBlaze\Configuration\Exception as Exception;
	use TeraBlaze\Events\Events as Events;
	
	class Language extends Base
	{
		public static $language_strings = array();
		/**
		 * @readwrite
		 */
		protected $language;
		
		public function set($language, $default = "")
		{
			Events::fire("terablaze.libraries.language.set.before", array($language));
			
			if (is_string($language)) {
				$this->language = $language;
			} else {
				$this->language = $default;
			}
			
			Events::fire("terablaze.libraries.language.set.after", array($language));
		}
		
		public function load($lang_file)
		{
			Events::fire("terablaze.libraries.language.load.before", array($lang_file));
			
			$lang_file = APP_DIR . 'language/' . $this->language . '/' . $lang_file;
			$ext = pathinfo($lang_file, PATHINFO_EXTENSION);
			$lang_file = ($ext === '') ? $lang_file . '.php' : $lang_file;
			
			@include_once $lang_file;
			
			if (!empty($lang)) {
				Language::$language_strings = array_merge(Language::$language_strings, $lang);
			}
			
			
			Events::fire("terablaze.libraries.language.load.after", array($lang_file));
		}
		
		protected function _getExceptionForImplementation($method)
		{
			return new Exception\Implementation("{$method} method not implemented");
		}
		
	}
}