import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import { TabProps } from '..';
import ExportablePerformanceGraphWithTimeline from '../../../Graph/Performance/ExportableGraphWithTimeline';
import TimePeriodButtonGroup from '../../../Graph/Performance/TimePeriods';
import useLoadDetails from '../../../Listing/useLoadResources/useLoadDetails';
import memoizeComponent from '../../../memoizedComponent';
import { ResourceType } from '../../../models';
import FederatedComponent from '../../../../components/FederatedComponents';

import HostGraph from './HostGraph';

const useStyles = makeStyles()((theme: Theme) => ({
  container: {
    display: 'grid',
    gridRowGap: theme.spacing(2),
    gridTemplateRows: 'auto 1fr'
  },
  exportToPngButton: {
    display: 'flex',
    justifyContent: 'space-between',
    margin: theme.spacing(0, 1, 1, 2)
  },
  graph: {
    height: '100%',
    margin: 'auto',
    width: '100%'
  },
  graphContainer: {
    display: 'grid',
    gridTemplateRows: '1fr',
    padding: theme.spacing(2, 1, 1)
  }
}));

const GraphTabContent = ({ details }: TabProps): JSX.Element => {
  const { classes } = useStyles();

  const type = details?.type as ResourceType;
  const equalsService = equals(ResourceType.service);
  const equalsMetaService = equals(ResourceType.metaservice);
  const equalsAnomalyDetection = equals(ResourceType.anomalyDetection);

  const { loadDetails } = useLoadDetails();

  const isService =
    equalsService(type) ||
    equalsMetaService(type) ||
    equalsAnomalyDetection(type);

  const reload = (value: boolean): void => {
    if (!value) {
      return;
    }
    loadDetails();
  };

  const modalData = {
    data: details,
    renderGraph: ({
      interactWithGraph,
      graphHeight,
      renderAdditionalLines,
      filterLines
    }): JSX.Element => (
      <ExportablePerformanceGraphWithTimeline
        filterLines={filterLines}
        graphHeight={graphHeight}
        interactWithGraph={interactWithGraph}
        renderAdditionalLines={renderAdditionalLines}
        resource={details}
      />
    ),
    sendReloadGraphPerformance: reload,
    timePeriodGroup: <TimePeriodButtonGroup />
  };

  return (
    <div className={classes.container}>
      {isService ? (
        <>
          <TimePeriodButtonGroup />
          <ExportablePerformanceGraphWithTimeline
            interactWithGraph
            graphHeight={280}
            renderAdditionalGraphAction={
              <FederatedComponent
                displayModal
                modalData={modalData}
                path="/anomaly-detection/graph"
              />
            }
            renderAdditionalLines={({
              additionalLinesProps,
              resource
            }): JSX.Element => (
              <FederatedComponent
                displayAdditionalLines
                additionalLinesData={{ additionalLinesProps, resource }}
                path="/anomaly-detection/graph"
              />
            )}
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
  memoProps: ['details', 'ariaLabel']
});

const GraphTab = ({ details }: TabProps): JSX.Element => {
  return <MemoizedGraphTabContent details={details} />;
};

export default GraphTab;
