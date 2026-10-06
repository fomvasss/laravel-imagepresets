import { fileURLToPath } from 'node:url';
import { defineConfig, passthroughImageService } from 'astro/config';
import starlight from '@astrojs/starlight';
import starlightGitHubAlerts from 'starlight-github-alerts';
import starlightLinksValidator from 'starlight-links-validator';
import { remarkPlainMarkdown } from './src/remark-plain-markdown.mjs';

const base = '/laravel-imagepresets';

export default defineConfig({
	site: 'https://fomvasss.github.io',
	base,
	image: { service: passthroughImageService() },
	markdown: {
		remarkPlugins: [[remarkPlainMarkdown, { root: fileURLToPath(new URL('.', import.meta.url)), base }]],
	},
	integrations: [
		starlight({
			title: 'Laravel Image Presets',
			description: 'On-the-fly image resizing, cropping and format conversion for Laravel via League Glide',
			social: [{ icon: 'github', label: 'GitHub', href: 'https://github.com/fomvasss/laravel-imagepresets' }],
			// the pages live in docs/ itself, not in src/content/docs/
			markdown: { processedDirs: ['.'] },
			expressiveCode: { shiki: { langAlias: { env: 'dotenv' } } },
			editLink: { baseUrl: 'https://github.com/fomvasss/laravel-imagepresets/edit/master/docs/' },
			plugins: [starlightGitHubAlerts(), starlightLinksValidator()],
			sidebar: [
				{ label: 'Getting started', items: [{ label: 'Overview', slug: 'index' }, 'installation', 'configuration'] },
				{
					label: 'Usage',
					items: [
						'usage/generating-urls',
						'usage/transformations',
						'usage/presets',
						'usage/allowlists',
						'usage/sources',
						'usage/storage',
						'usage/drivers-and-formats',
						'usage/svg',
						'usage/http-caching',
						'usage/signed-urls',
						'usage/trusted-bypass',
						'usage/cache-maintenance',
						'usage/security',
					],
				},
				{
					label: 'Reference',
					items: ['reference/api', 'reference/query-parameters', 'reference/responses', 'reference/commands'],
				},
				'upgrading',
			],
		}),
	],
});
