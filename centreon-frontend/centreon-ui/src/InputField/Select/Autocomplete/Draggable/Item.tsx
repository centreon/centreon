import * as React from 'react';

import clsx from 'clsx';
import { DraggableSyntheticListeners } from '@dnd-kit/core';

import { Chip, lighten, makeStyles } from '@material-ui/core';
import CloseIcon from '@material-ui/icons/Close';

import { ItemProps } from './SortableItem';

const useStyles = makeStyles((theme) => ({
  tag: {
    margin: theme.spacing(0.5),
  },
  createdTag: {
    backgroundColor: lighten(theme.palette.primary.main, 0.7),
  },
}));

interface Props extends Omit<ItemProps, 'id'> {
  style?: React.CSSProperties;
  chipStyle?: React.CSSProperties;
  listeners?: DraggableSyntheticListeners;
}

const Item = React.forwardRef(
  (
    {
      name,
      createOption,
      deleteValue,
      index,
      style,
      chipStyle,
      listeners,
      ...props
    }: Props,
    ref: React.ForwardedRef<HTMLDivElement>,
  ): JSX.Element => {
    const classes = useStyles();

    return (
      <div style={style} ref={ref}>
        <Chip
          size="small"
          label={
            <p {...props} {...listeners}>
              {name}
            </p>
          }
          className={clsx(classes.tag, createOption && classes.createdTag)}
          clickable
          onDelete={() => deleteValue(index)}
          deleteIcon={<CloseIcon />}
          style={chipStyle}
        />
      </div>
    );
  },
);

export default Item;
