// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import type { ComponentColumnProps } from '@centreon/ui';

import { path } from 'ramda';

import { Resource } from '../../models';
import { labelAcknowledged, labelInDowntime } from '../../translatedLabels';
import HoverChip from '../HoverChip';
import AcknowledgeChip from './Chip/Acknowledge';
import DowntimeChip from './Chip/Downtime';
import FlappingChip from './Chip/Flapping';
import AcknowledgementDetailsTable from './DetailsTable/Acknowledgement';
import DowntimeDetailsTable from './DetailsTable/Downtime';
import useStyles from './State.styles';

interface StateChipProps {
  Chip: () => JSX.Element;
  DetailsTable: (props: { endpoint: string }) => JSX.Element;
  endpoint: string;
  label: string;
}

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
  const typedRow = row as Resource & {
    is_in_downtime?: boolean;
    is_acknowledged?: boolean;
    is_in_flapping?: boolean;
  };

  return (
    <div className={classes.container}>
      {typedRow.is_in_downtime && <DowntimeHoverChip resource={typedRow} />}
      {typedRow.is_acknowledged && <AcknowledgeHoverChip resource={typedRow} />}
      {typedRow.is_in_flapping && <FlappingChip />}
    </div>
  );
};

export default StateColumn;
