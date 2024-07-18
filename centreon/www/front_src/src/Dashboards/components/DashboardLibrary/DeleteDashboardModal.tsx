import { useMemo, useRef } from 'react';

import { useAtom, useAtomValue } from 'jotai';
import { useTranslation } from 'react-i18next';
import { has, isEmpty, isNil } from 'ramda';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';
import { LoadingSkeleton, useFetchQuery } from '@centreon/ui';

import { dashboardToDeleteAtom } from '../../atoms';
import { useDashboardDelete } from '../../hooks/useDashboardDelete';
import {
  labelCancel,
  labelDelete,
  labelDeleteDashboard,
  labelDescriptionDeleteDashboard,
  labelDescriptionDeleteDashboardPlaylists
} from '../../translatedLabels';
import { Dashboard } from '../../api/models';
import { playlistsByDashboardEndpoint } from '../../api/endpoints';
import { platformVersionsAtom } from '../../../Main/atoms/platformVersionsAtom';
import { playlistsByDashboardDecoder } from '../../api/decoders';

const DeleteDashboardModal = (): JSX.Element => {
  const dashboardRef = useRef('');

  const { t } = useTranslation();
  const [dashboardToDelete, setDashboardToDelete] = useAtom(
    dashboardToDeleteAtom
  );
  const platformVersions = useAtomValue(platformVersionsAtom);

  const hasDashbordToDelete = useMemo(
    () => !isNil(dashboardToDelete),
    [dashboardToDelete]
  );

  const isITEditionExtensionsInstalled = useMemo(() => {
    return has('centreon-it-edition-extensions', platformVersions?.modules);
  }, [platformVersions]);

  const { isFetching, data } = useFetchQuery({
    decoder: playlistsByDashboardDecoder,
    getEndpoint: () =>
      playlistsByDashboardEndpoint(dashboardToDelete?.id as number),
    getQueryKey: () => ['playlistsByDashboard', dashboardToDelete?.id],
    queryOptions: {
      enabled: isITEditionExtensionsInstalled && hasDashbordToDelete,
      suspense: false
    }
  });

  const deleteDashboard = useDashboardDelete();

  const confirm = (): void => {
    deleteDashboard(dashboardToDelete as Dashboard)();
    close();
  };

  const close = (): void => {
    setDashboardToDelete(null);
  };

  if (dashboardToDelete?.name) {
    dashboardRef.current = dashboardToDelete?.name;
  }

  const bodyMessage = useMemo(
    () =>
      !isFetching && !isNil(data) && !isEmpty(data)
        ? labelDescriptionDeleteDashboardPlaylists
        : labelDescriptionDeleteDashboard,
    [isFetching, data]
  );

  return (
    <Modal open={hasDashbordToDelete} size="large" onClose={close}>
      <Modal.Header>{t(labelDeleteDashboard)}</Modal.Header>
      <Modal.Body>
        {isFetching ? (
          <>
            <LoadingSkeleton variant="text" width="100%" />
            <LoadingSkeleton variant="text" width="40%" />
          </>
        ) : (
          <Typography>
            {t(bodyMessage, {
              name: dashboardRef.current
            })}
          </Typography>
        )}
      </Modal.Body>
      <Modal.Actions
        isDanger
        disabled={isFetching}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDelete)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default DeleteDashboardModal;
