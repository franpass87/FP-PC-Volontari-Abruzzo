# GitHub Actions Workflows

This repository includes GitHub Actions workflows to automatically build WordPress plugin ZIP files.

## Workflows

### 1. Build WordPress Plugin ZIP (`build-wordpress-zip.yml`)

**Triggers:**
- Push to `main` branch
- Git tags (e.g., `v1.0`, `v1.1.0`)
- Pull requests to `main`
- Manual trigger with optional version input

**Features:**
- Automatically determines version from git tags or commit hash
- Creates a clean plugin directory structure
- Updates version numbers in plugin files for tagged releases
- Generates ZIP file with proper WordPress plugin structure
- Uploads ZIP as GitHub Actions artifact
- Creates GitHub releases for tagged versions
- Provides detailed build summary

**Usage:**
```bash
# Trigger automatic build on push
git push origin main

# Create a release build with git tag
git tag v1.1.0
git push origin v1.1.0

# Manual trigger via GitHub web interface
# Go to Actions → Build WordPress Plugin ZIP → Run workflow
```

### 2. Quick Plugin Build (`quick-build.yml`)

**Triggers:**
- Manual trigger only

**Features:**
- Fast build for testing purposes
- Option to include or exclude development files
- Timestamped version naming
- 7-day artifact retention

**Usage:**
- Go to GitHub Actions page
- Select "Quick Plugin Build"
- Click "Run workflow"
- Optionally check "Include development files"

## Generated Files

### Plugin ZIP Structure
```
pc-volontari-abruzzo/
├── pc-volontari-abruzzo.php    # Main plugin file
├── assets/
│   ├── css/
│   │   └── frontend.css        # Frontend styles
│   └── js/
│       └── frontend.js         # Frontend JavaScript
├── data/
│   └── comuni_abruzzo.json     # Municipality data
├── README.md                   # Documentation
└── plugin-info.txt             # Build information
```

### Version Management

- **Development builds**: `dev-{commit-hash}` (e.g., `dev-a1b2c3d4`)
- **Tagged releases**: Uses git tag (e.g., `v1.0`, `v1.1.0`)
- **Manual builds**: Uses input version or timestamp
- **Quick builds**: `quick-{timestamp}` (e.g., `quick-20231201-143022`)

## Downloading Built Plugins

### From GitHub Actions
1. Go to the repository's Actions page
2. Click on a completed workflow run
3. Scroll down to "Artifacts" section
4. Download the ZIP file

### From Releases (Tagged versions only)
1. Go to the repository's Releases page
2. Find the desired version
3. Download the attached ZIP file

## WordPress Installation

1. Download the plugin ZIP file
2. Log into your WordPress admin panel
3. Go to Plugins → Add New → Upload Plugin
4. Select the downloaded ZIP file
5. Click "Install Now"
6. Activate the plugin

## Customization

### Adding Files to ZIP
Edit `.github/workflows/build-wordpress-zip.yml` and modify the "Prepare plugin directory" step to include additional files:

```bash
# Add new file
cp your-file.txt "$PLUGIN_DIR/"

# Add new directory
cp -r your-directory "$PLUGIN_DIR/"
```

### Excluding Files
Modify the ZIP creation commands or add patterns to `.gitignore`.

### Version Format
Modify the version detection logic in the "Get version information" step.

## Build Status

The workflow provides detailed information about:
- Plugin version
- Build date and commit
- ZIP file size
- Included files
- Installation instructions