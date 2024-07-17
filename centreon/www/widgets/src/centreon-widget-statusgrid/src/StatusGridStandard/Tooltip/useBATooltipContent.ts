import { equals } from 'ramda';

import {
  SeverityCode,
  useFetchQuery,
  usePluralizedTranslation
} from '@centreon/ui';

import { ResourceData, BusinessActivity, Indicator } from '../models';
import { getBAEndpoint } from '../../api/endpoints';
import { businessActivityDecoder } from '../api/decoders';

interface UseServiceTooltipContentState {
  calculationMethod?: string;
  criticalKPIs: string;
  criticalLevel?: number | null;
  health: number;
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
  const { pluralizedT } = usePluralizedTranslation();

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

  const ProblematicKPIsCount = indicatorsWithProblems?.length || 0;

  const health = ((total - ProblematicKPIsCount) * 100) / total;

  const criticalKPIsCount =
    businessActivity?.indicators?.filter(({ status }) =>
      equals(SeverityCode.High, status?.severityCode)
    )?.length || 0;

  const criticalKPIs = equals(isPercentage, false)
    ? `${criticalKPIsCount} ${pluralizedT({
        count: criticalKPIsCount || 0,
        label: 'KPI'
      })}`
    : `${criticalKPIsCount}%`;

  return {
    calculationMethod,
    criticalKPIs,
    criticalLevel,
    health,
    indicatorsWithProblems,
    indicatorsWithStatusOk,
    isLoading,
    isPercentage,
    total,
    warningLevel
  };
};

export default useBATooltipContent;
