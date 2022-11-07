<<<<<<< HEAD
import { Shape } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { max, pick, prop } from 'ramda';
import { useAtom } from 'jotai';
import { useAtomValue } from 'jotai/utils';

import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { Shape } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { max, prop } from 'ramda';

import { makeStyles } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import { useLocaleDateTimeFormat, useMemoComponent } from '@centreon/ui';

import { labelFrom, labelTo } from '../../../../../translatedLabels';
<<<<<<< HEAD
import {
  annotationHoveredAtom,
  getFillColorDerivedAtom,
  getIconColorDerivedAtom,
} from '../../annotationsAtoms';
=======
import useAnnotationsContext from '../../Context';
>>>>>>> centreon/dev-21.10.x

import Annotation, { Props as AnnotationProps, yMargin, iconSize } from '.';

type Props = {
  Icon: (props) => JSX.Element;
  ariaLabel: string;
  color: string;
  endDate: string;
  graphHeight: number;
  startDate: string;
  xScale: ScaleTime<number, number>;
<<<<<<< HEAD
} & Omit<AnnotationProps, 'marker' | 'xIcon' | 'header' | 'icon'>;
=======
} & Omit<
  AnnotationProps,
  'marker' | 'xIcon' | 'header' | 'icon' | 'setAnnotationHovered'
>;
>>>>>>> centreon/dev-21.10.x

const useStyles = makeStyles((theme) => ({
  icon: {
    transition: theme.transitions.create('color', {
      duration: theme.transitions.duration.shortest,
    }),
  },
}));

const AreaAnnotation = ({
  Icon,
  ariaLabel,
  color,
  graphHeight,
  xScale,
  startDate,
  endDate,
  ...props
}: Props): JSX.Element => {
  const { toDateTime } = useLocaleDateTimeFormat();

  const classes = useStyles();

<<<<<<< HEAD
  const [annotationHovered, setAnnotationHovered] = useAtom(
    annotationHoveredAtom,
  );
  const getFillColor = useAtomValue(getFillColorDerivedAtom);
  const getIconColor = useAtomValue(getIconColorDerivedAtom);
=======
  const { annotationHovered, setAnnotationHovered, getFill, getIconColor } =
    useAnnotationsContext();
>>>>>>> centreon/dev-21.10.x

  const xIconMargin = -iconSize / 2;

  const xStart = max(xScale(new Date(startDate)), 0);
  const xEnd = endDate ? xScale(new Date(endDate)) : xScale.range()[1];

<<<<<<< HEAD
  const annotation = pick(['event', 'resourceId'], props);

  const area = (
    <Shape.Bar
      fill={getFillColor({ annotation, color })}
=======
  const area = (
    <Shape.Bar
      fill={getFill({ color, event: prop('event', props) })}
>>>>>>> centreon/dev-21.10.x
      height={graphHeight + iconSize / 2}
      width={xEnd - xStart}
      x={xStart}
      y={yMargin + iconSize + 2}
      onMouseEnter={(): void =>
<<<<<<< HEAD
        setAnnotationHovered(() => ({
          annotation,
          resourceId: prop('resourceId', props),
        }))
=======
        setAnnotationHovered(() => prop('event', props))
>>>>>>> centreon/dev-21.10.x
      }
      onMouseLeave={(): void => setAnnotationHovered(() => undefined)}
    />
  );

  const from = `${labelFrom} ${toDateTime(startDate)}`;
  const to = endDate ? ` ${labelTo} ${toDateTime(endDate)}` : '';

  const header = `${from}${to}`;

  const icon = (
    <Icon
      aria-label={ariaLabel}
      className={classes.icon}
      height={iconSize}
      style={{
        color: getIconColor({
<<<<<<< HEAD
          annotation,
          color,
=======
          color,
          event: prop('event', props),
>>>>>>> centreon/dev-21.10.x
        }),
      }}
      width={iconSize}
    />
  );

  return useMemoComponent({
    Component: (
      <Annotation
        header={header}
        icon={icon}
        marker={area}
<<<<<<< HEAD
=======
        setAnnotationHovered={setAnnotationHovered}
>>>>>>> centreon/dev-21.10.x
        xIcon={xStart + (xEnd - xStart) / 2 + xIconMargin}
        {...props}
      />
    ),
    memoProps: [annotationHovered, xStart, xEnd],
  });
};

export default AreaAnnotation;
