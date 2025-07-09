/**
 * @type {import('lint-staged', { with: { 'resolution-mode': 'import' } }).Configuration}
 */
const config = {
	'*.{js,ts,mjs}': [ 'npm run lint:js', () => 'npx tsc' ],
	'*.css': [ 'npm run lint:css' ],
	'composer.{json,lock}': [
		() => 'composer validate --strict',
		() => 'composer normalize --dry-run',
	],
	'*.php': [ 'composer phpcs', () => 'composer phpstan' ],
	'*.md': [ 'npm run lint:md' ],
	'README.md': [ 'npm run transform-readme' ],
};

module.exports = config;
