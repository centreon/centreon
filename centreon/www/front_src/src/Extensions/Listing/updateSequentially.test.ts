import updateSequentially from './updateSequentially';

const moduleExtension = (id: string): { id: string; type: string } => ({
  id,
  type: 'module'
});

describe('updateSequentially', () => {
  it('updates the extensions one after the other, then clears the cache once', async () => {
    const calls: Array<string> = [];
    let running = 0;

    await updateSequentially({
      clearCache: () => {
        calls.push('clearCache');

        return Promise.resolve();
      },
      extensions: [moduleExtension('bam'), moduleExtension('map')],
      update: ({ id }) => {
        running += 1;
        expect(running).toBe(1);

        return Promise.resolve().then(() => {
          calls.push(id);
          running -= 1;
        });
      }
    });

    expect(calls).toEqual(['bam', 'map', 'clearCache']);
  });

  it('carries on after a failed update, and still clears the cache when the last one fails', async () => {
    const calls: Array<string> = [];

    await updateSequentially({
      clearCache: () => {
        calls.push('clearCache');

        return Promise.resolve();
      },
      extensions: [moduleExtension('bam'), moduleExtension('map')],
      update: ({ id }) => {
        calls.push(id);

        return Promise.reject(new Error(`${id} failed`));
      }
    });

    expect(calls).toEqual(['bam', 'map', 'clearCache']);
  });

  it('does not fail when the cache cannot be cleared', async () => {
    await expect(
      updateSequentially({
        clearCache: () => Promise.reject(new Error('cache:clear failed')),
        extensions: [moduleExtension('bam')],
        update: () => Promise.resolve()
      })
    ).resolves.toBeUndefined();
  });

  it('does not clear the cache for widgets only', async () => {
    const clearCache = jest.fn(() => Promise.resolve());

    await updateSequentially({
      clearCache,
      extensions: [{ id: 'engine-status', type: 'widget' }],
      update: () => Promise.resolve()
    });

    expect(clearCache).not.toHaveBeenCalled();
  });
});
