import { equals } from 'ramda';

import { ResourceData } from '../models';

import HostTooltipContent from './HostTooltipContent';
import ServiceTooltipContent from './ServiceTooltipContent';
import BATooltipContent from './BATooltipContent';

interface Props {
  data: ResourceData;
  resourceType: string;
}

export const StatusTooltip = ({ resourceType, data }: Props): JSX.Element => {
  if (equals(resourceType, 'host')) {
    return <HostTooltipContent data={data} />;
  }

  if (equals(resourceType, 'business-activity')) {
    return <BATooltipContent data={data} />;
  }

  if (equals(resourceType, 'boolean-rule')) {
    return <div />;
  }

  return <ServiceTooltipContent data={data} />;
};

export default () =>
  ({ data }: Pick<Props, 'data'>) => (
    <StatusTooltip data={data} resourceType={data?.type} />
  );
