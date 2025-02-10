import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';
import { equals, isEmpty } from 'ramda';
import {
  labelCancel,
  labelDelete,
  labelDeleteResource,
  labelDeleteResourceConfirmation,
  labelDeleteResources,
  labelDeleteResourcesConfirmation
} from '../../../translatedLabels';
import useDelete from './useDelete';

import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../../../atoms';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType?.toLowerCase();

  const { close, confirm, isMutating, hostGroupsToDelete, count, name } =
    useDelete({ resourceType });

  return (
    <Modal open={!isEmpty(hostGroupsToDelete)} size="large" onClose={close}>
      <Modal.Header>
        {t(equals(count, 1) ? labelDeleteResource : labelDeleteResources, {
          resourceType
        })}
      </Modal.Header>
      <Modal.Body>
        <Typography
          dangerouslySetInnerHTML={{
            __html: equals(count, 1)
              ? t(labelDeleteResourceConfirmation, { resourceType, name })
              : t(labelDeleteResourcesConfirmation, { resourceType, count })
          }}
        />
      </Modal.Body>
      <Modal.Actions
        isDanger
        disabled={isMutating}
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

export default DeleteDialog;
