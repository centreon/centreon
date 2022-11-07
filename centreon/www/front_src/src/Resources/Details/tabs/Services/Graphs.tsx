<<<<<<< HEAD
import { RefObject } from 'react';

import { path, isNil, equals, last, pipe, not } from 'ramda';

import makeStyles from '@mui/styles/makeStyles';

import { Resource } from '../../../models';
import ExportableGraphWithTimeline from '../../../Graph/Performance/ExportableGraphWithTimeline';
import { MousePosition } from '../../../Graph/Performance/Graph/mouseTimeValueAtoms';

interface Props {
  infiniteScrollTriggerRef: RefObject<HTMLDivElement>;
=======
import * as React from 'react';

import { path, isNil, equals, last, pipe, not } from 'ramda';

import { makeStyles } from '@material-ui/core';

import { Resource } from '../../../models';
import ExportablePerformanceGraphWithTimeline from '../../../Graph/Performance/ExportableGraphWithTimeline';
import { MousePosition } from '../../../Graph/Performance/Graph/useMetricsValue';

const MemoizedPerformanceGraph = React.memo(
  ExportablePerformanceGraphWithTimeline,
  (prevProps, nextProps) => {
    const prevResource = prevProps.resource;
    const nextResource = nextProps.resource;

    return (
      equals(prevResource?.id, nextResource?.id) &&
      equals(
        prevProps.resourceGraphMousePosition,
        nextProps.resourceGraphMousePosition,
      )
    );
  },
);

interface Props {
  infiniteScrollTriggerRef: React.RefObject<HTMLDivElement>;
>>>>>>> centreon/dev-21.10.x
  services: Array<Resource>;
}

export interface ResourceGraphMousePosition {
  mousePosition: MousePosition;
  resourceId: string | number;
}

const useStyles = makeStyles((theme) => ({
  graph: {
    columnGap: '8px',
    display: 'grid',
    gridTemplateColumns: `repeat(auto-fill, minmax(${theme.spacing(
      40,
<<<<<<< HEAD
    )}, auto))`,
=======
    )}px, auto))`,
>>>>>>> centreon/dev-21.10.x
    rowGap: '8px',
  },
}));

const ServiceGraphs = ({
  services,
  infiniteScrollTriggerRef,
}: Props): JSX.Element => {
  const classes = useStyles();
<<<<<<< HEAD
=======
  const [resourceGraphMousePosition, setResourceGraphMousePosition] =
    React.useState<ResourceGraphMousePosition | null>(null);
>>>>>>> centreon/dev-21.10.x

  const servicesWithGraph = services.filter(
    pipe(path(['links', 'endpoints', 'performance_graph']), isNil, not),
  );

  return (
    <div className={classes.graph}>
      {servicesWithGraph.map((service) => {
        const { id } = service;
        const isLastService = equals(last(servicesWithGraph), service);

        return (
          <div key={id}>
<<<<<<< HEAD
            <ExportableGraphWithTimeline
              limitLegendRows
              graphHeight={120}
              resource={service}
=======
            <MemoizedPerformanceGraph
              limitLegendRows
              graphHeight={120}
              resource={service}
              resourceGraphMousePosition={resourceGraphMousePosition}
              updateResourceGraphMousePosition={setResourceGraphMousePosition}
>>>>>>> centreon/dev-21.10.x
            />
            {isLastService && <div ref={infiniteScrollTriggerRef} />}
          </div>
        );
      })}
    </div>
  );
};

export default ServiceGraphs;
