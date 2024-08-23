import { FC } from 'react';

import { path } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import type { ComponentColumnProps } from '@centreon/ui';

import AcknowledgeChip from '../../../Chip/Acknowledge';
import DowntimeChip from '../../../Chip/Downtime';
import { Resource } from '../../../models';
import { labelAcknowledged, labelInDowntime } from '../../../translatedLabels';
import HoverChip from '../HoverChip';

import AcknowledgementDetailsTable from './DetailsTable/Acknowledgement';
import DowntimeDetailsTable from './DetailsTable/Downtime';

interface StateChipProps {
  Chip: () => JSX.Element;
  DetailsTable: FC<{ endpoint: string }>;
  endpoint: string;
  label: string;
}

const useStyles = makeStyles()({
  container: {
    display: 'flex',
    flexDirection: 'row',
    gridGap: 2,
    marginLeft: 2
  }
});

const StateHoverChip = ({
  endpoint,
  Chip,
  DetailsTable,
  label
}: StateChipProps): JSX.Element => {
  return (
    <HoverChip Chip={Chip} label={label}>
      {(): JSX.Element => <DetailsTable endpoint={endpoint} />}
    </HoverChip>
  );
};

const DowntimeHoverChip = ({
  resource
}: {
  resource: Resource;
}): JSX.Element => {
  const downtimeEndpoint = path(['links', 'endpoints', 'downtime'], resource);

  return (
    <StateHoverChip
      Chip={DowntimeChip}
      DetailsTable={DowntimeDetailsTable}
      endpoint={downtimeEndpoint as string}
      label={`${resource.name} ${labelInDowntime}`}
    />
  );
};

const AcknowledgeHoverChip = ({
  resource
}: {
  resource: Resource;
}): JSX.Element => {
  const acknowledgementEndpoint = path(
    ['links', 'endpoints', 'acknowledgement'],
    resource
  );

  return (
    <StateHoverChip
      Chip={AcknowledgeChip}
      DetailsTable={AcknowledgementDetailsTable}
      endpoint={acknowledgementEndpoint as string}
      label={`${resource.name} ${labelAcknowledged}`}
    />
  );
};

const StateColumn = ({ row }: ComponentColumnProps): JSX.Element => {
  const { classes } = useStyles();

  return (
    <div className={classes.container}>
      {row.is_in_downtime && <DowntimeHoverChip resource={row} />}
      {row.is_acknowledged && <AcknowledgeHoverChip resource={row} />}
    </div>
  );
};

export default StateColumn;
