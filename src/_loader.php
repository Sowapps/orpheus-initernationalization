<?php
/**
 * Initernationalization library
 * Translation plugin using ini files
 * Require declaration of constants: LANG_FOLDER, DEFAULT_LOCALE.
 *
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

use Orpheus\Initernationalization\TranslationService;

/**
 * Translate a key
 *
 * @param string $key The Key to translate, prefer to use an internal language (English CamelCase).
 * @param string|array|null $domain The domain to apply the Key. Default value is 'global'.
 * @param array $parameters The values array to replace in text. Could be used as second parameter.
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
function t(string $key, string|array|null $domain = 'global', array $parameters = []): string {
	return TranslationService::getActive()->translate($key, $domain, $parameters);
}

/**
 * Translate a key allowing null value (if not found)
 *
 */
function tn(string $key, string|array|null $domain = 'global', array $parameters = []): ?string {
	return TranslationService::getActive()->translate($key, $domain, $parameters, true);
}

/**
 * Display t()
 *
 * @param string $key The Key to translate, prefer to use an internal language (English CamelCase).
 * @param string|array $domain The domain to apply the Key. Default value is 'global'.
 * @see t()
 * @deprecated Use echo t()
 */
function _t(string $key, string|array $domain = 'global', array $parameters = []): void {
	echo t($key, $domain, $parameters);
}

/**
 * Convert a localized string number into a programming one
 *
 */
function parseNumber(string $value): float {
	return TranslationService::getActive()->parseNumber($value);
}

/**
 * @deprecated Use parseNumber()
 */
function sanitizeNumber(string $value): float {
	return parseNumber($value);
}

/**
 * Format money by currency and using a number formatter
 *
 * @param bool|int $decimals Decimals or true for 2, false for 0
 */
function formatCurrency(float $value, string $currency, bool|int $decimals = true): string {
	return TranslationService::getActive()->formatCurrency($value, $decimals, $currency);

}

/**
 * Format a number using locale
 *
 */
function formatNumber(int|float $value, int $decimals = 0): string {
	return TranslationService::getActive()->formatNumber($value, $decimals);
}

