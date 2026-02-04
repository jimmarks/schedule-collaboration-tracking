# Creating GitHub Releases

## Quick Release Process

### 1. Commit and Push Your Changes
```bash
git add .
git commit -m "Release v1.0.7: Add auto-updater"
git push origin main
```

### 2. Create a Git Tag
```bash
git tag -a v1.0.7 -m "Release 1.0.7 - Auto-updater integrated"
git push origin v1.0.7
```

### 3. Create GitHub Release

**Option A: Via GitHub Web UI** (Recommended)
1. Go to: https://github.com/jimmarks/schedule-collaboration-tracking/releases
2. Click "Create a new release"
3. Click "Choose a tag" → Select `v1.0.7`
4. Release title: `v1.0.7`
5. Description: Copy from CHANGELOG.md or write summary
6. Upload the ZIP file: `download/schedule-collaboration-tracking-v1.0.7.zip`
7. Click "Publish release"

**Option B: Via GitHub CLI**
```bash
gh release create v1.0.7 \
  download/schedule-collaboration-tracking-v1.0.7.zip \
  --title "v1.0.7" \
  --notes "Auto-updater integrated for seamless updates"
```

## What Happens Next

Once the release is published:

✅ **Existing Installations** will see "Update available" in WordPress
✅ **New Installations** can download the latest version
✅ **Automatic Updates** work via the Plugin Update Checker

## Version Numbering

Follow semantic versioning:
- **Major** (1.x.x) - Breaking changes
- **Minor** (x.1.x) - New features, backwards compatible
- **Patch** (x.x.1) - Bug fixes

## Regular Release Workflow

```bash
# 1. Make changes
# 2. Build package (auto-increments version)
bash build-package.sh

# 3. Commit
git add .
git commit -m "Describe your changes"
git push origin main

# 4. Tag the release (use the version from build)
git tag -a v1.0.7 -m "Release description"
git push origin v1.0.7

# 5. Create GitHub release and upload the ZIP
# Go to GitHub → Releases → New Release
# Attach: download/schedule-collaboration-tracking-v1.0.7.zip
```

## Testing Updates

Before releasing to users:

1. Install previous version on test WordPress site
2. Create the new release on GitHub
3. Check for updates in WordPress (Plugins → Schedule Collaboration Tracking)
4. Click "Update" and verify it works
5. Test plugin functionality after update

## Rollback Process

If you need to rollback:

1. Users can manually install previous version ZIP
2. Or create a new release with previous code
3. Version number must be higher than current to trigger update

## Notes

- **ZIP file name** doesn't matter, only the Git tag version
- **Release notes** help users know what changed
- **Pre-release checkbox** for beta versions (won't auto-update)
- **Draft releases** let you prepare without publishing
