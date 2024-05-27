import { useState } from 'react';

import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Theme } from '@mui/material';

import { TimePeriods } from '@centreon/ui';

import { TabProps } from '..';
import FederatedComponent from '../../../../components/FederatedComponents';
import ExportablePerformanceGraphWithTimeline from '../../../Graph/Performance/ExportableGraphWithTimeline';
import GraphOptions from '../../../Graph/Performance/ExportableGraphWithTimeline/GraphOptions';
import { updatedGraphIntervalAtom } from '../../../Graph/Performance/ExportableGraphWithTimeline/atoms';
import PopoverCustomTimePeriodPickers from '../../../Graph/Performance/TimePeriods/PopoverCustomTimePeriodPicker';
import useLoadDetails from '../../../Listing/useLoadResources/useLoadDetails';
import memoizeComponent from '../../../memoizedComponent';
import { ResourceType } from '../../../models';

import HostGraph from './HostGraph';
import { GraphTimeParameters } from './models';

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

  const [graphTimeParameters, setGraphTimeParameters] =
    useState<GraphTimeParameters>();

  const type = details?.type as ResourceType;
  const equalsService = equals(ResourceType.service);
  const equalsMetaService = equals(ResourceType.metaservice);
  const equalsAnomalyDetection = equals(ResourceType.anomalyDetection);
  const updatedGraphInterval = useAtomValue(updatedGraphIntervalAtom);

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

  const getTimePeriodsParameters = (data: GraphTimeParameters): void => {
    setGraphTimeParameters(data);
  };

  const newGraphInterval = updatedGraphInterval
    ? { end: updatedGraphInterval.end, start: updatedGraphInterval.start }
    : undefined;

  const renderGraph = ({
    interactWithGraph,
    graphHeight,
    renderAdditionalLines,
    filterLines
  }): JSX.Element => {
    return (
      <div>
        {graphTimeParameters && (
          <ExportablePerformanceGraphWithTimeline
            filterLines={filterLines}
            graphHeight={graphHeight}
            graphTimeParameters={graphTimeParameters}
            interactWithGraph={interactWithGraph}
            renderAdditionalLines={renderAdditionalLines}
            resource={details}
          />
        )}
      </div>
    );
  };

  const modalData = {
    data: details,
    graphTimeParameters,
    renderGraph,
    renderTimePeriodPicker: PopoverCustomTimePeriodPickers,
    sendReloadGraphPerformance: reload,
    timePeriodGroup: (
      <TimePeriods
        adjustTimePeriodData={newGraphInterval}
        getParameters={getTimePeriodsParameters}
        renderExternalComponent={<GraphOptions />}
      />
    )
  };

  return (
    <div className={classes.container}>
      {isService ? (
        <>
          <TimePeriods
            adjustTimePeriodData={newGraphInterval}
            getParameters={getTimePeriodsParameters}
            renderExternalComponent={<GraphOptions />}
          />
          {graphTimeParameters && (
            <ExportablePerformanceGraphWithTimeline
              interactWithGraph
              graphHeight={280}
              graphTimeParameters={graphTimeParameters}
              renderAdditionalGraphAction={
                <FederatedComponent
                  modalData={modalData}
                  path="/anomaly-detection"
                  styleMenuSkeleton={{ height: 0, width: 0 }}
                />
              }
              renderAdditionalLines={({
                additionalLinesProps,
                resource
              }): JSX.Element => (
                <FederatedComponent
                  additionalLinesData={{ additionalLinesProps, resource }}
                  path="/anomaly-detection"
                />
              )}
              resource={details}
            />
          )}
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
