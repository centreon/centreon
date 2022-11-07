<<<<<<< HEAD
import { useEffect } from 'react';

import { path, isNil, not } from 'ramda';
import { useAtomValue, useUpdateAtom } from 'jotai/utils';

import { Paper } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
import IconGraph from '@mui/icons-material/BarChart';
=======
import * as React from 'react';

import { path, isNil, not } from 'ramda';

import { makeStyles, Paper } from '@material-ui/core';
import IconGraph from '@material-ui/icons/BarChart';
>>>>>>> centreon/dev-21.10.x

import { IconButton, ComponentColumnProps } from '@centreon/ui';

import { labelGraph, labelServiceGraphs } from '../../translatedLabels';
import PerformanceGraph from '../../Graph/Performance';
import { ResourceDetails } from '../../Details/models';
import { Resource } from '../../models';
<<<<<<< HEAD
import {
  changeMousePositionAndTimeValueDerivedAtom,
  isListingGraphOpenAtom,
} from '../../Graph/Performance/Graph/mouseTimeValueAtoms';
import { graphQueryParametersDerivedAtom } from '../../Graph/Performance/TimePeriods/timePeriodAtoms';
import { lastDayPeriod } from '../../Details/tabs/Graph/models';
=======
import useTimePeriod from '../../Graph/Performance/TimePeriods/useTimePeriod';
>>>>>>> centreon/dev-21.10.x

import HoverChip from './HoverChip';
import IconColumn from './IconColumn';

const useStyles = makeStyles((theme) => ({
  graph: {
    display: 'block',
    overflow: 'auto',
    padding: theme.spacing(1),
    width: 575,
  },
}));

interface GraphProps {
  displayCompleteGraph: () => void;
  endpoint?: string;
  row: Resource | ResourceDetails;
}

const Graph = ({
  row,
  endpoint,
  displayCompleteGraph,
}: GraphProps): JSX.Element => {
<<<<<<< HEAD
  const getGraphQueryParameters = useAtomValue(graphQueryParametersDerivedAtom);
  const setIsListingGraphOpen = useUpdateAtom(isListingGraphOpenAtom);
  const changeMousePositionAndTimeValue = useUpdateAtom(
    changeMousePositionAndTimeValueDerivedAtom,
  );

  const graphQueryParameters = getGraphQueryParameters({
    timePeriod: lastDayPeriod,
  });

  useEffect(() => {
    setIsListingGraphOpen(true);

    return (): void => {
      setIsListingGraphOpen(false);
      changeMousePositionAndTimeValue({ position: null, timeValue: null });
    };
  }, []);
=======
  const { periodQueryParameters } = useTimePeriod({});
>>>>>>> centreon/dev-21.10.x

  return (
    <PerformanceGraph
      limitLegendRows
      displayCompleteGraph={displayCompleteGraph}
      displayTitle={false}
<<<<<<< HEAD
      endpoint={`${endpoint}${graphQueryParameters}`}
=======
      endpoint={`${endpoint}${periodQueryParameters}`}
>>>>>>> centreon/dev-21.10.x
      graphHeight={150}
      resource={row}
      timeline={[]}
    />
  );
};

<<<<<<< HEAD
const renderChip =
  ({ onClick, label }) =>
  (): JSX.Element =>
    (
      <IconButton
        ariaLabel={label}
        size="large"
        title={label}
        onClick={onClick}
      >
        <IconGraph fontSize="small" />
      </IconButton>
    );

=======
>>>>>>> centreon/dev-21.10.x
const GraphColumn = ({
  onClick,
}: {
  onClick: (row) => void;
}): ((props: ComponentColumnProps) => JSX.Element | null) => {
  const GraphHoverChip = ({
    row,
    isHovered,
  }: ComponentColumnProps): JSX.Element | null => {
    const classes = useStyles();

    const { type } = row;

    const isHost = type === 'host';

    const endpoint = path<string | undefined>(
      ['links', 'endpoints', 'performance_graph'],
      row,
    );

    if (isNil(endpoint) && !isHost) {
      return null;
    }

    const label = isHost ? labelServiceGraphs : labelGraph;

    return (
      <IconColumn>
        <HoverChip
<<<<<<< HEAD
          Chip={renderChip({ label, onClick: () => onClick(row) })}
=======
          Chip={(): JSX.Element => (
            <IconButton
              ariaLabel={label}
              title={label}
              onClick={(): void => onClick(row)}
            >
              <IconGraph fontSize="small" />
            </IconButton>
          )}
>>>>>>> centreon/dev-21.10.x
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
