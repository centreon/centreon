import { DraggableSyntheticListeners } from '@dnd-kit/core';
import { useFormikContext } from 'formik';
import { equals, reject } from 'ramda';

import KrilinIndicatorIcon from '@mui/icons-material/DragIndicator';
import DeleteOutlineIcon from '@mui/icons-material/DeleteOutline';
import { Typography } from '@mui/material';

import { IconButton } from '@centreon/ui/components';

import { Dashboard, PlaylistConfig } from '../../models';

import { useDashboardSortStyles } from './DashboardSort.styles';

interface ContentProps {
  attributes;
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
  name,
  itemRef,
  attributes,
  style,
  isDragging,
  id
}: ContentProps): JSX.Element => {
  const { classes } = useDashboardSortStyles();
  const { setFieldValue, values } = useFormikContext<PlaylistConfig>();

  const deleteDashboard = (): void => {
    const newDashboards = reject<Dashboard>((dashboard) =>
      equals(Number(id), dashboard.id)
    )(values.dashboards);

    setFieldValue('dashboards', newDashboards);
  };

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
        icon={<KrilinIndicatorIcon fontSize="small" />}
      />
      <Typography className={classes.name}>{name}</Typography>
      <IconButton
        icon={<DeleteOutlineIcon color="error" fontSize="small" />}
        size="small"
        onClick={deleteDashboard}
      />
    </div>
  );
};

export default Content;
