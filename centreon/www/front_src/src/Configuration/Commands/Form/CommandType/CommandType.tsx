import { ChangeEvent, ReactElement, useMemo } from 'react';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { useTranslation } from 'react-i18next';

import { FormControlLabel, Radio, RadioGroup } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';
import { Command } from '../../models';

import { useUserPermissions } from '../../useUserPermissions';

import {
  labelCheck,
  labelDiscovery,
  labelMiscellaneous,
  labelNotification,
  labelYouAreNotAllowed
} from '../../translatedLabels';

const CommandType = (): ReactElement => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<Command>();

  const value = values?.type;

  const {
    canEditCheckCommands,
    canEditNotificationCommands,
    canEditDiscoveryCommands,
    canEditMiscellaneousCommands
  } = useUserPermissions();

  const options = useMemo(
    () => [
      {
        id: 'Notification',
        name: labelNotification,
        canEdit: canEditNotificationCommands
      },
      {
        id: 'Check',
        name: labelCheck,
        canEdit: canEditCheckCommands
      },
      {
        id: 'Miscellaneous',
        name: labelMiscellaneous,
        canEdit: canEditMiscellaneousCommands
      },
      {
        id: 'Discovery',
        name: labelDiscovery,
        canEdit: canEditDiscoveryCommands
      }
    ],
    [
      canEditNotificationCommands,
      canEditCheckCommands,
      canEditMiscellaneousCommands,
      canEditDiscoveryCommands
    ]
  );

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched('type', true);
    setFieldValue('type', event.target.value);
  };

  return (
    <RadioGroup value={value} onChange={change} row>
      {options.map(({ id, name, canEdit }) => (
        <Tooltip key={id} label={!canEdit && t(labelYouAreNotAllowed)}>
          <FormControlLabel
            aria-label={t(name)}
            checked={equals(id, value)}
            control={<Radio />}
            disabled={!canEdit}
            label={t(name)}
            value={id}
            name={name}
          />
        </Tooltip>
      ))}
    </RadioGroup>
  );
};

export default CommandType;
