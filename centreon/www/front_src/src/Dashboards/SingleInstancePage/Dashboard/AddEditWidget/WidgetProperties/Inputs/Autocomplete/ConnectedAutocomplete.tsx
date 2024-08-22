import { useMemo } from 'react';

import { useTranslation } from 'react-i18next';

import { Typography } from '@mui/material';

import {
  MultiConnectedAutocompleteField,
  SingleConnectedAutocompleteField
} from '@centreon/ui';

import Subtitle from '../../../../components/Subtitle';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import { WidgetPropertyProps } from '../../../models';

import { useStyles } from './ConnectedAutocomplete.styles';
import useAutocomplete from './useConnectedAutocomplete';

const ConnectedAutocomplete = ({
  propertyName,
  label,
  secondaryLabel,
  isSingleAutocomplete,
  baseEndpoint,
  isInGroup
}: WidgetPropertyProps): JSX.Element => {
  const { classes } = useStyles();
  const { t } = useTranslation();

  const { canEditField } = useCanEditProperties();

  const { changeValue, changeValues, deleteItem, getEndpoint, value } =
    useAutocomplete({
      baseEndpoint,
      propertyName
    });

  const Label = useMemo(() => (isInGroup ? Typography : Subtitle), [isInGroup]);

  return (
    <div className={classes.container}>
      <Label>{t(label)}</Label>

      {isSingleAutocomplete ? (
        <SingleConnectedAutocompleteField
          chipProps={{
            color: 'primary'
          }}
          disableClearable={false}
          disabled={!canEditField}
          getEndpoint={getEndpoint}
          label={t(secondaryLabel)}
          limitTags={2}
          value={value}
          onChange={changeValue}
        />
      ) : (
        <MultiConnectedAutocompleteField
          chipProps={{
            color: 'primary',
            onDelete: deleteItem
          }}
          disabled={!canEditField}
          field={undefined}
          getEndpoint={getEndpoint}
          label={t(secondaryLabel)}
          limitTags={2}
          placeholder=""
          value={value}
          onChange={changeValues}
        />
      )}
    </div>
  );
};

export default ConnectedAutocomplete;
