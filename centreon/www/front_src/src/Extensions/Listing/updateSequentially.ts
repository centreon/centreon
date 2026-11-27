import { any, propEq } from 'ramda';

interface Extension {
  id: string;
  type: string;
}

interface UpdateSequentiallyParameters<T extends Extension> {
  clearCache: () => Promise<unknown>;
  extensions: Array<T>;
  update: (extension: T) => Promise<unknown>;
}

// One at a time so a shared dependency is never upgraded concurrently; the cache is cleared once, last.
const updateSequentially = <T extends Extension>({
  clearCache,
  extensions,
  update
}: UpdateSequentiallyParameters<T>): Promise<void> =>
  extensions
    .reduce<Promise<unknown>>(
      (previous, extension) =>
        previous.then(() => update(extension).catch(() => undefined)),
      Promise.resolve()
    )
    .then(() =>
      any(propEq('module', 'type'), extensions)
        ? clearCache().catch(() => undefined)
        : undefined
    )
    .then(() => undefined);

export default updateSequentially;
