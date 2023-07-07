import { lazy, Suspense, useEffect } from 'react';

import { useAtomValue, useSetAtom } from 'jotai';
import { isNil, not, path } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import IconGraph from '@mui/icons-material/BarChart';
import { Paper } from '@mui/material';

import type { ComponentColumnProps } from '@centreon/ui';
import { IconButton, LoadingSkeleton } from '@centreon/ui';

import FederatedComponent from '../../../components/FederatedComponents';
import { ResourceDetails } from '../../Details/models';
import { lastDayPeriod } from '../../Details/tabs/Graph/models';
import {
  changeMousePositionAndTimeValueDerivedAtom,
  isListingGraphOpenAtom
} from '../../Graph/Performance/Graph/mouseTimeValueAtoms';
import { graphQueryParametersDerivedAtom } from '../../Graph/Performance/TimePeriods/timePeriodAtoms';
import { Resource } from '../../models';
import { labelGraph, labelServiceGraphs } from '../../translatedLabels';

import HoverChip from './HoverChip';
import IconColumn from './IconColumn';

const PerformanceGraph = lazy(() => import('../../Graph/Performance'));

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
  displayCompleteGraph: () => void;
  endpoint?: string;
  row: Resource | ResourceDetails;
}

const Graph = ({
  row,
  endpoint,
  displayCompleteGraph
}: GraphProps): JSX.Element => {
  const getGraphQueryParameters = useAtomValue(graphQueryParametersDerivedAtom);
  const setIsListingGraphOpen = useSetAtom(isListingGraphOpenAtom);
  const changeMousePositionAndTimeValue = useSetAtom(
    changeMousePositionAndTimeValueDerivedAtom
  );

  const graphQueryParameters = getGraphQueryParameters({
    timePeriod: lastDayPeriod
  });

  useEffect(() => {
    setIsListingGraphOpen(true);

    return (): void => {
      setIsListingGraphOpen(false);
      changeMousePositionAndTimeValue({ position: null, timeValue: null });
    };
  }, []);

  return (
    <Suspense fallback={<LoadingSkeleton height="100%" />}>
      <PerformanceGraph
        limitLegendRows
        displayCompleteGraph={displayCompleteGraph}
        displayTitle={false}
        endpoint={`${endpoint}${graphQueryParameters}`}
        graphHeight={150}
        interactWithGraph={false}
        renderAdditionalLines={({
          additionalLinesProps,
          resource
        }): JSX.Element => (
          <FederatedComponent
            displayAdditionalLines
            additionalLinesData={{ additionalLinesProps, resource }}
            path="/anomaly-detection"
          />
        )}
        resource={row}
        timeline={[]}
      />
    </Suspense>
  );
};

const renderChip =
  ({ onClick, label, className }) =>
  (): JSX.Element =>
    (
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

const GraphColumn = ({
  onClick
}: {
  onClick: (row) => void;
}): ((props: ComponentColumnProps) => JSX.Element | null) => {
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
          {({ close, isChipHovered }): JSX.Element => {
            if (isHost || not(isChipHovered) || not(isHovered)) {
              return <div />;
            }

            return (
              <Paper className={classes.graph}>
                <Graph
                  displayCompleteGraph={(): void => {
                    onClick(row);
                    close();
                  }}
                  endpoint={endpoint}
                  row={row}
                />
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
