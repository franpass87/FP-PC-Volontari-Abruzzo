# GitHub Actions Workflow Configuration
# This file documents the workflow setup for PC Volontari Abruzzo plugin

## Workflow Overview

This repository uses GitHub Actions for automated CI/CD processes:

### 1. CI/CD Pipeline (`ci.yml`)
- **Triggers:** Push and PR to main/develop branches
- **Purpose:** Code quality checks, security scanning, WordPress compatibility testing
- **Jobs:**
  - Code quality checks (PHP syntax, WordPress coding standards)
  - Security scanning with Trivy
  - WordPress compatibility testing across multiple PHP/WP versions

### 2. Deployment (`deploy.yml`)
- **Triggers:** Release published, manual dispatch
- **Purpose:** Package and deploy plugin releases
- **Jobs:**
  - Build plugin package
  - Upload to GitHub releases
  - Deploy to WordPress.org (when configured)

### 3. Release Management (`release.yml`)
- **Triggers:** Version changes in main plugin file, manual dispatch
- **Purpose:** Automated release creation and version management
- **Jobs:**
  - Detect version changes
  - Create GitHub releases with changelogs
  - Automated version bumping

### 4. Code Quality & Linting (`quality.yml`)
- **Triggers:** Push and PR to main/develop branches
- **Purpose:** Comprehensive code quality checks
- **Jobs:**
  - PHP CodeSniffer (WordPress standards)
  - ESLint for JavaScript
  - Stylelint for CSS
  - JSON validation
  - Markdown linting
  - PHPStan static analysis

### 5. Dependency Updates (`dependencies.yml`)
- **Triggers:** Weekly schedule (Mondays 9 AM UTC), manual dispatch
- **Purpose:** Automated dependency updates and security audits
- **Jobs:**
  - Update Composer/NPM dependencies
  - Security vulnerability scanning
  - WordPress-specific security checks

### 6. Documentation (`docs.yml`)
- **Triggers:** Push to main (docs changes), PR with doc changes
- **Purpose:** Automated documentation generation and validation
- **Jobs:**
  - Generate plugin API documentation
  - Validate README completeness
  - Update changelog and contributor lists

### 7. Issue & PR Management (`issue-pr-management.yml`)
- **Triggers:** Issues and PR events, comments
- **Purpose:** Automated project management
- **Jobs:**
  - Auto-labeling based on content
  - Welcome messages for new contributors
  - PR validation and formatting checks
  - Stale issue/PR management
  - Auto-assign reviewers

## Required Secrets

To fully utilize these workflows, add the following secrets to your repository:

### Optional Secrets
- `WP_ORG_USERNAME`: WordPress.org username for plugin deployment
- `WP_ORG_PASSWORD`: WordPress.org password for plugin deployment

### Default Secrets (Automatically Available)
- `GITHUB_TOKEN`: Automatically provided by GitHub Actions

## Workflow Files Structure

```
.github/
└── workflows/
    ├── ci.yml                    # Main CI/CD pipeline
    ├── deploy.yml               # Deployment automation
    ├── release.yml              # Release management
    ├── quality.yml              # Code quality checks
    ├── dependencies.yml         # Dependency management
    ├── docs.yml                 # Documentation automation
    └── issue-pr-management.yml  # Project management
```

## Customization

### PHP Versions
Edit the matrix in `ci.yml` to test against different PHP versions:
```yaml
matrix:
  php-version: ['7.4', '8.0', '8.1', '8.2']
```

### WordPress Versions
Edit the matrix in `ci.yml` to test against different WordPress versions:
```yaml
matrix:
  wordpress-version: ['5.0', '6.0', '6.4']
```

### Code Standards
Modify the PHPCS standards in `quality.yml`:
```yaml
phpcs --standard=WordPress --extensions=php
```

### Security Scanning
Configure Trivy scanning options in `ci.yml`:
```yaml
- name: Run Trivy vulnerability scanner
  uses: aquasecurity/trivy-action@master
  with:
    scan-type: 'fs'
    format: 'sarif'
```

## Workflow Badges

Add these badges to your README.md:

```markdown
[![CI/CD](https://github.com/franpass87/FP-PC-Volontari-Abruzzo/workflows/CI%2FCD%20Pipeline/badge.svg)](https://github.com/franpass87/FP-PC-Volontari-Abruzzo/actions)
[![Code Quality](https://github.com/franpass87/FP-PC-Volontari-Abruzzo/workflows/Code%20Quality%20%26%20Linting/badge.svg)](https://github.com/franpass87/FP-PC-Volontari-Abruzzo/actions)
```

## Best Practices

1. **Keep workflows focused**: Each workflow has a specific purpose
2. **Use caching**: Cache dependencies when possible to speed up builds
3. **Fail fast**: Set up jobs to fail quickly on critical errors
4. **Security first**: Always scan for vulnerabilities before deployment
5. **Documentation**: Keep workflows documented and up-to-date

## Troubleshooting

### Common Issues

1. **PHPCS fails**: Ensure WordPress Coding Standards are properly installed
2. **Security scan fails**: Review and fix security vulnerabilities before merging
3. **Deploy fails**: Check that version numbers match between plugin file and release
4. **Permission denied**: Ensure GITHUB_TOKEN has necessary permissions

### Debug Mode

Enable debug logging by adding this to any workflow:
```yaml
env:
  ACTIONS_STEP_DEBUG: true
```