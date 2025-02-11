import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { Modal } from '@centreon/ui/components';
import { equals, isEmpty } from 'ramda';
import {
  labelCancel,
  labelDelete,
  labelDeleteResource,
  labelDeleteResourceConfirmation,
  labelDeleteResourcesConfirmation
} from '../../../translatedLabels';
import useDelete from './useDelete';

import { useAtomValue } from 'jotai';
import pluralize from 'pluralize';
import { configurationAtom } from '../../../../atoms';

const DeleteDialog = (): JSX.Element => {
  const { t } = useTranslation();

  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType as string;

  const { close, confirm, isMutating, hostGroupsToDelete, count, name } =
    useDelete({ resourceType });

  const labelResourceType = pluralize(resourceType, count);

  return (
    <Modal open={!isEmpty(hostGroupsToDelete)} size="large" onClose={close}>
      <Modal.Header>{t(labelDeleteResource(labelResourceType))}</Modal.Header>
      <Modal.Body>
        <Typography
          dangerouslySetInnerHTML={{
            __html: equals(count, 1)
              ? t(labelDeleteResourceConfirmation(labelResourceType), { name })
              : t(labelDeleteResourcesConfirmation(labelResourceType), {
                  count
                })
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
