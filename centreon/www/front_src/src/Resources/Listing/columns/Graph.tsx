import dayjs from 'dayjs';
import { Suspense, useState } from 'react';

import { path, isNil, not } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import IconGraph from '@mui/icons-material/BarChart';
import { Paper } from '@mui/material';

import type { ComponentColumnProps, LineChartData } from '@centreon/ui';
import {
  IconButton,
  LineChart,
  LoadingSkeleton,
  useFetchQuery
} from '@centreon/ui';

import { lastDayPeriod } from '@centreon/ui';
import FederatedComponent from '../../../components/FederatedComponents';
import type { ResourceDetails } from '../../Details/models';
import type { Resource } from '../../models';
import { labelGraph, labelServiceGraphs } from '../../translatedLabels';

import HoverChip from './HoverChip';
import IconColumn from './IconColumn';

const useStyles = makeStyles()((theme) => ({
  button: {
    padding: 0
  },
  graph: {
    display: 'block',
    overflow: 'auto',
    padding: theme.spacing(1),
    width: 575
  }
}));

interface GraphProps {
  endpoint?: string;
  row: Resource | ResourceDetails;
}

const Graph = ({ row, endpoint }: GraphProps): JSX.Element => {
  const [areaThresholdLines, setAreaThresholdLines] = useState();

  const start = lastDayPeriod.getStart().toISOString();
  const end = dayjs().toISOString();

  const graphEndpoint = `${endpoint}?start=${start}&end=${end}`;

  const { data } = useFetchQuery<LineChartData>({
    getEndpoint: () => graphEndpoint,
    getQueryKey: () => ['chartLineColumns', endpoint],
    queryOptions: {
      enabled: !!graphEndpoint,
      suspense: false
    }
  });

  const getShapeLines = (callback) => {
    setAreaThresholdLines(callback(row.uuid));
  };

  const rest = areaThresholdLines ? { shapeLines: areaThresholdLines } : {};

  return (
    <Suspense fallback={<LoadingSkeleton height="100%" />}>
      <FederatedComponent
        path="/anomaly-detection/enableThresholdLines"
        styleMenuSkeleton={{ height: 0, width: 0 }}
        type={row?.type}
        getShapeLines={getShapeLines}
      />
      <LineChart
        data={data}
        end={end}
        height={200}
        legend={{ mode: 'grid', placement: 'bottom' }}
        lineStyle={{ lineWidth: 1 }}
        start={start}
        tooltip={{ mode: 'hidden' }}
        displayAnchor={{
          displayGuidingLines: false,
          displayTooltipsGuidingLines: false
        }}
        {...rest}
      />
    </Suspense>
  );
};

const renderChip =
  ({ onClick, label, className }) =>
  (): JSX.Element => (
    <IconButton
      ariaLabel={label}
      className={className}
      size="small"
      title={label}
      onClick={onClick}
    >
      <IconGraph fontSize="small" />
    </IconButton>
  );

interface Props {
  onClick: (row) => void;
}

const GraphColumn = ({
  onClick
}: Props): ((props: ComponentColumnProps) => JSX.Element | null) => {
  const GraphHoverChip = ({
    row,
    isHovered
  }: ComponentColumnProps): JSX.Element | null => {
    const { classes } = useStyles();

    const { type } = row;

    const isHost = type === 'host';

    const endpoint = path<string | undefined>(
      ['links', 'endpoints', 'performance_graph'],
      row
    );

    if (isNil(endpoint) && !isHost) {
      return null;
    }

    const label = isHost ? labelServiceGraphs : labelGraph;

    return (
      <IconColumn>
        <HoverChip
          Chip={renderChip({
            className: classes.button,
            label,
            onClick: () => onClick(row)
          })}
          isHovered={isHovered}
          label={label}
        >
          {({ isChipHovered }): JSX.Element => {
            if (isHost || not(isChipHovered) || not(isHovered)) {
              return <div />;
            }

            return (
              <Paper className={classes.graph}>
                <Graph endpoint={endpoint} row={row} />
              </Paper>
            );
          }}
        </HoverChip>
      </IconColumn>
    );
  };

  return GraphHoverChip;
};

export default GraphColumn;
