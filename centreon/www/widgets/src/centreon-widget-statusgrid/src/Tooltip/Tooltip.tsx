import { equals } from 'ramda';

import { ResourceData } from '../models';

import HostTooltipContent from './HostTooltipContent';

interface Props {
  data: ResourceData;
  resourceType: string;
}

const Tooltip = ({ resourceType, data }: Props): JSX.Element => {
  return equals(resourceType, 'host') ? (
    <HostTooltipContent data={data} />
  ) : (
    <div />
  );
};

export default (resourceType: string) =>
  ({ data }: Pick<Props, 'data'>) => (
    <Tooltip data={data} resourceType={resourceType} />
  );
