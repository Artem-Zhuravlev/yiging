import js from '@eslint/js'
import vue from 'eslint-plugin-vue'
import vueTsEslintConfig from '@vue/eslint-config-typescript'
import eslintConfigPrettier from 'eslint-config-prettier'

export default [
  { ignores: ['dist/**', 'node_modules/**', 'coverage/**'] },
  js.configs.recommended,
  ...vue.configs['flat/recommended'],
  ...vueTsEslintConfig(),
  eslintConfigPrettier,
  {
    rules: {
      'vue/multi-word-component-names': 'off',
    },
  },
]
