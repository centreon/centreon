import { ReactNode } from 'react';

import { DraggableSyntheticListeners } from '@dnd-kit/core';

import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import KrilinIndicatorIcon from '@mui/icons-material/DragIndicator';

import { IconButton } from '../../../components';

import { useListStyles } from './List.styles';

export interface ContentProps {
  attributes;
  children: ReactNode;
  deleteItem: (id: string) => () => void;
  id: string;
  isDragging: boolean;
  isInDragOverlay?: boolean;
  itemRef: React.RefObject<HTMLDivElement>;
  listeners: DraggableSyntheticListeners;
  name: string;
  style;
}

const Content = ({
  listeners,
  itemRef,
  attributes,
  style,
  isDragging,
  id,
  children,
  deleteItem
}: ContentProps): JSX.Element => {
  const { classes } = useListStyles();

  return (
    <div
      className={classes.content}
      ref={itemRef}
      {...attributes}
      style={style}
    >
      <IconButton
        data-dragging={isDragging}
        size="small"
        {...listeners}
        aria-label={`sort-${id}`}
        icon={<KrilinIndicatorIcon fontSize="small" />}
      />
      <div className={classes.innerContent}>{children}</div>
      <IconButton
        aria-label={`delete-${id}`}
        icon={<DeleteOutlineIcon color="error" fontSize="small" />}
        size="small"
        onClick={deleteItem(id)}
      />
    </div>
  );
};

export default Content;
