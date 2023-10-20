<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Initernationalization;

use NumberFormatter;
use Orpheus\Cache\FileSystemCache;
use Orpheus\Initernationalization\Provider\AbstractTranslationProvider;
use Orpheus\Initernationalization\Provider\IniTranslationProvider;
use Orpheus\Initernationalization\Provider\YamlTranslationProvider;
use Orpheus\Service\ApplicationKernel;
use RuntimeException;

class TranslationService {
	
	/*
	 * Instance for default language
	 */
	protected static ?TranslationService $activeInstance = null;
	
	protected static ?array $providers = null;
	
	protected ?NumberFormatter $currencyFormatter = null;
	
	protected string $locale; // [domain][key]
	
	protected array $translations = [];
	
	public function __construct(string $locale) {
		$this->locale = $locale;
		// TODO Reload translations from cache
		// TODO Save translations to cache when building domain
	}
	
	public static function getInstance(string $locale): TranslationService {
		if( static::getActiveLocale() === $locale ) {
			return static::getActive();
		}
		
		return new static($locale);
	}
	
	public static function getActiveLocale(): string {
		return static::getActive()->getLocale();
	}
	
	public static function getActive(): TranslationService {
		if( !static::$activeInstance ) {
			static::$activeInstance = new static(static::getDefaultLocale());
			static::$activeInstance->setup();
		}
		
		return static::$activeInstance;
	}
	
	public static function getDefaultLocale(): string {
		return defined('DEFAULT_LOCALE') ? DEFAULT_LOCALE : 'en_US';
	}
	
	/**
	 * Format associated locales, "en_US" will return ["en_US"=>"en_US", "en"=>"en_US"]
	 *
	 * @return string[]
	 */
	public static function formatAssociatedLocales(array $locales): array {
		$associatedLocales = [];
		foreach( $locales as $locale ) {
			$associatedLocales[$locale] = $locale;
			if( strlen($locale) >= 5 ) {
				[$main,] = explode('_', $locale, 2);
				if( !isset($associatedLocales[$main]) ) {
					$associatedLocales[$main] = $locale;
				}
			}
		}
		
		return $associatedLocales;
	}
	
	/**
	 * @return string[]
	 */
	public static function guessAvailableLocales(): array {
		$locales = [];
		foreach( static::getSupportedProviders() as $provider ) {
			$locales = array_merge($locales, $provider->getLocales());
		}
		
		return array_unique($locales);
	}
	
	public function setActive(): void {
		static::$activeInstance = $this;
		$this->setup();
	}
	
	public function setup(): void {
		setlocale(LC_ALL, $this->getTranslation('locale', 'global') ?? $this->getLocale());
	}
	
	public function getTranslation(string $key, string $domain, bool $resolveLinks = false): ?string {
		$domainTranslations = $this->getDomainTranslations($domain);
		$translation = $domainTranslations[$key] ?? null;
		// Translation reference starts with '%'
		while( $resolveLinks && $translation && $translation[0] === '%' ) {
			$key = substr($translation, 1);
			$translation = $this->getTranslation($key, $domain, true);
		}
		return $translation;
	}
	
	public function getDomainTranslations(string $domain): array {
		if( !$this->isDomainBuilt($domain) ) {
			$this->buildDomain($domain);
		}
		
		return $this->translations[$domain] ?? [];
	}
	
	public function isDomainBuilt(string $domain): bool {
		return isset($this->translations[$domain]);
	}
	
	public function buildDomain(string $domain, bool $force = false): void {
		$force = $force || !ApplicationKernel::get()->isKernelCachingEnabled();
		$cache = new FileSystemCache('translations', $this->locale . '-' . $domain);
		if( $force || !$cache->get($translations) ) {
			$domainTranslations = [];
			foreach( static::getSupportedProviders() as $provider ) {
				$domainTranslations += $provider->getDomainTranslations($this->locale, $domain);
			}
			// Format translation keys
			$translations = $this->formatTranslations($domainTranslations);
			$cache->set($translations);
		}
		$this->translations[$domain] = $translations;
	}
	
	/**
	 * @return AbstractTranslationProvider[]
	 */
	public static function getSupportedProviders(): array {
		return array_filter(self::getProviders(), function (AbstractTranslationProvider $provider) {
			return $provider->isSupported();
		});
	}
	
	/**
	 * @return AbstractTranslationProvider[]
	 */
	public static function getProviders(): array {
		if( static::$providers === null ) {
			// Load basic providers
			static::initializeProviders();
		}
		
		return static::$providers;
	}
	
	public static function initializeProviders(): void {
		static::$providers = [
			new YamlTranslationProvider(),
			new IniTranslationProvider(),
		];
	}
	
