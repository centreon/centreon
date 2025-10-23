import {
  type ComponentColumnProps,
  IconButton,
  LoadingSkeleton,
} from '@centreon/ui';
import IconGraph from '@mui/icons-material/BarChart';
import { Paper } from '@mui/material';
import { useAtomValue, useSetAtom } from 'jotai';
import { isNil, not, path } from 'ramda';
import { lazy, type ReactElement, Suspense, useEffect } from 'react';
import { makeStyles } from 'tss-react/mui';

import FederatedComponent from '../../../components/FederatedComponents';
import { graphsCapNumber } from '../../constants';
import type { ResourceDetails } from '../../Details/models';
import { lastDayPeriod } from '../../Details/tabs/Graph/models';
import {
  changeMousePositionAndTimeValueDerivedAtom,
  isListingGraphOpenAtom
} from '../../Graph/Performance/Graph/mouseTimeValueAtoms';
import { graphQueryParametersDerivedAtom } from '../../Graph/Performance/TimePeriods/timePeriodAtoms';
import type { Resource } from '../../models';
import TooManyElementsCard from '../../TooManyElementsCard';
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
}: GraphProps): ReactElement => {
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

  const metricsCount = data?.metrics.length ?? 0;
  if (metricsCount > graphsCapNumber) {
    return (
      <Suspense fallback={<LoadingSkeleton height="100%" />}>
        <TooManyElementsCard
          listing={true}
          title={data?.global.title ?? ''}
        />
      </Suspense>
    );
  }

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
        }): ReactElement => (
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
  (): ReactElement => (
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
}): ((props: ComponentColumnProps) => ReactElement | null) => {
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
          {({ close, isChipHovered }): ReactElement => {
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
