import * as React from 'react';

import clsx from 'clsx';
import { DraggableSyntheticListeners } from '@dnd-kit/core';

import { Chip, lighten, makeStyles } from '@material-ui/core';
import CloseIcon from '@material-ui/icons/Close';

import { ItemProps } from './SortableItem';

const useStyles = makeStyles((theme) => ({
  createdTag: {
    backgroundColor: lighten(theme.palette.primary.main, 0.7),
  },
  tag: {
    margin: theme.spacing(0.5),
  },
}));

interface Props extends Omit<ItemProps, 'id'> {
  chipStyle?: React.CSSProperties;
  listeners?: DraggableSyntheticListeners;
  style?: React.CSSProperties;
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
      <div ref={ref} style={style}>
        <Chip
          clickable
          className={clsx(classes.tag, createOption && classes.createdTag)}
          deleteIcon={<CloseIcon />}
          label={
            <p {...props} {...listeners}>
              {name}
            </p>
          }
          size="small"
          style={chipStyle}
          onDelete={(): void => deleteValue(index)}
        />
      </div>
    );
  },
);

export default Item;
