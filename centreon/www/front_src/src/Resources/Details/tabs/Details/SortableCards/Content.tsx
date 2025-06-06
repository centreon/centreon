import { RefObject } from 'react';

import { DraggableSyntheticListeners } from '@dnd-kit/core';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import MoreVertIcon from '@mui/icons-material/MoreVert';
import { GridSize, Paper } from '@mui/material';
import Grid from '@mui/material/Grid';

import DetailsCard from '../DetailsCard';

import { CardsLayout } from './models';

const useStyles = makeStyles<{ isDragging: boolean }>()(
  (theme, { isDragging }) => ({
    handler: {
      alignItems: 'center',
      cursor: isDragging ? 'grabbing' : 'grab',
      display: 'flex',
      height: '100%'
    },
    paper: {
      height: '100%'
    },
    tile: {
      '&:hover': {
        boxShadow: theme.shadows[3]
      },
      display: 'grid',
      gridTemplateColumns: 'min-content auto',
      height: '100%'
    }
  })
);

interface ContentProps extends CardsLayout {
  attributes;
  isDragging: boolean;
  itemRef: RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  style;
}

const Content = ({
  listeners,
  isDragging,
  attributes,
  style,
  itemRef,
  title,
  line,
  xs,
  isCustomCard,
  width
}: ContentProps): JSX.Element => {
  const { classes } = useStyles({ isDragging });
  const { t } = useTranslation();

  const getVariableXs = (): GridSize => {
    const variableXs = isNil(xs) ? 6 : xs;

    return (width > 950 ? variableXs / 2 : variableXs) as GridSize;
  };

  return (
    <Grid
      item
      key={title}
      xs={getVariableXs()}
      {...attributes}
      ref={itemRef}
      style={style}
      size={xs || 6}
    >
      <Paper className={classes.paper}>
        <div className={classes.tile}>
          <div {...listeners} className={classes.handler}>
            <MoreVertIcon fontSize="small" />
          </div>
          <DetailsCard
            isCustomCard={isCustomCard}
            line={line}
            title={t(title)}
          />
        </div>
      </Paper>
    </Grid>
  );
};

export default Content;
