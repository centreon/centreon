import { equals } from 'ramda';

import { ResourceData } from '../models';

import HostTooltipContent from './HostTooltipContent';
import ServiceTooltipContent from './ServiceTooltipContent';

interface Props {
  data: ResourceData;
  resourceType: string;
}

export const StatusTooltip = ({ resourceType, data }: Props): JSX.Element => {
  return equals(resourceType, 'host') ? (
    <HostTooltipContent data={data} />
  ) : (
    <ServiceTooltipContent data={data} />
  );
};

export default (resourceType: string) =>
  ({ data }: Pick<Props, 'data'>) => (
    <StatusTooltip data={data} resourceType={resourceType} />
  );
