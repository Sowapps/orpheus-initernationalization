<?php
/**
 * @author Florent HAZARD <f.hazard@sowapps.com>
 */

namespace Orpheus\Initernationalization\Controller;

use Orpheus\Initernationalization\TranslationService;
use Orpheus\InputController\CliController\CliController;
use Orpheus\InputController\CliController\CliRequest;
use Orpheus\InputController\CliController\CliResponse;

class TranslationBuildCliController extends CliController {
	
	/**
	 * @param CliRequest $request The input CLI request
	 */
	public function run($request): CliResponse {
		$help = $request->getOption('help', 'h');
		$locale = $request->getOption('locale');
		$domains = $request->getOption('domains');
		
		if( $help ) {
			return new CliResponse(0, $this->getHelp());
		}
		
		$translationService = TranslationService::getInstance($locale ?? TranslationService::getActiveLocale());
		
		if( $domains ) {
			$domains = explode(',', $domains);
		} else {
			// Guess automatically
			$domains = $translationService->guessAvailableDomains();
		}
		
		$this->printLine(sprintf('%sBuild translations for locale "%s" and domains : %s', $request->isDryRun() ? '[DRY-RUN] ' : '', $translationService->getLocale(), implode(', ', $domains)));
		
		foreach( $domains as $domain ) {
			if( !$request->isDryRun() ) {
				$translationService->buildDomain($domain, true);
			}
			if( $request->isVerbose() ) {
				$this->printLine(sprintf('Built domain "%s"', $domain));
			}
		}
		
		return new CliResponse(0, 'Domains were built.');
	}
	
	protected function getHelp(): string {
		return <<<END
php app/console/run.php translation-build --locale=fr_FR
Build translation for a locale. Domains may be specified.
Options:
 - --locale : Locale to build, else the default locale.
 - --domains : Domains to build separated by coma, else all domains are built.
 - -v|vv|vvv : Verbose mode, More v, more verbose.
 - --dry-run : Dry run, do not apply any change.
 - -h|--help : Show this help.
END
			;
	}
	
	
}
