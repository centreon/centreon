import { useFetchQuery } from '@centreon/ui';

import { getBooleanRuleEndpoint } from '../../api/endpoints';
import { booleanRuleDecoder } from '../api/decoders';
import { BooleanRule, ResourceData } from '../models';

interface UseServiceTooltipContentState {
  isImpactingWhenTrue?: boolean;
  isLoading?: boolean;
  statusName?: string;
}

const useBooleanTooltipContent = (
  data: ResourceData
): UseServiceTooltipContentState => {
  const { data: booleanRule, isLoading } = useFetchQuery<BooleanRule>({
    decoder: booleanRuleDecoder,
    getEndpoint: () => getBooleanRuleEndpoint(data?.resourceId || data?.id),
    getQueryKey: () => ['statusgrid', 'BooleanRule', data?.id],
    httpCodesBypassErrorSnackbar: [404],
    queryOptions: {
      suspense: false
    }
  });

  return {
    isImpactingWhenTrue: booleanRule?.isImpactingWhenTrue,
    isLoading,
    statusName: booleanRule?.status.name
  };
};

export default useBooleanTooltipContent;
