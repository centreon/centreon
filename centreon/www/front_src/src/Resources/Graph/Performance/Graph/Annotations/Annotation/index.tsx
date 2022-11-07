<<<<<<< HEAD
import { useTranslation } from 'react-i18next';
import { useUpdateAtom } from 'jotai/utils';

import { Tooltip, Paper, Typography } from '@mui/material';
import makeStyles from '@mui/styles/makeStyles';
=======
import * as React from 'react';

import { useTranslation } from 'react-i18next';

import { makeStyles, Tooltip, Paper, Typography } from '@material-ui/core';
>>>>>>> centreon/dev-21.10.x

import truncate from '../../../../../truncate';
import { TimelineEvent } from '../../../../../Details/tabs/Timeline/models';
import { labelBy } from '../../../../../translatedLabels';
<<<<<<< HEAD
import { annotationHoveredAtom } from '../../annotationsAtoms';
=======
>>>>>>> centreon/dev-21.10.x

const yMargin = -32;
const iconSize = 20;

const useStyles = makeStyles((theme) => ({
  tooltip: {
    backgroundColor: 'transparent',
  },
  tooltipContent: {
    padding: theme.spacing(1),
  },
}));

export interface Props {
  event: TimelineEvent;
  header: string;
  icon: JSX.Element;
  marker: JSX.Element;
<<<<<<< HEAD
  resourceId: string;
=======
  setAnnotationHovered: React.Dispatch<
    React.SetStateAction<TimelineEvent | undefined>
  >;
>>>>>>> centreon/dev-21.10.x
  xIcon: number;
}

const Annotation = ({
  icon,
  header,
  event,
  xIcon,
  marker,
<<<<<<< HEAD
  resourceId,
=======
  setAnnotationHovered,
>>>>>>> centreon/dev-21.10.x
}: Props): JSX.Element => {
  const classes = useStyles();
  const { t } = useTranslation();

<<<<<<< HEAD
  const setAnnotationHovered = useUpdateAtom(annotationHoveredAtom);

=======
>>>>>>> centreon/dev-21.10.x
  const content = `${truncate(event.content)} (${t(labelBy)} ${
    event.contact?.name
  })`;

  return (
    <g>
      <Tooltip
        classes={{ tooltip: classes.tooltip }}
        title={
          <Paper className={classes.tooltipContent}>
            <Typography variant="body2">{header}</Typography>
            <Typography variant="caption">{content}</Typography>
          </Paper>
        }
      >
        <svg
          height={iconSize}
          width={iconSize}
          x={xIcon}
          y={yMargin}
<<<<<<< HEAD
          onMouseEnter={(): void =>
            setAnnotationHovered(() => ({ event, resourceId }))
          }
=======
          onMouseEnter={(): void => setAnnotationHovered(() => event)}
>>>>>>> centreon/dev-21.10.x
          onMouseLeave={(): void => setAnnotationHovered(() => undefined)}
        >
          <rect fill="transparent" height={iconSize} width={iconSize} />
          {icon}
        </svg>
      </Tooltip>
      {marker}
    </g>
  );
};

export default Annotation;
export { yMargin, iconSize };
