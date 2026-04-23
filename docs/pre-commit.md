# Pre-Commit Hook Configuration Guide

This guide documents the pre-commit hook setup used in this Laravel project. Follow these instructions to replicate this configuration in other projects.

## Overview

This project uses **Husky** and **lint-staged** to enforce code quality standards before commits. The setup automatically runs linters and formatters on staged files.

## What Runs on Pre-Commit

When you commit code, the following tools run automatically on **staged files only**:

1. **ESLint** - JavaScript/Vue linting
2. **Prettier** - Code formatting for JS/Vue/JSON/HTML
3. **Laravel Pint** - PHP code formatting
4. **Composer Audit** - Security vulnerability check (via `composer lint`)

## Required Dependencies

### NPM Dependencies (package.json)

```json
{
  "devDependencies": {
    "husky": "^9.1.6",
    "lint-staged": "^15.2.10",
    "eslint": "^9.12.0",
    "eslint-config-prettier": "^9.1.0",
    "eslint-plugin-vue": "^9.28.0",
    "prettier": "^3.3.3",
    "@eslint/js": "^9.12.0",
    "globals": "^15.11.0"
  }
}
```

### Composer Dependencies (composer.json)

```json
{
  "require-dev": {
    "laravel/pint": "^1.18",
    "larastan/larastan": "^2.9",
    "phpstan/phpstan": "^1.12"
  }
}
```

## Installation Steps

### 1. Install NPM Packages

```bash
npm install --save-dev husky@^9.1.6 lint-staged@^15.2.10 eslint@^9.12.0 eslint-config-prettier@^9.1.0 eslint-plugin-vue@^9.28.0 prettier@^3.3.3 @eslint/js@^9.12.0 globals@^15.11.0
```

### 2. Install Composer Packages

```bash
composer require --dev laravel/pint larastan/larastan phpstan/phpstan
```

### 3. Add NPM Scripts to package.json

Add these scripts to your `package.json`:

```json
{
  "scripts": {
    "eslint": "eslint resources/js/**",
    "eslint-fix": "eslint --fix resources/js/**",
    "lint": "prettier --check resources/js/**",
    "lint-fix": "prettier --write resources/js/**",
    "prepare": "node .husky/install.mjs"
  },
  "lint-staged": {
    "*.{js,vue,ts}": "eslint",
    "*.{json,js,ts,vue,html}": "prettier --write",
    "**/*.php": "composer lint"
  }
}
```

### 4. Add Composer Scripts to composer.json

Add these scripts to your `composer.json`:

```json
{
  "scripts": {
    "analyse": "vendor/bin/phpstan analyse --memory-limit=1G",
    "format": "vendor/bin/pint",
    "lint": [
      "@php vendor/bin/pint --ansi",
      "@php vendor/bin/phpstan analyse --verbose --ansi --memory-limit=1G",
      "@composer audit"
    ]
  }
}
```

### 5. Create Husky Configuration

Create `.husky/install.mjs`:

```javascript
// Skip Husky install in production and CI
if (process.env.NODE_ENV === 'production' || process.env.CI === 'true') {
    process.exit(0)
}
const husky = (await import('husky')).default
console.log(husky())
```

### 6. Create Pre-Commit Hook

Create `.husky/pre-commit`:

```bash
npx lint-staged
```

Make it executable:

```bash
chmod +x .husky/pre-commit
```

### 7. Initialize Husky

```bash
npm run prepare
```

## Configuration Files

### ESLint Configuration (eslint.config.js)

Create `eslint.config.js`:

```javascript
import eslintConfigPrettier from 'eslint-config-prettier';
import globals from 'globals';
import pluginJs from '@eslint/js';
import pluginVue from 'eslint-plugin-vue';

export default [
    { files: ['**/*.{js,mjs,cjs,vue}'] },
    { languageOptions: { globals: globals.browser } },
    { ignores: ['vendor/', 'public/'] },
    pluginJs.configs.recommended,
    eslintConfigPrettier,
    ...pluginVue.configs['flat/essential'],
    {
        rules: {
            // override/add rules settings here, such as:
            'vue/multi-word-component-names': 'off',
            'vue/require-default-prop': 'off',
            'no-undef': 'off', // Ziggy's global route()
            'no-unused-vars': ['error', { caughtErrors: 'none' }],
            'vue/require-v-for-key': 'off',
            'vue/valid-v-for': 'off',
        },
    },
];
```

### Prettier Configuration (.prettierrc)

Create `.prettierrc`:

