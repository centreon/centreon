import {
  ContentCopy as ContentCopyIcon,
  DeleteOutlined as DeleteIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';

import { IconButton } from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { useAtom, useSetAtom } from 'jotai';
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
import { useActionsStyles } from './Actions.styles';
import MoreActions from './MoreActions';

const MassiveActions = (): JSX.Element => {
  const { t } = useTranslation();
  const { cx, classes } = useActionsStyles();

  const [selectedRows, setSelectedRows] = useAtom(selectedRowsAtom);
  const setHostGroupsToDelete = useSetAtom(hostGroupsToDeleteAtom);
  const setHostGroupsToDuplicate = useSetAtom(hostGroupsToDuplicateAtom);

  const [moreActionsOpen, setMoreActionsOpen] = useState(null);

  const selectedRowsIds = selectedRows?.map((row) => row.id);
  const resetSelectedRows = () => setSelectedRows([]);

  const disabled = isEmpty(selectedRowsIds);

  const openMoreActions = (event): void => setMoreActionsOpen(event.target);
  const closeMoreActions = (): void => setMoreActionsOpen(null);

  const hostGroupEntities = map(pick(['id', 'name']), selectedRows);

  const openDeleteModal = (): void => setHostGroupsToDelete(hostGroupEntities);
  const openDuplicateModal = (): void =>
    setHostGroupsToDuplicate(hostGroupEntities);

  return (
    <div className={classes.actions}>
      <div className={cx(classes.actions, classes.buttons)}>
        <Button
          aria-label={t(labelDuplicate)}
          data-testid="add-host-group"
          icon={<ContentCopyIcon />}
          iconVariant="start"
          size="medium"
          variant="primary"
          onClick={openDuplicateModal}
          disabled={disabled}
        >
          {t(labelDuplicate)}
        </Button>

        <Button
          aria-label={t(labelDelete)}
          data-testid="add-host-group"
          icon={<DeleteIcon />}
          iconVariant="start"
          size="medium"
          variant="primary"
          onClick={openDeleteModal}
          disabled={disabled}
        >
          {t(labelDelete)}
        </Button>
      </div>
      <div className={cx(classes.actions, classes.iconButtons)}>
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
      </div>

      <IconButton
        ariaLabel={t(labelMoreActions)}
        title={t(labelMoreActions)}
        onClick={openMoreActions}
        disabled={disabled}
      >
        <MoreIcon className={classes.duplicateIcon} />
      </IconButton>

      <MoreActions
        anchor={moreActionsOpen}
        close={closeMoreActions}
        resetSelectedRows={resetSelectedRows}
        selectedRowsIds={selectedRowsIds}
      />
    </div>
  );
};

export default MassiveActions;
