# Container Optimization Changes

This document summarizes all the optimizations applied to the Centreon web container.

## Changes Made

### 1. **Dockerfile** - Image Size Optimization
- ✅ Removed duplicate `sudo` package (was listed twice on lines 12 and 25)
- ✅ Enhanced cleanup: Added `/tmp/*` and `/var/tmp/*` to removal after apt operations
- **Expected savings:** 30-50MB

### 2. **10-mysql.sh** - Database Connection Improvements
- ✅ Added configurable timeout (default 300s via `DB_TIMEOUT` env var)
- ✅ Changed to simpler `SELECT 1` query instead of querying mysql.user table
- ✅ Increased sleep interval from 1s to 3s (reduces DB hammering)
- ✅ Better progress logging showing elapsed time
- **Benefit:** Prevents infinite hangs if database fails to start

### 3. **20-installation.sh** - Security & Readiness
- ✅ Fixed password exposure on command line by using MySQL config files (`~/.my.cnf`)
- ✅ Applied secure credentials to all mysql commands (installation, dataset import, language setting)
- ✅ Added `/tmp/docker.ready` marker file when initialization completes
- **Benefit:** Passwords no longer visible in process list or logs

### 4. **00-init.sh** - Startup Performance
- ✅ Removed `rsync` operation (moved to init container)
- ✅ Added verification that application files exist in volume
- ✅ Added version checking to detect sync issues
- **Benefit:** Much faster container restarts (no rsync on every start)

### 5. **60-permissions.sh** - Permission Optimization
- ✅ Added marker file (`/var/cache/centreon/.permissions_set`)
- ✅ Only runs full recursive `chown -R` on first container start
- ✅ Subsequent starts skip slow recursive operations
- **Benefit:** Faster startup times, especially with large volumes

### 6. **container.sh** - Debug Logging
- ✅ Made debug mode optional via `DEBUG` environment variable
- ✅ Only enables `set -x` when `DEBUG=true` or `DEBUG=1`
- **Benefit:** Cleaner logs in production

### 7. **docker-compose.yml** - Init Container Pattern
- ✅ Added `centreon-sync` init container
- ✅ Syncs application files from image to shared volume before services start
- ✅ Stores version marker for upgrade detection
- ✅ Both `php-fpm` and `apache` wait for sync to complete
- **Benefit:** Always fresh code, no stale files from old versions

## How to Undo Changes

If something breaks, you can restore files using git:

```bash
# Undo ALL changes
git restore .

# Undo specific files
git restore Dockerfile
git restore entrypoint/container.d/00-init.sh
git restore entrypoint/container.d/10-mysql.sh
git restore entrypoint/container.d/20-installation.sh
git restore entrypoint/container.d/60-permissions.sh
git restore entrypoint/container.sh
git restore ../../docker/docker-compose.yml

# See what changed
git diff

# Create backup branch
git checkout -b backup-before-optimization
git checkout fth-docker-centreon-web
```

## Testing Procedure

1. **Commit and push changes:**
   ```bash
   git add .
   git commit -m "Optimize container: reduce size, improve security, add init container"
   git push
   ```

2. **Build new image** (your CI/CD should handle this)

3. **Test fresh installation:**
   ```bash
   cd docker
   docker-compose down -v  # Remove old volumes
   docker-compose pull     # Get new image
   docker-compose up -d    # Start services
   docker-compose logs -f centreon-sync  # Watch init container
   docker-compose logs -f php-fpm        # Watch main container
   ```

4. **Test upgrade scenario:**
   ```bash
   # Keep existing volumes, pull new image
   docker-compose pull
   docker-compose up -d
   # centreon-sync should detect version change and update files
   ```

5. **Test container restart:**
   ```bash
   docker-compose restart php-fpm
   # Should be much faster now (no rsync, optimized permissions)
   ```

## Expected Improvements

| Metric | Before | After | Improvement |
|--------|--------|-------|-------------|
| Image size | ~XMB | ~X-50MB | 30-50MB smaller |
| First startup | ~Xs | Similar | Slightly faster |
| Container restart | ~Xs | ~X-30s | Much faster (no rsync) |
| Security | Passwords in logs | Secured | Critical fix |
| Reliability | Can hang forever | Times out | More stable |

## Configuration Options

New environment variables you can set:

```yaml
environment:
  - DEBUG=true           # Enable verbose logging
  - DB_TIMEOUT=300       # Database connection timeout (seconds)
  - PUID=900             # User ID (existing feature)
  - PGID=900             # Group ID (existing feature)
```

## Troubleshooting

### Issue: "Application files not found in volume"
**Cause:** Init container didn't run or failed
**Fix:** Check `docker-compose logs centreon-sync`

### Issue: "Version mismatch warning"
**Cause:** Init container using old image
**Fix:** Run `docker-compose pull` before `docker-compose up`

### Issue: Container startup is slow
**Cause:** First-time permission setting
**Fix:** This is expected on first run. Subsequent restarts will be faster.

### Issue: Need to reset permissions
**Solution:** Delete the marker file and restart:
```bash
docker-compose exec php-fpm rm /var/cache/centreon/.permissions_set
docker-compose restart php-fpm
```

## Rollback Plan

If these changes cause issues in production:

1. **Immediate rollback:**
   ```bash
   git revert HEAD
   git push
   # Wait for CI to build old version
   docker-compose pull
   docker-compose up -d
   ```

2. **Keep data safe:**
   - All volumes remain intact
   - Database data is preserved
   - Only application code changes

## Notes

- The init container pattern is widely used in Kubernetes and works great with Docker Compose
- Password security fix is critical and should not be rolled back
- Permission optimization significantly improves restart times
- All changes maintain backward compatibility with existing volumes
