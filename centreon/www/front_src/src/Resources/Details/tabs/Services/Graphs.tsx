import { RefObject } from 'react';

import { path, isNil, equals, last, pipe, not } from 'ramda';
import { makeStyles } from 'tss-react/mui';

import { Resource } from '../../../models';
import ExportableGraphWithTimeline from '../../../Graph/Performance/ExportableGraphWithTimeline';
import { MousePosition } from '../../../Graph/Performance/Graph/mouseTimeValueAtoms';
import { GraphTimeParameters } from '../Graph/models';

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
            <ExportableGraphWithTimeline
              interactWithGraph
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
