/**
 * @type {import('lint-staged', { with: { 'resolution-mode': 'import' } }).Configuration}
 */
const config = {
	'*.{js,ts,mjs}': [ 'npx wp-scripts lint-js', () => 'npx tsc' ],
	'composer.{json,lock}': [
		() => 'composer validate --strict',
		() => 'composer normalize --dry-run',
	],
	'*.php': [ 'composer phpcs', () => 'composer phpstan' ],
	'*.md': [ 'npx wp-scripts lint-md-docs' ],
	'README.md': [ 'npm run transform-readme' ],
};

module.exports = config;
