# QA Config File Templates

Copy these verbatim when generating project config files. Adapt paths only if the
project structure differs from `src/` (PHP) or `src/` (JS/TS).

---

## phpstan.neon

```neon
includes:
    - vendor/phpstan/extension-installer/loader.php

parameters:
    level: 10
    paths:
        - src
    symfony:
        container_xml_path: var/cache/dev/App_KernelDevDebugContainer.xml
    doctrine:
        objectManagerLoader: tests/object-manager.php
    ignoreErrors: []
```

---

## phpcs.xml

```xml
<?xml version="1.0"?>
<ruleset name="Project">
    <description>PSR-12 coding standard</description>

    <arg name="basepath" value="."/>
    <arg name="extensions" value="php"/>
    <arg name="parallel" value="8"/>
    <arg name="colors"/>
    <arg value="p"/>

    <file>src</file>
    <file>tests</file>

    <rule ref="PSR12"/>

    <!-- Allow long lines in config files -->
    <rule ref="Generic.Files.LineLength">
        <properties>
            <property name="lineLimit" value="120"/>
            <property name="absoluteLineLimit" value="0"/>
        </properties>
    </rule>
</ruleset>
```

Run order: always `phpcbf` (auto-fix) before `phpcs` (check).

---

## deptrac.yaml

Use `deptrac/deptrac` (not `qossmic/deptrac-shim`).

The collector regexes **must** exclude `src/Shared/` from the context-specific layers.
Without the negative lookahead, classes under `src/Shared/Domain/` match both `Domain`
and `Shared`, causing false "in more than one layer" violations for every cross-context
dependency on a shared value object or exception.

```yaml
deptrac:
    paths:
        - src

    layers:
        - name: Domain
          collectors:
              - type: directory
                value: src/(?!Shared)[^/]+/Domain/.*

        - name: Application
          collectors:
              - type: directory
                value: src/(?!Shared)[^/]+/Application/.*

        - name: Infrastructure
          collectors:
              - type: directory
                value: src/(?!Shared)[^/]+/Infrastructure/.*

        - name: Shared
          collectors:
              - type: directory
                value: src/Shared/.*

    ruleset:
        Domain:
            - Shared
        Application:
            - Domain
            - Shared
        Infrastructure:
            - Application
            - Domain
            - Shared
        Shared: []

    formatters:
        graphviz:
            pointToGroups: true
```

---

## phpunit.xml.dist

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit
    xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
    xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
    bootstrap="vendor/autoload.php"
    colors="true"
    failOnRisky="true"
    failOnWarning="true"
>
    <testsuites>
        <testsuite name="Unit">
            <directory>tests/Unit</directory>
        </testsuite>
        <testsuite name="Integration">
            <directory>tests/Integration</directory>
        </testsuite>
        <testsuite name="Functional">
            <directory>tests/Functional</directory>
        </testsuite>
    </testsuites>

    <source>
        <include>
            <directory>src</directory>
        </include>
    </source>

    <extensions>
        <bootstrap class="DAMA\DoctrineTestBundle\PHPUnit\PHPUnitExtension"/>
    </extensions>
</phpunit>
```

---

## eslint.config.js

```js
import eslint from '@eslint/js'
import tseslint from 'typescript-eslint'
import vuePlugin from 'eslint-plugin-vue'
import prettierConfig from 'eslint-config-prettier'

export default [
  eslint.configs.recommended,
  ...tseslint.configs.recommended,
  ...vuePlugin.configs['flat/recommended'],
  prettierConfig,
  {
    files: ['**/*.{ts,tsx,vue}'],
    rules: {
      // TypeScript
      '@typescript-eslint/no-explicit-any': 'error',
      '@typescript-eslint/explicit-function-return-type': 'warn',
      '@typescript-eslint/no-unused-vars': ['error', { argsIgnorePattern: '^_' }],

      // Vue
      'vue/component-name-in-template-casing': ['error', 'PascalCase'],
      'vue/multi-word-component-names': 'error',
      'vue/no-v-html': 'error',
      'vue/require-default-prop': 'off',
      'vue/block-lang': ['error', { script: { lang: 'ts' } }],

      // General
      'no-console': ['warn', { allow: ['warn', 'error'] }],
    },
  },
  {
    ignores: ['dist/**', 'node_modules/**', '*.config.js'],
  },
]
```

---

## .prettierrc

```json
{
  "semi": false,
  "singleQuote": true,
  "printWidth": 120,
  "tabWidth": 2,
  "trailingComma": "all",
  "vueIndentScriptAndStyle": true,
  "endOfLine": "lf"
}
```

---

## vitest.config.ts

```ts
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import { fileURLToPath } from 'node:url'

export default defineConfig({
  plugins: [vue()],
  test: {
    globals: true,
    environment: 'jsdom',
    setupFiles: ['./tests/setup.ts'],
    coverage: {
      provider: 'v8',
      reporter: ['text', 'lcov'],
      include: ['src/**'],
      exclude: ['src/**/*.stories.*', 'src/types/**'],
    },
  },
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
```

**tests/setup.ts** (create alongside vitest.config.ts):
```ts
import { config } from '@vue/test-utils'

// Global test utilities, mocks, etc.
// config.global.plugins = [...]
```

---

## tsconfig.json (if missing)

```json
{
  "compilerOptions": {
    "target": "ES2022",
    "module": "ESNext",
    "moduleResolution": "bundler",
    "strict": true,
    "noUnusedLocals": true,
    "noUnusedParameters": true,
    "noImplicitReturns": true,
    "jsx": "preserve",
    "lib": ["ES2022", "DOM", "DOM.Iterable"],
    "baseUrl": ".",
    "paths": { "@/*": ["./src/*"] },
    "skipLibCheck": true
  },
  "include": ["src/**/*", "tests/**/*"],
  "exclude": ["node_modules", "dist"]
}
```
