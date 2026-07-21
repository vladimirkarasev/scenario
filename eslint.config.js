import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import globals from 'globals'

export default tseslint.config(
    { ignores: ['public/**', 'vendor/**', 'node_modules/**', 'eslint.config.js', 'knip.config.ts', 'vite.config.ts', 'resources/js/ziggy.js', 'resources/js/vue-augment.d.ts'] },

    js.configs.recommended,
    ...tseslint.configs.recommended,
    ...pluginVue.configs['flat/recommended'],

    {
        files: ['resources/js/**/*.{ts,vue}'],
        languageOptions: {
            globals: {
                ...globals.browser,
                ...globals.es2022,
                route: 'readonly',
            },
            parserOptions: {
                parser: tseslint.parser,
                extraFileExtensions: ['.vue'],
                sourceType: 'module',
            },
        },
        rules: {
            // TypeScript
            '@typescript-eslint/no-explicit-any': 'error',
            '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_', varsIgnorePattern: '^_' }],
            '@typescript-eslint/consistent-type-imports': ['error', { prefer: 'type-imports' }],

            // Vue — logic rules
            'vue/component-api-style': ['error', ['script-setup']],
            'vue/define-macros-order': ['error', { order: ['defineProps', 'defineEmits'] }],
            'vue/no-unused-vars': 'error',
            'vue/prefer-true-attribute-shorthand': 'error',
            'vue/no-v-html': 'warn',
            'vue/multi-word-component-names': 'off',

            // Vue — formatting off (handled by editor/prettier)
            'vue/html-indent': 'off',
            'vue/max-attributes-per-line': 'off',
            'vue/singleline-html-element-content-newline': 'off',
            'vue/html-closing-bracket-newline': 'off',
            'vue/html-self-closing': 'off',
            'vue/first-attribute-linebreak': 'off',

            // General
            'no-console': ['warn', { allow: ['warn', 'error'] }],
            'prefer-const': 'error',
            'no-empty': ['error', { allowEmptyCatch: true }],
        },
    },
)
