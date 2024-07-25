import { string, object, number, array } from 'yup';
import { useTranslation } from 'react-i18next';

import {
  labelAtLeastOneVCenterIsRequired,
  labelAteastOnePollerIsRequired,
  labelCharacters,
  labelDescription,
  labelInvalidPortNumber,
  labelMustBeAtLeast,
  labelMustBeAvalidURL,
  labelMustBeMost,
  labelName,
  labelPassword,
  labelRequired,
  labelUrlIsRequired
} from '../../translatedLabels';

const useValidationSchema = (): { validationSchema } => {
  const { t } = useTranslation();

  const urlValidationSchema = string()
    .required(t(labelUrlIsRequired))
    .matches(
      /^(https?:\/\/)?((([a-zA-Z\d]([a-zA-Z\d-]*[a-zA-Z\d])*)\.)+[a-zA-Z]{2,}|((\d{1,3}\.){3}\d{1,3})|(\[([a-fA-F\d:]+)\]))(:\d+)?(\/[-a-zA-Z\d%_.~+]*)*(\?[;&a-zA-Z\d%_.~+=-]*)?(#[-a-zA-Z\d_]*)?$/i,
      t(labelMustBeAvalidURL)
    );

  const selectEntryValidationSchema = object().shape({
    id: number().required(t(labelRequired)),
    name: string().required(t(labelRequired))
  });

  const vcenterSchema = object().shape({
    Password: string().label(t(labelPassword)).required(t(labelRequired)),
    Url: urlValidationSchema,
    Username: string().required(t(labelRequired)),
    'Vcenter name': string().required(t(labelRequired))
  });

  const parametersSchema = object().shape({
    port: number()
      .required(t(labelRequired))
      .integer(t(labelInvalidPortNumber))
      .min(0, t(labelInvalidPortNumber))
      .max(65535, t(labelInvalidPortNumber)),
    vcenters: array()
      .of(vcenterSchema)
      .min(1, t(labelAtLeastOneVCenterIsRequired))
  });

  const validationSchema = object({
    description: string()
      .label(t(labelDescription) || '')
      .max(
        180,
        (p) => `${p.label} ${t(labelMustBeMost)} ${p.max} ${t(labelCharacters)}`
      )
      .nullable(),
    name: string()
      .label(t(labelName))
      .min(3, ({ min, label }) => t(labelMustBeAtLeast, { label, min }))
      .max(50, ({ max, label }) => t(labelMustBeMost, { label, max }))
      .required(t(labelRequired)),
    parameters: parametersSchema,
    pollers: array()
      .of(selectEntryValidationSchema)
      .min(1, t(labelAteastOnePollerIsRequired)),
    type: number().required(t(labelRequired))
  });

  return {
    validationSchema
  };
};

export default useValidationSchema;
