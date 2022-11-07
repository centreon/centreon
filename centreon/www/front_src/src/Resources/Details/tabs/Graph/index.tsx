<<<<<<< HEAD
import { equals, or } from 'ramda';

import { Theme } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { equals, or } from 'ramda';

import { Theme, makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import { TabProps } from '..';
import TimePeriodButtonGroup from '../../../Graph/Performance/TimePeriods';
import ExportablePerformanceGraphWithTimeline from '../../../Graph/Performance/ExportableGraphWithTimeline';
<<<<<<< HEAD
import memoizeComponent from '../../../memoizedComponent';
=======
import { ResourceContext, useResourceContext } from '../../../Context';
import memoizeComponent from '../../../memoizedComponent';
import { GraphOptions } from '../../models';
import useGraphOptions, {
  GraphOptionsContext,
} from '../../../Graph/Performance/ExportableGraphWithTimeline/useGraphOptions';
>>>>>>> centreon/dev-21.10.x

import HostGraph from './HostGraph';

const useStyles = makeStyles((theme: Theme) => ({
  container: {
    display: 'grid',
    gridRowGap: theme.spacing(2),
    gridTemplateRows: 'auto 1fr',
  },
  exportToPngButton: {
    display: 'flex',
    justifyContent: 'space-between',
    margin: theme.spacing(0, 1, 1, 2),
  },
  graph: {
    height: '100%',
    margin: 'auto',
    width: '100%',
  },
  graphContainer: {
    display: 'grid',
    gridTemplateRows: '1fr',
    padding: theme.spacing(2, 1, 1),
  },
}));

<<<<<<< HEAD
const GraphTabContent = ({ details }: TabProps): JSX.Element => {
  const classes = useStyles();

=======
type GraphTabContentProps = TabProps &
  Pick<ResourceContext, 'tabParameters' | 'setGraphTabParameters'>;

const GraphTabContent = ({
  details,
  tabParameters,
  setGraphTabParameters,
}: GraphTabContentProps): JSX.Element => {
  const classes = useStyles();

  const changeTabGraphOptions = (options: GraphOptions): void => {
    setGraphTabParameters({
      ...tabParameters.graph,
      options,
    });
  };

  const graphOptions = useGraphOptions({
    changeTabGraphOptions,
    options: tabParameters.graph?.options,
  });

>>>>>>> centreon/dev-21.10.x
  const type = details?.type as string;
  const equalsService = equals('service');
  const equalsMetaService = equals('metaservice');

  const isService = or(equalsService(type), equalsMetaService(type));

  return (
<<<<<<< HEAD
    <div className={classes.container}>
      {isService ? (
        <>
          <TimePeriodButtonGroup />
          <ExportablePerformanceGraphWithTimeline
            graphHeight={280}
            resource={details}
          />
        </>
      ) : (
        <HostGraph details={details} />
      )}
    </div>
  );
};

const MemoizedGraphTabContent = memoizeComponent<TabProps>({
  Component: GraphTabContent,
  memoProps: ['details', 'ariaLabel'],
});

const GraphTab = ({ details }: TabProps): JSX.Element => {
  return <MemoizedGraphTabContent details={details} />;
=======
    <GraphOptionsContext.Provider value={graphOptions}>
      <div className={classes.container}>
        {isService ? (
          <>
            <TimePeriodButtonGroup />
            <ExportablePerformanceGraphWithTimeline
              graphHeight={280}
              resource={details}
            />
          </>
        ) : (
          <HostGraph details={details} />
        )}
      </div>
    </GraphOptionsContext.Provider>
  );
};

const MemoizedGraphTabContent = memoizeComponent<GraphTabContentProps>({
  Component: GraphTabContent,
  memoProps: ['details', 'tabParameters', 'ariaLabel'],
});

const GraphTab = ({ details }: TabProps): JSX.Element => {
  const { tabParameters, setGraphTabParameters } = useResourceContext();

  return (
    <MemoizedGraphTabContent
      details={details}
      setGraphTabParameters={setGraphTabParameters}
      tabParameters={tabParameters}
    />
  );
>>>>>>> centreon/dev-21.10.x
};

export default GraphTab;
