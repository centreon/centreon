import { buildEndPoint, clearCacheEndpoint } from './endpoint';

describe('module endpoints', () => {
  it('lets a single update clear the cache', () => {
    expect(buildEndPoint({ action: 'update', id: 'bam', type: 'module' })).toBe(
      './api/internal.php?object=centreon_module&action=update&id=bam&type=module'
    );
  });

  it('leaves the cache to the end of a batch', () => {
    expect(
      buildEndPoint({
        action: 'update',
        clearCache: false,
        id: 'bam',
        type: 'module'
      })
    ).toBe(
      './api/internal.php?object=centreon_module&action=update&id=bam&type=module&clearCache=0'
    );
    expect(clearCacheEndpoint).toBe(
      './api/internal.php?object=centreon_module&action=clearCache'
    );
  });
});
