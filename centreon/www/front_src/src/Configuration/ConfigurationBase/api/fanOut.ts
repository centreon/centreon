import type { ResponseError } from '@centreon/ui';

interface BulkResult {
  results: Array<{ href: string; status: number }>;
}

/**
 * Turns a selection into one request per row, for an API that exposes
 * single-resource operations only.
 *
 * Sequential, not parallel: each write ends its transaction by flagging the
 * host's monitoring server, so concurrent writes on rows sharing one deadlock
 * (MariaDB 1213) and all but the first roll back.
 *
 * Returns the shape a bulk endpoint would, so `useBulkResponse` can report a
 * partial failure by name instead of calling the whole selection a success.
 */
const fanOut = async <TResponse>(
  ids: Array<number>,
  mutate: (id: number) => Promise<TResponse>
): Promise<BulkResult> => {
  const results: BulkResult['results'] = [];

  for (const id of ids) {
    const response = await mutate(id);

    results.push({
      href: `/${id}`,
      status: (response as ResponseError)?.isError ? 500 : 204
    });
  }

  return { results };
};

export default fanOut;
