<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Initernationalization\Provider;

class IniTranslationProvider extends AbstractTranslationProvider {
	
	public function resolveDomainFile(string $domain): string {
		return sprintf('%s.ini', $domain);
	}
	
	public function parseFile(string $path): array {
		return parse_ini_file($path);
	}
	public function isSupported(): bool {
		return function_exists('parse_ini_file');
	}
	
}
