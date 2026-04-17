import js from '@eslint/js'
import pluginVue from 'eslint-plugin-vue'
import tseslint from 'typescript-eslint'
import prettier from 'eslint-config-prettier'

export default tseslint.config(
  js.configs.recommended,
  ...tseslint.configs.recommended,
  ...pluginVue.configs['flat/recommended'],
  prettier,
  {
    files: ['**/*.vue'],
    languageOptions: {
      parserOptions: {
        parser: tseslint.parser,
      },
    },
    rules: {
      'vue/multi-word-component-names': 'off',
      'vue/component-api-style': ['error', ['script-setup']],
      '@typescript-eslint/no-explicit-any': 'warn',
      'no-undef': 'off',
    },
  },
  {
    ignores: ['dist/', 'node_modules/', '*.config.*'],
  },
)
