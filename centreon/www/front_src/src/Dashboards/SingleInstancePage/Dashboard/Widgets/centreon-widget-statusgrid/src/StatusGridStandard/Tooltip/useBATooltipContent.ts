import { equals } from 'ramda';

import {
  SeverityCode,
  useFetchQuery,
  usePluralizedTranslation
} from '@centreon/ui';

import { getBAEndpoint } from '../../api/endpoints';
import { businessActivityDecoder } from '../api/decoders';
import { BusinessActivity, Indicator, ResourceData } from '../models';

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

  const health = Math.floor(((total - ProblematicKPIsCount) * 100) / total);

  const criticalKPIsCount =
    businessActivity?.indicators?.filter(({ status }) =>
      equals(SeverityCode.High, status?.severityCode)
    )?.length || 0;

  const criticalKPIsPercentage = Math.floor((criticalKPIsCount * 100) / total);

  const criticalKPIs = equals(isPercentage, false)
    ? `${criticalKPIsCount} ${pluralizedT({
        count: criticalKPIsCount || 0,
        label: 'KPI'
      })}`
    : `${criticalKPIsPercentage}%`;

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
