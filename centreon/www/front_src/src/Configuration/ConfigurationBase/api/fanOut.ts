import type { ResponseError } from '@centreon/ui';

interface BulkResult {
  results: Array<{ href: string; status: number }>;
}

/**
 * Turns a selection into one request per row.
 *
 * Sequential on purpose: these writes contend on a shared row and deadlock
 * when they overlap.
 *
 * Returns what a bulk endpoint would, so `useBulkResponse` can name a partial
 * failure rather than reading nothing.
 */
const fanOut = async <TResponse>(
  ids: Array<number>,
  mutate: (id: number) => Promise<TResponse>
): Promise<BulkResult> => {
  const results: BulkResult['results'] = [];

  for (const id of ids) {
    const response = await mutate(id);

    const { isError, statusCode } = (response ?? {}) as ResponseError;

    results.push({
      href: `/${id}`,
      status: isError ? (statusCode ?? 500) : 204
    });
  }

  return { results };
};

export default fanOut;
