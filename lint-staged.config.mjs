import { ESLint } from 'eslint';

// ESLint warns "File ignored" on ignored paths, which trips `--max-warnings=0`.
const keepLintable = async ( files ) => {
	const eslint = new ESLint();
	const results = await Promise.all(
		files.map( async ( file ) =>
			( await eslint.isPathIgnored( file ) ) ? null : file
		)
	);
	return results.filter( Boolean );
};

export default {
	'*.{ts,tsx}': async ( files ) => {
		const commands = [ `prettier --write ${ files.join( ' ' ) }` ];
		const lintable = await keepLintable( files );
		if ( lintable.length > 0 ) {
			commands.push(
				`eslint --fix --max-warnings=0 ${ lintable.join( ' ' ) }`
			);
		}
		return commands;
	},
	'*.{css,scss}': [ 'prettier --write', 'stylelint --fix' ],
	'*.{json,md,yml,yaml}': [ 'prettier --write' ],
	'*.php': [
		'vendor/bin/php-cs-fixer fix --config=.php-cs-fixer.dist.php',
		'vendor/bin/phpcs',
	],
};
