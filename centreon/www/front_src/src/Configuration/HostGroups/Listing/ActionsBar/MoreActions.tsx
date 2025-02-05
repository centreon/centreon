import { pipe } from 'ramda';
import { useTranslation } from 'react-i18next';

import { ToggleOffRounded, ToggleOnRounded } from '@mui/icons-material';
import { Menu } from '@mui/material';

import { ActionsList, ActionsListActionDivider } from '@centreon/ui';

import { labelDisable, labelEnable } from '../../translatedLabels';

import { useAtomValue, useSetAtom } from 'jotai';
import {
  hostGroupsToDeleteAtom,
  hostGroupsToDuplicateAtom,
  selectedRowsAtom
} from '../../atoms';

interface Props {
  anchor: HTMLElement | null;
  close: () => void;
}

const MoreActions = ({ close, anchor }: Props): JSX.Element => {
  const { t } = useTranslation();

  const selectedRows = useAtomValue(selectedRowsAtom);

  const seDashboardToDuplicate = useSetAtom(hostGroupsToDuplicateAtom);
  const seDashboardToDelete = useSetAtom(hostGroupsToDeleteAtom);

  const openDuplicateModal = (): void => seDashboardToDuplicate(selectedRows);

  const openDeleteModal = (): void => seDashboardToDelete(selectedRows);

  return (
    <Menu anchorEl={anchor} open={Boolean(anchor)} onClose={close}>
      <ActionsList
        actions={[
          {
            Icon: ToggleOnRounded,
            label: t(labelEnable),
            onClick: pipe(openDuplicateModal, close),
            variant: 'success'
          },
          ActionsListActionDivider.divider,
          {
            Icon: ToggleOffRounded,
            label: t(labelDisable),
            onClick: pipe(openDeleteModal, close)
          }
        ]}
      />
    </Menu>
  );
};

export default MoreActions;
