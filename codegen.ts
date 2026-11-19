import type { CodegenConfig } from '@graphql-codegen/cli';

const config: CodegenConfig = {
    schema: 'graphql/generated/schema.graphql',
    documents: 'resources/js/graphql/operations/**/*.graphql',
    ignoreNoDocuments: false,
    generates: {
        'resources/js/graphql/generated/': {
            preset: 'client',
            presetConfig: {
                fragmentMasking: false,
            },
            config: {
                documentMode: 'string',
                useTypeImports: true,
                skipTypename: true,
                avoidOptionals: { field: true },
                disableDescriptions: true,
                strictScalars: true,
                scalars: {
                    DateTime: { input: 'string', output: 'string' },
                },
            },
        },
    },
};

export default config;
