<?php
/**
 * Initernationalization library
 * Translation plugin using ini files
 * Require declaration of constants: LANG_FOLDER, DEFAULT_LOCALE.
 *
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

if( !defined('ORPHEUS_PATH') ) {
	// Do not load in a non-orpheus environment
	return;
}

/**
 * Get a language ini file
 *
 * @param string $lang The lang to get the domain file
 * @param string $domain The domain of the file to load
 * @return array The translations
 *
 */
function getLangDomainFile($lang, $domain) {
	if( !empty($domain) && existsPathOf(LANG_FOLDER . '/' . $lang . '/' . $domain . '.ini') ) {
		return parse_ini_file(pathOf(LANG_FOLDER . '/' . $lang . '/' . $domain . '.ini'));
	} else {
		return null;
	}
}

/**
 * Load a language ini file
 *
 * @param string $domain The domain of the file to load.
 *
 * Load a language ini file from the file system.
 * You don't have to use this function explicitly.
 */
function loadLangFile($domain = 'global') {
	// 	global $LANG, $APP_LANG;
	/**
	 * $APP_LOCALE is the current locale, e.g en_US
	 * $APP_LANG is the current base language, e.g fr
	 * $LANG is the array containing all current locale translations
	 */
	global $LANG, $APP_LOCALE, $APP_LANG;
	if( $LANG && array_key_exists($domain, $LANG) ) {
		return;
	}
	if( !isset($APP_LOCALE) ) {
		// Set APP LANG to default with backward compatibility
		$APP_LOCALE = defined('DEFAULT_LOCALE') ? DEFAULT_LOCALE : (defined('LANG') ? LANG : 'en_US');
	}
	// Former LANGBASE constant
	if( !isset($APP_LANG) ) {
		if( defined('LANGBASE') ) {
			// backward compatibility
			$APP_LANG = LANGBASE;
		} else {
			[$APP_LANG] = explode('_', $APP_LOCALE, 2);
			// backward compatibility
			define('LANGBASE', $APP_LANG);
		}
	}
	$LANG[$domain] = getLangDomainFile($APP_LOCALE, $domain);
}

/**
 * Translate a key
 *
 * @param string $k The Key to translate, prefer to use an internal language (English CamelCase).
 * @param string $domain The domain to apply the Key. Default value is 'global'.
 * @param array|string $values The values array to replace in text. Could be used as second parameter.
 * @return string The translated human text.
 *
 * This function try to translate the given key, in case of failure, it just returns the Key.
 * It tries to replace $values in text by key using \#key\# format using str_replace() but if $values is a list of values, it uses sprintf().
 * $values allows 3 formats:
 *  - array('key1'=>'value1', 'key2'=>'value2'...)
 *  - array(array('key1', 'key2'...), array('value1', 'value2'...))
 *  - array('value1', 'value2'...)
 *  This function is variadic, you can specify values with more scalar arguments.
 *
 *  Examples: t('untranslatedString', 'aDomain'), t('My already translated string'), t('untranslatedString', 'global', array('key1'=>'val1')), t('untranslatedString', 'global', 'val1', 60)
 */
function t($k, $domain = 'global', $values = []) {
	global $LANG;
	if( is_array($domain) ) {
		$values = $domain;
		$domain = 'global';
	}
	$k = "$k";
	$r = hasTranslation($k, $domain) ? $LANG[$domain][$k] : $k;
	while( isset($r[0]) && $r[0] == '%' ) {
		$k = substr($r, 1);
		if( hasTranslation($k, $domain) ) {
			$r = $LANG[$domain][$k];
		} else {
			break;
		}
	}
	if( $values !== [] ) {
		if( !is_array($values) ) {
			$values = array_slice(func_get_args(), 2);
		}
		if( isset($values[0]) ) {
			if( !is_array($values[0]) ) {
				return vsprintf($r, $values);
			}
			$rkeys = $values[0];
			$rvalues = !empty($values[1]) ? $values[1] : '';
		} else {
			$rvalues = $rkeys = [];
			foreach( $values as $key => $value ) {
				// Ignore non string values
				if( !is_string_convertible($value) ) {
					continue;
				}
				$rkeys[] = "#{$key}#";
				$rvalues[] = "$value";
			}
		}
		$r = str_replace($rkeys, $rvalues, $r);
	}
	return $r;
}

/**
 * Display t()
 *
 * @param string $k The Key to translate, prefer to use an internal language (English CamelCase).
 * @param string $domain The domain to apply the Key. Default value is 'global'.
 * @param array|string $values The values array to replace in text. Could be used as second parameter.
 * @see t()
 */
function _t($k, $domain = 'global', $values = []) {
	echo t($k, $domain, is_array($values) ? $values : array_slice(func_get_args(), 2));
}

/**
 * Check if this key exists.
 *
 * @param string $k The Key to translate, prefer to use an internal language (English CamelCase).
 * @param string $domain The domain to apply the Key. Default value is 'global'.
 * @return boolean True if the translation exists in this domain.
 *
 * This function checks if the key is known in the translation list.
 */
function hasTranslation($k, $domain = 'global') {
	global $LANG;
	loadLangFile($domain);
	return isset($LANG[$domain]) && isset($LANG[$domain][$k]);
}

/**
 * Check if this key exists.
 *
 * @param string $k The Key to translate, prefer to use an internal language (English CamelCase).
 * @param string $default The default translation value to use.
 * @param string $domain The domain to apply the Key. Default value is 'global'.
 * @return string The translation
 *
 * This function translate the key without any fail.
 * If no translation is available, it uses the $default.
 */
function translate($k, $default, $domain = 'global') {
	return hasTranslation($k, $domain) ? t($k, $domain) : $default;
}

if( hasTranslation('locale') ) {
	setlocale(LC_ALL, t('locale'));
} elseif( defined('LOCALE') ) {
	setlocale(LC_ALL, LOCALE);
}

/**
 * Translate currency using t() or server config with localeconv()
 *
 * @param string $k
 * @return string
 * @see http://php.net/manual/fr/function.localeconv.php
 */
function tc($k) {
	if( hasTranslation($k) ) {
		return t($k);
	}
	global $LOCALECONV;
	if( !isset($LOCALECONV) ) {
		$LOCALECONV = localeconv();
	}
	return isset($LOCALECONV[$k]) ? $LOCALECONV[$k] : null;
}

/**
 * Convert a localized string number into a programming one
 *
 * @param string $value
 * @return float
 */
function sanitizeNumber($value) {
	return floatval(str_replace([tc('decimal_point'), tc('thousands_sep')], ['.', ''], $value));
}

/**
 * Format a money by currency and using a number formatter
 *
 * @param number $value
 * @param string $double
 * @param string $currency
 * @return string
 */
function formatMoney($value, $double = true, $currency = '€') {
	return ($double ? formatDouble($value) : formatInt($value)) . ' ' . $currency;
}

/**
 * Format a int using locale
 *
 * @param number $value
 * @return string
 */
function formatInt($value) {
	return number_format($value, 0, '', t('thousands_sep'));
}

/**
 * Format a double using locale
 *
 * @param number $value
 * @return string
 */
function formatDouble($value) {
	return number_format($value, 2, t('decimal_point'), t('thousands_sep'));
}

