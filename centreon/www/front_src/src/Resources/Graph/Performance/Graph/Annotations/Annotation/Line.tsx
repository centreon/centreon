<<<<<<< HEAD
import { Shape } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { pick } from 'ramda';
import { useAtomValue } from 'jotai/utils';

import makeStyles from '@mui/styles/makeStyles';

import { useLocaleDateTimeFormat, useMemoComponent } from '@centreon/ui';

import {
  annotationHoveredAtom,
  getIconColorDerivedAtom,
  getStrokeOpacityDerivedAtom,
  getStrokeWidthDerivedAtom,
} from '../../annotationsAtoms';
=======
import * as React from 'react';

import { Shape } from '@visx/visx';
import { ScaleTime } from 'd3-scale';
import { prop } from 'ramda';

import { makeStyles } from '@material-ui/core';

import { useLocaleDateTimeFormat, useMemoComponent } from '@centreon/ui';

import useAnnotationsContext from '../../Context';
>>>>>>> centreon/dev-21.10.x

import Annotation, { Props as AnnotationProps, yMargin, iconSize } from '.';

type Props = {
  Icon: (props) => JSX.Element;
  ariaLabel: string;
  color: string;
  date: string;
  graphHeight: number;
  xScale: ScaleTime<number, number>;
} & Omit<
  AnnotationProps,
  'marker' | 'xIcon' | 'header' | 'icon' | 'setAnnotationHovered'
>;

const useStyles = makeStyles((theme) => ({
  icon: {
    transition: theme.transitions.create('color', {
      duration: theme.transitions.duration.shortest,
    }),
  },
}));

const LineAnnotation = ({
  color,
  graphHeight,
  xScale,
  date,
  Icon,
  ariaLabel,
  ...props
}: Props): JSX.Element => {
  const { toDateTime } = useLocaleDateTimeFormat();

  const classes = useStyles();

<<<<<<< HEAD
  const annotationHovered = useAtomValue(annotationHoveredAtom);
  const getStrokeWidth = useAtomValue(getStrokeWidthDerivedAtom);
  const getStrokeOpacity = useAtomValue(getStrokeOpacityDerivedAtom);
  const getIconColor = useAtomValue(getIconColorDerivedAtom);
=======
  const {
    annotationHovered,
    setAnnotationHovered,
    getStrokeWidth,
    getStrokeOpacity,
    getIconColor,
  } = useAnnotationsContext();
>>>>>>> centreon/dev-21.10.x

  const xIconMargin = -iconSize / 2;

  const xIcon = xScale(new Date(date));

  const header = toDateTime(date);

<<<<<<< HEAD
  const annotation = pick(['event', 'resourceId'], props);

=======
>>>>>>> centreon/dev-21.10.x
  const line = (
    <Shape.Line
      from={{ x: xIcon, y: yMargin + iconSize + 2 }}
      stroke={color}
<<<<<<< HEAD
      strokeOpacity={getStrokeOpacity(annotation)}
      strokeWidth={getStrokeWidth(annotation)}
=======
      strokeOpacity={getStrokeOpacity(prop('event', props))}
      strokeWidth={getStrokeWidth(prop('event', props))}
>>>>>>> centreon/dev-21.10.x
      to={{ x: xIcon, y: graphHeight }}
    />
  );

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
        marker={line}
<<<<<<< HEAD
=======
        setAnnotationHovered={setAnnotationHovered}
>>>>>>> centreon/dev-21.10.x
        xIcon={xIcon + xIconMargin}
        {...props}
      />
    ),
    memoProps: [annotationHovered, xIcon],
  });
};

export default LineAnnotation;
