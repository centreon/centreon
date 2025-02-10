import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import { NumberField } from '@centreon/ui';
import { Modal } from '@centreon/ui/components';

import { equals, isEmpty } from 'ramda';
import {
  labelCancel,
  labelDuplicate,
  labelDuplicateResource,
  labelDuplicateResourceConfirmation,
  labelDuplicateResources,
  labelDuplicateResourcesConfirmation,
  labelDuplications
} from '../../../translatedLabels';
import useDuplicate from './useDuplicate';

import { useStyles } from './Duplicate.styles';

import { useAtomValue } from 'jotai';
import { configurationAtom } from '../../../../atoms';

const DuplicateDialog = (): JSX.Element => {
  const { classes } = useStyles();

  const { t } = useTranslation();

  const configuration = useAtomValue(configurationAtom);

  const resourceType = configuration?.resourceType?.toLowerCase();

  const {
    confirm,
    close,
    isMutating,
    duplicatesCount,
    changeDuplicateCount,
    hostGroupsToDuplicate,
    count,
    name
  } = useDuplicate({ resourceType });

  return (
    <Modal open={!isEmpty(hostGroupsToDuplicate)} size="large" onClose={close}>
      <Modal.Header>
        {t(
          equals(count, 1) ? labelDuplicateResource : labelDuplicateResources,
          { resourceType }
        )}
      </Modal.Header>
      <Modal.Body>
        <Typography
          dangerouslySetInnerHTML={{
            __html: equals(count, 1)
              ? t(labelDuplicateResourceConfirmation, { resourceType, name })
              : t(labelDuplicateResourcesConfirmation, { resourceType, count })
          }}
        />

        <div className={classes.duplicationCount}>
          <Typography className={classes.duplicationCountTitle}>
            {t(labelDuplications)}
          </Typography>
          <NumberField
            autoSize
            autoSizeDefaultWidth={20}
            dataTestId={labelDuplications}
            defaultValue={duplicatesCount}
            disabled={isMutating}
            fallbackValue={1}
            textFieldSlotsAndSlotProps={{
              slotProps: {
                htmlInput: {
                  'aria-label': t(labelDuplications),
                  min: 1,
                  max: 10
                }
              }
            }}
            size="compact"
            type="number"
            onChange={changeDuplicateCount}
          />
        </div>
      </Modal.Body>
      <Modal.Actions
        disabled={isMutating}
        labels={{
          cancel: t(labelCancel),
          confirm: t(labelDuplicate)
        }}
        onCancel={close}
        onConfirm={confirm}
      />
    </Modal>
  );
};

export default DuplicateDialog;
