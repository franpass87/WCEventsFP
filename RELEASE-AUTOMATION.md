# Release Automation with release-please

This repository uses [release-please](https://github.com/googleapis/release-please) to automate changelog generation, version bumping, and GitHub releases based on Conventional Commits.

## How it works

### 1. Conventional Commits
All commits must follow the [Conventional Commits](https://www.conventionalcommits.org/) format:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

**Examples:**
```bash
feat: add email notifications for new bookings
fix: resolve memory allocation issue in bootstrap
docs: update installation instructions
chore: update dependencies to latest versions
feat!: change booking API structure (BREAKING CHANGE)
```

### 2. Automated Version Bumps

Based on commit types:
- **feat**: Minor version bump (2.1.1 → 2.2.0)
- **fix**: Patch version bump (2.1.1 → 2.1.2) 
- **perf, refactor, docs, chore**: Patch version bump
- **feat!** or **fix!**: Major version bump (2.1.1 → 3.0.0)

### 3. Files Updated Automatically

When a release PR is created, these files are automatically updated:

- **wceventsfp.php**: Plugin header `Version:` and `WCEFP_VERSION` constant
- **includes/Bootstrap/Plugin.php**: `private $version` property
- **README.md**: Version in title
- **CHANGELOG.md**: Generated from conventional commits

### 4. Release Process

1. **Developers**: Commit using conventional commit format
2. **release-please**: Creates/updates Release PR with:
   - Updated CHANGELOG.md
   - Version bumps in all tracked files
   - Release notes based on commits
3. **Maintainer**: Reviews and merges Release PR
4. **Automation**: Creates Git tag + GitHub Release

## Examples

### Feature Addition
```bash
git commit -m "feat: add Google Reviews integration for bookings"
# Result: Minor version bump (2.1.1 → 2.2.0)
```

### Bug Fix
```bash
git commit -m "fix: resolve WSOD issue with memory allocation in plugin bootstrap"
# Result: Patch version bump (2.1.1 → 2.1.2)
```

### Breaking Change
```bash
git commit -m "feat!: restructure booking API endpoints

BREAKING CHANGE: The /api/bookings endpoint now requires authentication
and returns a different response format."
# Result: Major version bump (2.1.1 → 3.0.0)
```

### Documentation
```bash
git commit -m "docs: update installation guide with new PHP requirements"
# Result: Patch version bump (2.1.1 → 2.1.2)
```

## Configuration Files

- **`.github/release-please-config.json`**: Main configuration
- **`.github/.release-please-manifest.json`**: Current version tracking
- **`.github/workflows/release-please.yml`**: GitHub Actions workflow

## Maintenance

### Adding New Files for Version Updates

To add more files that should have their version updated:

1. Add version marker to the file:
   ```php
   $version = '2.1.1'; // x-release-please-version
   ```

2. Update `.github/release-please-config.json`:
   ```json
   "extra-files": [
     "README.md",
     "wceventsfp.php", 
     "includes/Bootstrap/Plugin.php",
     "path/to/new/file.php"
   ]
   ```

### Updating Paths

If plugin files are moved, update the paths in:
- `.github/release-please-config.json`
- Existing GitHub Actions workflows that reference plugin files

## Setup Requirements

### Personal Access Token Configuration

The release-please workflow can use either a custom Personal Access Token (PAT) or the default GitHub token.

**⚠️ Note:** The workflow now includes automatic fallback to `GITHUB_TOKEN` if `RELEASE_PLEASE_TOKEN` is not configured, so the setup steps below are **optional** but recommended for repositories with special permission requirements.

**Setup Steps (Optional):**

1. **Create a Personal Access Token:**
   - Go to GitHub Settings → Developer settings → Personal access tokens → Tokens (classic)
   - Click "Generate new token (classic)"
   - Set expiration (recommended: 1 year)
   - Select scopes:
     - ✅ `repo` (Full control of private repositories)
     - ✅ `workflow` (Update GitHub Action workflows)

2. **Add Token to Repository:**
   - Go to repository Settings → Secrets and variables → Actions
   - Click "New repository secret"
   - Name: `RELEASE_PLEASE_TOKEN`
   - Value: Your PAT from step 1
   - Click "Add secret"

3. **Token Usage:**
   - If `RELEASE_PLEASE_TOKEN` is configured: Uses the custom PAT
   - If `RELEASE_PLEASE_TOKEN` is not configured: Automatically falls back to `GITHUB_TOKEN`
   - The fallback mechanism ensures the workflow works without additional setup

## Troubleshooting

### Release PR Not Created
- Check commit messages follow conventional format
- Verify workflow has proper permissions (contents: write, pull-requests: write)
- Check GitHub Actions logs for authentication errors
- The workflow automatically uses `GITHUB_TOKEN` if `RELEASE_PLEASE_TOKEN` is not configured

### Authentication Errors
- ✅ **New**: The workflow now automatically falls back to `GITHUB_TOKEN` if `RELEASE_PLEASE_TOKEN` is unavailable
- If using a custom PAT, ensure `RELEASE_PLEASE_TOKEN` secret exists and is not expired
- Verify PAT has `repo` and `workflow` scopes
- Check if PAT owner has write access to the repository
- For most repositories, the default `GITHUB_TOKEN` should work without additional setup

### Version Not Updated in Files  
- Verify `// x-release-please-version` or `<!-- x-release-please-version -->` markers exist
- Check file paths in `.github/release-please-config.json`
- Ensure file is listed in `extra-files` array