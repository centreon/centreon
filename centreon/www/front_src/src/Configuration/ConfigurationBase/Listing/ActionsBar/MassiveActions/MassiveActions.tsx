import { isEmpty } from 'ramda';
import { useTranslation } from 'react-i18next';

import {
  DeleteOutlineOutlined as DeleteIcon,
  ContentCopyOutlined as DuplicateIcon,
  MoreHoriz as MoreIcon
} from '@mui/icons-material';

import { IconButton } from '@centreon/ui';
import { Button } from '@centreon/ui/components';

import MoreActions from './MoreActions';
import useMassiveActions from './useMassiveActions';

import {
  labelDelete,
  labelDuplicate,
  labelMoreActions
} from '../../../translatedLabels';

import { useActionsStyles } from '../Actions.styles';

const MassiveActions = (): JSX.Element => {
  const { t } = useTranslation();
  const { cx, classes } = useActionsStyles();

  const {
    openMoreActions,
    closeMoreActions,
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
          data-testid={labelDuplicate}
          icon={<DuplicateIcon className={classes.duplicateIcon} />}
          iconVariant="start"
          size="medium"
          variant="secondary"
          onClick={openDuplicateModal}
          disabled={isEmpty(selectedRowsIds)}
        >
          {t(labelDuplicate)}
        </Button>

        <Button
          aria-label={t(labelDelete)}
          data-testid={labelDelete}
          icon={<DeleteIcon className={classes.removeIcon} />}
          iconVariant="start"
          size="medium"
          variant="secondary"
          onClick={openDeleteModal}
          disabled={isEmpty(selectedRowsIds)}
          isDanger
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
          <DuplicateIcon className={classes.duplicateIcon} />
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

      <MoreActions anchor={moreActionsOpen} close={closeMoreActions} />
    </div>
  );
};

export default MassiveActions;