```json
{
    "semi": true,
    "singleQuote": true,
    "printWidth": 100,
    "arrowParens": "always"
}
```

### Prettier Ignore (.prettierignore)

Create `.prettierignore`:

```
node_modules
resources/views
public
vendor
```

### Laravel Pint Configuration (pint.json)

Create `pint.json`:

```json
{
    "preset": "laravel",
    "rules": {
        "binary_operator_spaces": {
            "operators": {
                "=>": "align_single_space_minimal"
            }
        },
        "concat_space": {
            "spacing": "one"
        },
        "not_operator_with_successor_space": false
    },
    "exclude": [
        "simplesamlphp"
    ]
}
```

### PHPStan Configuration (phpstan.neon.dist)

Create `phpstan.neon.dist`:

```neon
includes:
    - vendor/larastan/larastan/extension.neon
    - phpstan-baseline.neon

parameters:

    paths:
        - app/

    excludePaths:
        - simplesamlphp/
        - tests/

    # Level 9 is the highest level
    level: 5

    ignoreErrors:
        - '#Access to an undefined property Illuminate\\Database\\Eloquent\\Model::\$[a-zA-Z_]+#'
```

## How It Works

### The Flow

1. Developer stages files with `git add`
2. Developer runs `git commit`
3. Husky intercepts the commit
4. `lint-staged` identifies staged files by extension
5. Appropriate linters/formatters run on those files:
   - `*.{js,vue,ts}` → ESLint
   - `*.{json,js,ts,vue,html}` → Prettier (with auto-fix)
   - `**/*.php` → `composer lint` (Pint + PHPStan + Composer Audit)
6. If all checks pass, commit proceeds
7. If any check fails, commit is blocked

### lint-staged Configuration Breakdown

```json
"lint-staged": {
  "*.{js,vue,ts}": "eslint",                    // Check JS/Vue/TS syntax
  "*.{json,js,ts,vue,html}": "prettier --write", // Format and auto-fix
  "**/*.php": "composer lint"                    // Run PHP linting
}
```

## Testing the Setup

### Test ESLint

```bash
npm run eslint
```

### Test Prettier

```bash
npm run lint
```

### Test PHP Linting

```bash
composer lint
```

### Test Pre-Commit Hook

1. Make a change to a JS/PHP file
2. Stage it: `git add .`
3. Try to commit: `git commit -m "test"`
4. Watch the pre-commit hooks run

## Manual Execution Commands

### Fix JavaScript Issues

```bash
npm run eslint-fix
```

### Fix Prettier Formatting

```bash
npm run lint-fix
```

### Fix PHP Formatting

```bash
composer format
# or
vendor/bin/pint
```

### Run PHPStan Analysis

```bash
composer analyse
# or
vendor/bin/phpstan analyse --memory-limit=1G
```

### Run All Linters Manually

```bash
composer lint
```

## Skipping Pre-Commit Hooks (Emergency Only)

```bash
git commit --no-verify -m "emergency fix"
```

**⚠️ Warning:** Only use this in emergencies. The hooks exist to maintain code quality.

## Troubleshooting

### Husky Not Running

```bash
npm run prepare
```

### Permission Denied on Hook

```bash
chmod +x .husky/pre-commit
```

### ESLint/Prettier Conflicts

The configuration includes `eslint-config-prettier` which disables ESLint rules that conflict with Prettier.

### Composer Lint Failing

Check individual tools:

```bash
vendor/bin/pint --test
vendor/bin/phpstan analyse
composer audit
```

## CI/CD Integration

The `.husky/install.mjs` script automatically skips Husky installation in:
- Production environments (`NODE_ENV=production`)
- CI environments (`CI=true`)

This prevents hook installation on servers and CI pipelines.

## Benefits

✅ **Consistent Code Style** - All team members follow the same formatting rules
✅ **Early Error Detection** - Catch issues before they reach the repository
✅ **Automated Quality** - No manual linting needed
✅ **Security Checks** - Composer audit runs on every commit
✅ **Fast Feedback** - Only staged files are checked
✅ **Git Integration** - Seamlessly integrated into the git workflow

## Additional Notes

- **Husky v9** uses `.husky/` directory instead of the deprecated `.husky/_/husky.sh` approach
- **lint-staged** only runs on staged files, making it fast
- **Prettier** auto-fixes formatting issues automatically
- **PHP linting** includes both formatting (Pint) and static analysis (PHPStan)
- **Composer audit** ensures no known security vulnerabilities in dependencies
