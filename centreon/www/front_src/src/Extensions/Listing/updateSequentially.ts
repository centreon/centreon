import { any, propEq } from 'ramda';

interface Extension {
  id: string;
  type: string;
}

interface Parameters<T extends Extension> {
  clearCache: () => Promise<unknown>;
  extensions: Array<T>;
  update: (extension: T) => Promise<unknown>;
}

// Sequential, so that a shared dependency is never upgraded twice at once. The modules leave the cache to a single
// clear, sent last whatever their outcome: a failed request is already reported.
const updateSequentially = <T extends Extension>({
  clearCache,
  extensions,
  update
}: Parameters<T>): Promise<void> =>
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
