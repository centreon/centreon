import { FormControlLabel, Radio, RadioGroup } from '@mui/material';

import { Tooltip } from '@centreon/ui/components';

import { useFormikContext } from 'formik';
import { equals, values as ramdaValues } from 'ramda';
import { ChangeEvent, ReactElement, useMemo } from 'react';
import { useTranslation } from 'react-i18next';

import { Command, CommandType } from '../../models';
import {
  labelCheck,
  labelDiscovery,
  labelMiscellaneous,
  labelNotification,
  labelYouAreNotAllowed
} from '../../translatedLabels';
import { useUserPermissions } from '../../useUserPermissions';

const Type = (): ReactElement => {
  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } =
    useFormikContext<Command>();

  const value = values?.type;

  const { editorPermissions } = useUserPermissions();

  const options = useMemo(
    () => [
      {
        canEdit: editorPermissions[CommandType.Notification],
        id: 'Notification',
        name: labelNotification
      },
      {
        canEdit: editorPermissions[CommandType.Check],
        id: 'Check',
        name: labelCheck
      },
      {
        canEdit: editorPermissions[CommandType.Miscellaneous],
        id: 'Miscellaneous',
        name: labelMiscellaneous
      },
      {
        canEdit: editorPermissions[CommandType.Discovery],
        id: 'Discovery',
        name: labelDiscovery
      }
    ],
    ramdaValues(editorPermissions)
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

export default Type;
