import { equals } from 'ramda';

import { SeverityCode, useFetchQuery } from '@centreon/ui';

import { ResourceData, CalculationMethod } from '../models';
import { getBAEndpoint } from '../../api/endpoints';

interface UseServiceTooltipContentState {
  calculationMethod: CalculationMethod;
  criticalLevel: number | null;
  indicatorsWithProblems: Array<object>;
  indicatorsWithStatusOk: Array<object>;
  infrastructureViewId: number | null;
  isLoading: boolean;
  isPercentage: boolean | null;
  total: number;
  warningLevel: number | null;
}

const useBATooltipContent = (
  data: ResourceData
): UseServiceTooltipContentState => {
  const { data: baData, isLoading } = useFetchQuery({
    getEndpoint: () => getBAEndpoint(data?.id),
    getQueryKey: () => ['statusgrid', 'BA', data?.id],
    httpCodesBypassErrorSnackbar: [404],
    queryOptions: {
      suspense: false
    }
  });

  const indicatorsWithProblems = baData?.indicators?.filter(
    ({ status }) =>
      equals(SeverityCode.High, status?.severity_code) ||
      equals(SeverityCode.Medium, status?.severity_code)
  );

  const indicatorsWithStatusOk = baData?.indicators?.filter(({ status }) =>
    equals(SeverityCode.OK, status?.severity_code)
  );

  const warningLevel = baData?.level_w || null;
  const criticalLevel = baData?.level_c || null;

  return {
    calculationMethod: baData?.calculation_method?.name,
    criticalLevel,
    indicatorsWithProblems,
    indicatorsWithStatusOk,
    infrastructureViewId: baData?.infrastructure_view_id,
    isLoading,
    isPercentage: baData?.calculation_method?.is_percentage,
    total: baData?.indicators?.length || 0,
    warningLevel
  };
};

export default useBATooltipContent;
