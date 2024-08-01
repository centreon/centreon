import { string, object, number, array } from 'yup';
import { useTranslation } from 'react-i18next';
import { equals } from 'ramda';

import {
  labelAtLeastOneVCenterIsRequired,
  labelAteastOnePollerIsRequired,
  labelDescription,
  labelDescriptionMustBeMost,
  labelInvalidPortNumber,
  labelMustBeAvalidURL,
  labelName,
  labelNameMustBeAtLeast,
  labelNameMustBeMost,
  labelRequired,
  labelVcenterNameMustBeUnique
} from '../../translatedLabels';

interface Props {
  variant: 'create' | 'update';
}
const useValidationSchema = ({ variant }: Props): { validationSchema } => {
  const { t } = useTranslation();

  const urlValidationSchema = string()
    .required(t(labelRequired))
    .matches(
      /^(https?:\/\/)?((([a-zA-Z\d]([a-zA-Z\d-]*[a-zA-Z\d])*)\.)+[a-zA-Z]{2,}|((\d{1,3}\.){3}\d{1,3})|(\[([a-fA-F\d:]+)\]))(:\d+)?(\/[-a-zA-Z\d%_.~+]*)*(\?[;&a-zA-Z\d%_.~+=-]*)?(#[-a-zA-Z\d_]*)?$/i,
      t(labelMustBeAvalidURL)
    );

  const selectEntryValidationSchema = object().shape({
    id: number().required(t(labelRequired)),
    name: string().required(t(labelRequired))
  });

  const secretsSchema = {
    Password: string().required(t(labelRequired)),
    Username: string().required(t(labelRequired))
  };

  const vcenterSchema = object().shape({
    ...(equals(variant, 'create') && secretsSchema),
    URL: urlValidationSchema,
    'vCenter name': string()
      .required(t(labelRequired))
      .test(
        'unique-vcenter-name',
        t(labelVcenterNameMustBeUnique),
        (value, context) => {
          if (!value) return true;

          const { options } = context;

          const vcenters = options.context?.parameters.vcenters || [];

          const duplicate =
            vcenters.filter((vcenter) => vcenter['vCenter name'] === value)
              .length > 1;

          return !duplicate;
        }
      )
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
      .max(180, t(labelDescriptionMustBeMost))
      .nullable(),
    name: string()
      .label(t(labelName))
      .min(3, t(labelNameMustBeAtLeast))
      .max(50, t(labelNameMustBeMost))
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
