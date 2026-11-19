import { execFileSync } from 'node:child_process';
import { mkdirSync, writeFileSync } from 'node:fs';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../', import.meta.url));
const output = new URL('../graphql/generated/schema.graphql', import.meta.url);
const schema = execFileSync('php', ['artisan', 'lighthouse:print-schema', '--sort', '--no-ansi'], {
    cwd: root,
    encoding: 'utf8',
    stdio: ['ignore', 'pipe', 'inherit'],
});

if (!schema.trim()) {
    throw new Error('Lighthouse schema export returned no output.');
}

mkdirSync(new URL('.', output), { recursive: true });
writeFileSync(output, schema);
