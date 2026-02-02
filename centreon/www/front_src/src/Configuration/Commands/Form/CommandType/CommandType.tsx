import { FormControlLabel, Radio, RadioGroup } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { equals } from 'ramda';
import { ChangeEvent, ReactElement, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { Command } from '../../models';
import {
  labelCheck,
  labelDiscovery,
  labelMiscellaneous,
  labelNotification,
  labelYouAreNotAllowed
} from '../../translatedLabels';
import { useUserPermissions } from '../../useUserPermissions';

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
        canEdit: canEditNotificationCommands,
        id: 'Notification',
        name: labelNotification
      },
      {
        canEdit: canEditCheckCommands,
        id: 'Check',
        name: labelCheck
      },
      {
        canEdit: canEditMiscellaneousCommands,
        id: 'Miscellaneous',
        name: labelMiscellaneous
      },
      {
        canEdit: canEditDiscoveryCommands,
        id: 'Discovery',
        name: labelDiscovery
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
    <RadioGroup onChange={change} row value={value}>
      {options.map(({ id, name, canEdit }) => (
        <Tooltip key={id} label={!canEdit && t(labelYouAreNotAllowed)}>
          <FormControlLabel
            aria-label={t(name)}
            checked={equals(id, value)}
            control={<Radio />}
            disabled={!canEdit || values.isFromMonitoringConnector}
            label={t(name)}
            name={name}
            value={id}
          />
        </Tooltip>
      ))}
    </RadioGroup>
  );
};

export default CommandType;
