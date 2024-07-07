import { equals } from 'ramda';

import { SeverityCode, useFetchQuery } from '@centreon/ui';

import { ResourceData, BusinessActivity, Indicator } from '../models';
import { getBAEndpoint } from '../../api/endpoints';
import { businessActivityDecoder } from '../api/decoders';

interface UseServiceTooltipContentState {
  calculationMethod?: string;
  criticalLevel?: number | null;
  indicatorsWithProblems?: Array<Indicator>;
  indicatorsWithStatusOk?: Array<Indicator>;
  isLoading?: boolean;
  isPercentage?: boolean | null;
  total: number;
  warningLevel?: number | null;
}

const useBATooltipContent = (
  data: ResourceData
): UseServiceTooltipContentState => {
  const { data: businessActivity, isLoading } = useFetchQuery<BusinessActivity>(
    {
      decoder: businessActivityDecoder,
      getEndpoint: () => getBAEndpoint(data?.resourceId || data?.id),
      getQueryKey: () => ['statusgrid', 'BA', data?.id],
      httpCodesBypassErrorSnackbar: [404],
      queryOptions: {
        suspense: false
      }
    }
  );

  const indicatorsWithProblems = businessActivity?.indicators?.filter(
    ({ status }) =>
      equals(SeverityCode.High, status?.severityCode) ||
      equals(SeverityCode.Medium, status?.severityCode)
  );

  const indicatorsWithStatusOk = businessActivity?.indicators?.filter(
    ({ status }) => equals(SeverityCode.OK, status.severityCode)
  );

  const warningLevel = businessActivity?.calculationMethod.warningThreshold;
  const criticalLevel = businessActivity?.calculationMethod.criticalThreshold;
  const calculationMethod = businessActivity?.calculationMethod.name;
  const isPercentage = businessActivity?.calculationMethod.isPercentage;
  const total = businessActivity?.indicators?.length || 0;

  return {
    calculationMethod,
    criticalLevel,
    indicatorsWithProblems,
    indicatorsWithStatusOk,
    isLoading,
    isPercentage,
    total,
    warningLevel
  };
};

export default useBATooltipContent;
