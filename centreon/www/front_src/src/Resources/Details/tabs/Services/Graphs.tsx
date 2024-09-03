import type { RefObject } from 'react';

import { path, equals, isNil, last, not, pipe } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import ExportableGraphWithTimeline from '../../../Graph/Performance/ExportableGraphWithTimeline';
import type { MousePosition } from '../../../Graph/Performance/Graph/mouseTimeValueAtoms';
import type { Resource } from '../../../models';
import type { GraphTimeParameters } from '../Graph/models';

interface Props {
  graphTimeParameters: GraphTimeParameters;
  infiniteScrollTriggerRef: RefObject<HTMLDivElement>;
  services: Array<Resource>;
}

export interface ResourceGraphMousePosition {
  mousePosition: MousePosition;
  resourceId: string | number;
}

const useStyles = makeStyles()((theme) => ({
  graph: {
    columnGap: '8px',
    display: 'grid',
    gridTemplateColumns: `repeat(auto-fill, minmax(${theme.spacing(
      40
    )}, auto))`,
    rowGap: '8px'
  }
}));

const ServiceGraphs = ({
  services,
  infiniteScrollTriggerRef,
  graphTimeParameters
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
            {/* a changer */}
            <ExportableGraphWithTimeline
              // interactWithGraph
              limitLegendRows
              graphHeight={120}
              graphTimeParameters={graphTimeParameters}
              resource={service}
            />
            {isLastService && <div ref={infiniteScrollTriggerRef} />}
          </div>
        );
      })}
    </div>
  );
};

export default ServiceGraphs;
