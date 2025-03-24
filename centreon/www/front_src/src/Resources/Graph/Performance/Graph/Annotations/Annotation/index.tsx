import { useSetAtom } from 'jotai';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import { Paper, Tooltip, Typography } from '@mui/material';

import { truncate } from '@centreon/ui';
import { TimelineEvent } from '../../../../../Details/tabs/Timeline/models';
import { labelBy } from '../../../../../translatedLabels';
import { annotationHoveredAtom } from '../../annotationsAtoms';

const yMargin = -32;
const iconSize = 20;

const useStyles = makeStyles()((theme) => ({
  tooltip: {
    backgroundColor: 'transparent'
  },
  tooltipContent: {
    padding: theme.spacing(1)
  }
}));

export interface Props {
  event: TimelineEvent;
  header: string;
  icon: JSX.Element;
  marker: JSX.Element;
  resourceId: string;
  xIcon: number;
}

const Annotation = ({
  icon,
  header,
  event,
  xIcon,
  marker,
  resourceId
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const setAnnotationHovered = useSetAtom(annotationHoveredAtom);

  const content = `${truncate({ content: event.content })} (${t(labelBy)} ${
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
          onMouseEnter={(): void =>
            setAnnotationHovered(() => ({ event, resourceId }))
          }
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
