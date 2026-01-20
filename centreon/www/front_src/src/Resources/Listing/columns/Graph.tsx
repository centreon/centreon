import dayjs from 'dayjs';
import { path, isNil, not } from 'ramda';
import { ReactElement, Suspense, useState } from 'react';
import { makeStyles } from 'tss-react/mui';

import IconGraph from '@mui/icons-material/BarChart';
import { Paper } from '@mui/material';

import {
  type ComponentColumnProps,
  IconButton,
  LineChart,
  type LineChartData,
  LoadingSkeleton,
  lastDayPeriod,
  useFetchQuery
} from '@centreon/ui';

import dayjs from 'dayjs';
import { isNil, not, path } from 'ramda';
import { ReactElement, Suspense, useState } from 'react';
import { makeStyles } from 'tss-react/mui';

import FederatedComponent from '../../../components/FederatedComponents';
import { graphsCapNumber } from '../../constants';
import type { ResourceDetails } from '../../Details/models';
import { graphsCapNumber } from '../../constants';
import type { Resource } from '../../models';
import TooManyElementsCard from '../../TooManyElementsCard';
import { labelGraph, labelServiceGraphs } from '../../translatedLabels';

import TooManyElementsCard from '../../TooManyElementsCard';
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

const Graph = ({ row, endpoint }: GraphProps): ReactElement => {
  const [areaThresholdLines, setAreaThresholdLines] = useState();

  const start = lastDayPeriod.getStart().toISOString();
  const end = dayjs().toISOString();

  const graphEndpoint = `${endpoint}?start=${start}&end=${end}`;

  const { data, isLoading, isFetching } = useFetchQuery<LineChartData>({
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

  const metricsCount = data?.metrics.length ?? 0;
  if (metricsCount > graphsCapNumber) {
    return (
      <Suspense fallback={<LoadingSkeleton height="100%" />}>
        <TooManyElementsCard listing={true} title={data?.global.title ?? ''} />
      </Suspense>
    );
  }

  return (
    <Suspense fallback={<LoadingSkeleton height="100%" />}>
      <FederatedComponent
        getShapeLines={getShapeLines}
        path="/anomaly-detection/enableThresholdLines"
        styleMenuSkeleton={{ height: 0, width: 0 }}
        type={row?.type}
      />
      <LineChart
        data={data}
        displayAnchor={{
          displayGuidingLines: false,
          displayTooltipsGuidingLines: false
        }}
        end={end}
        height={200}
        legend={{ mode: 'grid', placement: 'bottom' }}
        lineStyle={{ lineWidth: 1 }}
        loading={isFetching || isLoading || !data}
        start={start}
        timeShiftZones={{
          enable: false
        }}
        tooltip={{ mode: 'hidden' }}
        {...rest}
      />
    </Suspense>
  );
};

const renderChip =
  ({ onClick, label, className }) =>
  (): ReactElement => (
    <IconButton
      ariaLabel={label}
      className={className}
      onClick={onClick}
      size="small"
      title={label}
    >
      <IconGraph fontSize="small" />
    </IconButton>
  );

interface Props {
  onClick: (row) => void;
}

const GraphColumn = ({
  onClick
}: Props): ((props: ComponentColumnProps) => ReactElement | null) => {
  const GraphHoverChip = ({
    row,
    isHovered
  }: ComponentColumnProps): ReactElement | null => {
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
          {({ isChipHovered }): ReactElement => {
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
