import { Grid } from '@mui/material';

import {
  ContentCopy as ContentCopyIcon,
  DeleteOutlined as DeleteIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';

import { IconButton } from '@centreon/ui';
import { useAtomValue, useSetAtom } from 'jotai';
import { isEmpty, map, pick } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import {
  hostGroupsToDeleteAtom,
  hostGroupsToDuplicateAtom,
  selectedRowsAtom
} from '../../atoms';
import {
  labelDelete,
  labelDuplicate,
  labelMoreActions
} from '../../translatedLabels';
import AddHostGroups from './Add';
import Filters from './Filters/SearchBar';
import MoreActions from './MoreActions';
import { useActionsStyles } from './useActionsStyles';

const ActionsBar = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles();

  const selectedRows = useAtomValue(selectedRowsAtom);
  const setHostGroupsToDelete = useSetAtom(hostGroupsToDeleteAtom);
  const setHostGroupsToDuplicate = useSetAtom(hostGroupsToDuplicateAtom);

  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const selectedRowsIds = selectedRows?.map((row) => row.id);
  const disabled = isEmpty(selectedRowsIds);

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const hostGroupEntities = map(pick(['id', 'name']), selectedRows);

  const openDeleteModal = (): void => setHostGroupsToDelete(hostGroupEntities);
  const openDuplicateModal = (): void =>
    setHostGroupsToDuplicate(hostGroupEntities);

  return (
    <Grid container className={classes.actions}>
      <Grid item flex={1}>
        <div className={classes.actions}>
          <AddHostGroups openCreateDialog={() => undefined} />
          <IconButton
            ariaLabel={t(labelDuplicate)}
            title={t(labelDuplicate)}
            onClick={openDuplicateModal}
            disabled={disabled}
          >
            <ContentCopyIcon className={classes.duplicateIcon} />
          </IconButton>
          <IconButton
            ariaLabel={t(labelDelete)}
            title={t(labelDelete)}
            onClick={openDeleteModal}
            className={classes.removeButton}
            disabled={disabled}
          >
            <DeleteIcon className={classes.removeIcon} />
          </IconButton>
          <IconButton
            ariaLabel={t(labelMoreActions)}
            title={t(labelMoreActions)}
            onClick={openMoreActions}
            disabled={disabled}
          >
            <MoreIcon className={classes.duplicateIcon} />
          </IconButton>
        </div>
      </Grid>
      <Grid item flex={2}>
        <Filters />
      </Grid>

      <MoreActions anchor={moreActionsOpen} close={closeMoreActions} />
    </Grid>
  );
};

export default ActionsBar;
