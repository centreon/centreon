import { cond, equals, map } from 'ramda';

import { SeverityCode } from '@centreon/ui';

import { DisplayType, ResourceListing } from './models';

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

  const result = map((item) => {
    return {
      ...item,
      isHeadRow: true,
      parent_resource: item?.children?.resources,
      resourceCount: item?.children?.status_count
    };
  }, data?.result || []);

  const hostsResponse = { ...data, result } as ResourceListing;

  return hostsResponse;
};

export const getStatus = cond([
  [equals('ok'), () => ({ label: 'O', severity: SeverityCode.OK })],
  [equals('up'), () => ({ label: 'U', severity: SeverityCode.OK })],
  [equals('warning'), () => ({ label: 'W', severity: SeverityCode.Medium })],
  [equals('critical'), () => ({ label: 'C', severity: SeverityCode.High })],
  [equals('unknown'), () => ({ label: 'U', severity: SeverityCode.Low })],
  [equals('pending'), () => ({ label: 'P', severity: SeverityCode.Pending })]
]);
