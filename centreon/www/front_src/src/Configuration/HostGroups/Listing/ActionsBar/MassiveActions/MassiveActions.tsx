import {
  ContentCopy as ContentCopyIcon,
  DeleteOutlined as DeleteIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';

import { IconButton } from '@centreon/ui';
import { Button } from '@centreon/ui/components';
import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';
import {
  labelDelete,
  labelDuplicate,
  labelMoreActions
} from '../../../translatedLabels';
import { useActionsStyles } from '../Actions.styles';
import MoreActions from './MoreActions';
import useMassiveActions from './useMassiveActions';

const MassiveActions = (): JSX.Element => {
  const { t } = useTranslation();
  const { cx, classes } = useActionsStyles();

  const {
    openMoreActions,
    closeMoreActions,
    resetSelectedRows,
    openDeleteModal,
    openDuplicateModal,
    selectedRowsIds,
    moreActionsOpen
  } = useMassiveActions();

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
          disabled={isEmpty(selectedRowsIds)}
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
          disabled={isEmpty(selectedRowsIds)}
        >
          {t(labelDelete)}
        </Button>
      </div>
      <div className={cx(classes.actions, classes.iconButtons)}>
        <IconButton
          ariaLabel={t(labelDuplicate)}
          title={t(labelDuplicate)}
          onClick={openDuplicateModal}
          disabled={isEmpty(selectedRowsIds)}
        >
          <ContentCopyIcon className={classes.duplicateIcon} />
        </IconButton>
        <IconButton
          ariaLabel={t(labelDelete)}
          title={t(labelDelete)}
          onClick={openDeleteModal}
          className={classes.removeButton}
          disabled={isEmpty(selectedRowsIds)}
        >
          <DeleteIcon className={classes.removeIcon} />
        </IconButton>
      </div>

      <IconButton
        ariaLabel={t(labelMoreActions)}
        title={t(labelMoreActions)}
        onClick={openMoreActions}
        disabled={isEmpty(selectedRowsIds)}
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
