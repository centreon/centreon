import { type Parameters, TimePeriods } from '@centreon/ui';

import { useAtom } from 'jotai';
import { equals } from 'ramda';
import { useState } from 'react';

import GraphOptions from '../../../Graph/Performance/ExportableGraphWithTimeline/GraphOptions';
import memoizeComponent from '../../../memoizedComponent';
import { ResourceType } from '../../../models';
import type { TabProps } from '..';
import { updatedGraphIntervalAtom } from './atoms';
import ChartGraph from './ChartGraph';
import { useChartGraphStyles } from './chartGraph.styles';
import HostGraph from './HostGraph';

const GraphTabContent = ({ details }: TabProps): JSX.Element => {
  const { classes } = useChartGraphStyles();

  const [updatedGraphInterval, setUpdatedGraphInterval] = useAtom(
    updatedGraphIntervalAtom
  );

  const [graphTimeParameters, setGraphTimeParameters] = useState<Parameters>();

  const type = details?.type as ResourceType;
  const equalsService = equals(ResourceType.service);
  const equalsMetaService = equals(ResourceType.metaservice);
  const equalsAnomalyDetection = equals(ResourceType.anomalyDetection);

  const isService =
    equalsService(type) ||
    equalsMetaService(type) ||
    equalsAnomalyDetection(type);

  const getTimePeriodsParameters = (data: Parameters): void => {
    setGraphTimeParameters(data);
  };

  return (
    <div className={classes.graphTabContainer}>
      {isService ? (
        <>
          <TimePeriods
            adjustTimePeriodData={updatedGraphInterval}
            getParameters={getTimePeriodsParameters}
            renderExternalComponent={<GraphOptions />}
          />

          <ChartGraph
            graphTimeParameters={graphTimeParameters}
            resource={details}
            updatedGraphInterval={setUpdatedGraphInterval}
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
  memoProps: ['details']
});

const GraphTab = ({ details }: TabProps): JSX.Element => {
  return <MemoizedGraphTabContent details={details} />;
};

export default GraphTab;
