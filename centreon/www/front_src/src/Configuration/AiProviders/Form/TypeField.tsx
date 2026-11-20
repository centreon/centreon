import { Autocomplete, TextField } from '@mui/material';

import type { InputPropsWithoutGroup } from '@centreon/ui';

import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals, path } from 'ramda';
import { JSX } from 'react';
import { useTranslation } from 'react-i18next';

import { formStateAtom } from '../../ConfigurationBase/atoms';
import { AiProviderType } from '../models';
import { labelType, labelTypePlaceholder } from '../translatedLabels';
import { aiProviderTypeLabels, defaultModels } from '../utils';

const options = Object.values(AiProviderType);

const TypeField = ({ fieldName }: InputPropsWithoutGroup): JSX.Element => {
  const { t } = useTranslation();
  const { values, errors, touched, setValues, setFieldTouched } =
    useFormikContext();

  const { mode } = useAtomValue(formStateAtom);
  const isEditing = equals(mode, 'edit');

  const value = (path([fieldName], values) as AiProviderType | null) ?? null;
  const error = path([fieldName], touched)
    ? (path([fieldName], errors) as string | undefined)
    : undefined;

  const handleChange = (newType: AiProviderType | null): void => {
    setValues((currentValues) => {
      if (!newType) {
        return { ...currentValues, type: null };
      }

      // default the name and models for the provider type while the user has
      // not chosen them (empty, or still an auto-filled value of a previously
      // selected type)
      const typeLabels = Object.values(aiProviderTypeLabels).map((label) =>
        t(label)
      );
      const autoFilledModels = Object.values(defaultModels)
        .flatMap((models) => [models.defaultModel, models.fastModel])
        .filter(Boolean);

      const keepName =
        currentValues.name && !typeLabels.includes(currentValues.name);
      const keepDefaultModel =
        currentValues.defaultModel &&
        !autoFilledModels.includes(currentValues.defaultModel);
      const keepFastModel =
        currentValues.fastModel &&
        !autoFilledModels.includes(currentValues.fastModel);

      const models = defaultModels[newType];

      return {
        ...currentValues,
        defaultModel: keepDefaultModel
          ? currentValues.defaultModel
          : (models.defaultModel ?? ''),
        fastModel: keepFastModel ? currentValues.fastModel : models.fastModel,
        name: keepName ? currentValues.name : t(aiProviderTypeLabels[newType]),
        type: newType
      };
    });
  };

  return (
    <Autocomplete
      autoHighlight
      disableClearable={false}
      disabled={isEditing}
      getOptionLabel={(option) => t(aiProviderTypeLabels[option])}
      onBlur={() => setFieldTouched(fieldName, true, true)}
      onChange={(_event, newValue) => handleChange(newValue)}
      options={options}
      renderInput={(params) => (
        <TextField
          {...params}
          error={Boolean(error)}
          helperText={error}
          inputProps={{ ...params.inputProps, 'data-testid': t(labelType) }}
          label={t(labelType)}
          placeholder={t(labelTypePlaceholder)}
          required
          size="small"
        />
      )}
      sx={{ maxWidth: 320 }}
      value={value}
    />
  );
};

export default TypeField;
