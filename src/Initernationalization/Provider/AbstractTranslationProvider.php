<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Initernationalization\Provider;

use RuntimeException;

abstract class AbstractTranslationProvider {
	
	public function getLocales(): array {
		$path = pathOf(LANG_FOLDER);
		$locales = [];
		foreach( scanFolder($path) as $file ) {
			if( is_dir($path . '/' . $file) ) {
				$locales[] = $file;
			}
		}
		return $locales;
	}
	
	public function getLocaleDomains(string $locale): array {
		$path = $this->getLocalePath($locale);
		$domains = [];
		foreach( scanFolder($path) as $fileName ) {
			$domain = pathinfo($fileName, PATHINFO_FILENAME);
			if( $fileName === $this->resolveDomainFile($domain) ) {
				$domains[] = $domain;
			}
		}
		
		return $domains;
	}
	
	protected function getLocalePath(string $locale): string {
		$relativePath = LANG_FOLDER . '/' . $locale;
		$path = pathOf($relativePath);
		if( !$path ) {
			throw new RuntimeException(sprintf('Locale folder "%s" not found', $relativePath));
		}
		
		return $path;
	}
	
	public abstract function resolveDomainFile(string $domain): string;
	
	public function getDomainTranslations(string $locale, string $domain): array {
		$path = $this->getLocalePath($locale) . '/' . $this->resolveDomainFile($domain);
		return file_exists($path) ? $this->parseFile($path) : [];
	}
	
	public abstract function parseFile(string $path): array;
	
	public abstract function isSupported(): bool;
	
}
