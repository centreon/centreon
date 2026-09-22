// @ts-nocheck
// TODO: re-enable type-check after fixing this file
import {
  DeleteOutlineOutlined as DeleteIcon,
  ToggleOffOutlined as DisableIcon,
  ContentCopyOutlined as DuplicateIcon,
  ToggleOnOutlined as EnableIcon
} from '@mui/icons-material';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { useAtomValue } from 'jotai';
import { isEmpty, pipe } from 'ramda';
import { useTranslation } from 'react-i18next';

import { configurationAtom } from '../../../atoms';
import {
  labelDelete,
  labelDisable,
  labelDuplicate,
  labelEnable
} from '../../../translatedLabels';
import { selectedRowsAtom } from '../../atoms';
import { useActionsStyles } from '../Actions.styles';
import useMassiveActions from './useMassiveActions';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useActionsStyles({});

  const { openDeleteModal, openDuplicateModal, enable, disable, isMutating } =
    useMassiveActions();

  const selectedRows = useAtomValue(selectedRowsAtom);
  const configuration = useAtomValue(configurationAtom);
  const massiveActions = configuration?.actions?.massiveActions ?? [];

  const extraActions = massiveActions.flatMap(
    ({ Icon, dataTestId, label, onClick }) => [
      ActionsListActionDivider.divider,
      {
        'data-testid': dataTestId,
        disable: isMutating,
        Icon,
        label: t(label),
        onClick: pipe(() => onClick(selectedRows), close)
      }
    ]
  );

  return (
    <Menu anchorEl={anchor} onClose={close} open={Boolean(anchor)}>
      <ActionsList
        actions={[
          {
            disable: isMutating,
            Icon: DuplicateIcon,
            label: t(labelDuplicate),
            onClick: pipe(openDuplicateModal, close)
          },
          ActionsListActionDivider.divider,
          {
            disable: isMutating,
            Icon: EnableIcon,
            label: t(labelEnable),
            onClick: pipe(enable, close),
            variant: 'success'
          },
          ActionsListActionDivider.divider,
          {
            disable: isMutating,
            Icon: DisableIcon,
            label: t(labelDisable),
            onClick: pipe(disable, close),
            variant: 'error'
          },
          ActionsListActionDivider.divider,
          {
            disable: isMutating,
            Icon: DeleteIcon,
            label: t(labelDelete),
            onClick: pipe(openDeleteModal, close),
            variant: 'error'
          },
          ...(isEmpty(extraActions) ? [] : extraActions)
        ]}
        className={classes.ActionsList}
      />
    </Menu>
  );
};

export default MoreActions;
