import type { ResponseError } from '@centreon/ui';

interface BulkResult {
  results: Array<{ href: string; status: number }>;
}

/**
 * Turns a selection into one request per row.
 *
 * Returns what a bulk endpoint would, so `useBulkResponse` can name a partial
 * failure rather than reading nothing.
 */
const fanOut = async <TResponse>(
  ids: Array<number>,
  mutate: (id: number) => Promise<TResponse>
): Promise<BulkResult> => {
  const responses = await Promise.all(ids.map((id) => mutate(id)));

  return {
    results: responses.map((response, index) => {
      const { isError, statusCode } = (response ?? {}) as ResponseError;

      return {
        href: `/${ids[index]}`,
        status: isError ? (statusCode ?? 500) : 204
      };
    })
  };
};

export default fanOut;
