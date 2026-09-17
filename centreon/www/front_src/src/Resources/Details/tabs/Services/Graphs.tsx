import type { Interval } from '@centreon/ui';

import { equals, isNil, last, not, path, pipe } from 'ramda';
import type { RefObject } from 'react';
import { makeStyles } from 'tss-react/mui';

import type { MousePosition } from '../../../Graph/Performance/Graph/mouseTimeValueAtoms';
import type { Resource } from '../../../models';
import ChartGraph from '../Graph/ChartGraph';
import type { GraphTimeParameters } from '../Graph/models';

interface Props {
  graphTimeParameters: GraphTimeParameters;
  infiniteScrollTriggerRef: RefObject<HTMLDivElement>;
  services: Array<Resource>;
  updateGraphInterval: (args: Interval) => void;
}

export interface ResourceGraphMousePosition {
  mousePosition: MousePosition;
  resourceId: string | number;
}

const useStyles = makeStyles()((theme) => ({
  graph: {
    columnGap: theme.spacing(1.5),
    display: 'grid',
    gridTemplateColumns: `repeat(auto-fit, minmax(${theme.spacing(40)}, 1fr))`,
    rowGap: theme.spacing(1.5)
  }
}));

const ServiceGraphs = ({
  services,
  infiniteScrollTriggerRef,
  graphTimeParameters,
  updateGraphInterval
}: Props): JSX.Element => {
  const { classes } = useStyles();

  const servicesWithGraph = services.filter(
    pipe(path(['links', 'endpoints', 'performance_graph']), isNil, not)
  );

  return (
    <div className={classes.graph}>
      {servicesWithGraph.map((service) => {
        const { id } = service;
        const isLastService = equals(last(servicesWithGraph), service);

        return (
          <div key={id}>
            <ChartGraph
              graphTimeParameters={graphTimeParameters}
              resource={service}
              updatedGraphInterval={updateGraphInterval}
            />
            {isLastService && <div ref={infiniteScrollTriggerRef} />}
          </div>
        );
      })}
    </div>
  );
};

export default ServiceGraphs;
