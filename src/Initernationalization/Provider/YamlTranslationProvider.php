<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Initernationalization\Provider;

class YamlTranslationProvider extends AbstractTranslationProvider {
	
	public function isSupported(): bool {
		return function_exists('yaml_parse_file');
	}
	
	public function resolveDomainFile(string $domain): string {
		return sprintf('%s.yaml', $domain);
	}
	
	public function parseFile(string $path): array {
		return yaml_parse_file($path);
	}
	
}
