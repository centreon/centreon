import { T, always, cond, equals, map } from 'ramda';

import { ResourceListing, DisplayType } from './models';

export const formatStatusFilter = cond([
  [equals('success'), always(['ok', 'up'])],
  [equals('warning'), always(['warning'])],
  [equals('problem'), always(['down', 'critical'])],
  [equals('undefined'), always(['unreachable', 'unknown'])],
  [equals('pending'), always(['pending'])],
  [T, always([])]
]);

interface FormatRessourcesProps {
  data?: ResourceListing;
  displayType: DisplayType;
}
export const formatRessources = ({
  data,
  displayType
}: FormatRessourcesProps): ResourceListing => {
  if (!equals(displayType, DisplayType.Host)) {
    return data as ResourceListing;
  }

  const result = map(
    (item) => {
      return {
        ...item,
        parent_resource: item?.children?.resources,
        parent_resourceCount: item?.children?.status_count
      };
    },
    data?.result || []
  );

  const hostsResponse = { ...data, result } as ResourceListing;

  return hostsResponse;
};