	protected function formatTranslations(array $translations, string $path = ''): array {
		// Flatten translation keys
		$formattedTranslations = [];
		foreach( $translations as $key => $value ) {
			$keyPath = $path . $key;
			if( is_array($value) ) {
				$formattedTranslations = array_merge($formattedTranslations, $this->formatTranslations($value, $keyPath . '.'));
			} else {
				$formattedTranslations[$keyPath] = $value;
			}
		}
		
		return $formattedTranslations;
	}
	
	public function getLocale(): string {
		return $this->locale;
	}
	
	public function translate(string $key, string|array|null $domain = 'global', array $parameters = [], bool $nullable = false): ?string {
		if( is_array($domain) ) {
			$parameters = $domain;
			$domain = 'global';
		} else if( $domain === null ) {
			$domain = 'global';
		}
		$usingPrintParameters = isset($parameters[0]) && !is_array($parameters[0]);
		$translation = $this->getTranslation($key, $domain, !$usingPrintParameters);
		
		if( !$translation ) {
			if( $nullable ) {
				return null;
			}
			$translation = $key;
		}
		
		if( $parameters ) {
			if( isset($parameters[0]) ) {
				if( $usingPrintParameters ) {
					// List : ['value1', 'value2']
					return vsprintf($translation, $parameters);
				}
				// Replacement as array of array : [['key1'], ['value1']]
				$parameterKeys = $parameters[0];
				$parameterValues = !empty($parameters[1]) ? $parameters[1] : '';
			} else {
				// Array (dictionary) : ['key1' => 'value1']
				$parameterValues = $parameterKeys = [];
				foreach( $parameters as $name => $value ) {
					// Ignore non string values
					if( !is_string_convertible($value) ) {
						continue;
					}
					$parameterKeys[] = "#{$name}#";
					$parameterValues[] = "$value";
				}
			}
			$translation = str_replace($parameterKeys, $parameterValues, $translation);
		}
		
		return $translation;
	}
	
	public function formatCurrency(float $value, string $currency, bool|int $decimals = true): string {
		if( $decimals === true ) {
			$decimals = 2;
		} else if( $decimals === false ) {
			$decimals = 0;
		}
		$formatter = $this->getCurrencyFormatter();
		$formatter->setAttribute(NumberFormatter::MIN_FRACTION_DIGITS, $decimals);
		
		return $formatter->formatCurrency($value, $currency);
		//		return sprintf($this->getTranslation(''), $this->formatNumber($value, $decimals), $currency);
	}
	
	public function getCurrencyFormatter(): ?NumberFormatter {
		self::requireIntl();
		if( !$this->currencyFormatter ) {
			$this->currencyFormatter = $this->getFormatter(NumberFormatter::CURRENCY);
		}
		
		return $this->currencyFormatter;
	}
	
	public static function requireIntl(): void {
		if( !self::supportsIntl() ) {
			throw new RuntimeException('PHP extension "intl" is required');
		}
	}
	
	public static function supportsIntl(): bool {
		return class_exists(NumberFormatter::class);
	}
	
	/**
	 * @see https://www.php.net/manual/en/class.numberformatter.php#intl.numberformatter-constants
	 */
	public function getFormatter(int $style, ?string $pattern = null): NumberFormatter {
		self::requireIntl();
		
		return new NumberFormatter($this->locale, $style, $pattern);
	}
	
	public function formatNumber(int|float $value, int $decimals = 0): string {
		return number_format($value, $decimals, $this->info('decimal_point'), $this->info('thousands_sep'));
	}
	
	public function info(string $key): string {
		// Could be overridden by translation
		$value = $this->getTranslation($key, 'global');
		if( $value ) {
			return $value;
		}
		// Look into localeconv()'s array
		$infos = localeconv();
		if( !isset($infos[$key]) ) {
			throw new RuntimeException(sprintf('Invalid key "%s" for array from localeconv()', $key));
		}
		
		return $infos[$key];
	}
	
	public function parseNumber(string $value): float {
		return floatval(str_replace(
			[$this->info('decimal_point'), $this->info('thousands_sep')],
			['.', ''],
			$value
		));
	}
	
	/**
	 * @return string[]
	 */
	public function guessAvailableDomains(): array {
		$domains = [];
		foreach( static::getSupportedProviders() as $provider ) {
			$domains = array_merge($domains, $provider->getLocaleDomains($this->locale));
		}
		
		return array_unique($domains);
	}
	
	/**
	 * @return string Locale as per RFC 4646
	 */
	public function getHttpLocale(): string {
		return str_replace('_', '-', $this->locale);
	}
	
}
