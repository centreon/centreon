import {
  Image,
  ImageVariant,
  LoadingSkeleton,
  SingleConnectedAutocompleteField
} from '@centreon/ui';

import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';

import { getListImagesSearchEndpoint } from '../api/endpoints';
import { labelIcon } from '../translatedLabels';
import { useIconStyles } from './Form.styles';

const IconFiled = ({ disabled }: { disabled: boolean }): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useIconStyles();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const changeIcon = (_, value): void => {
    setFieldValue('icon', value);
  };

  return (
    <div className={classes.icon}>
      <SingleConnectedAutocompleteField
        disableClearable={false}
        disabled={disabled}
        displayOptionThumbnail
        field="name"
        fullWidth
        getEndpoint={getListImagesSearchEndpoint}
        id="icon"
        label={t(labelIcon)}
        onChange={changeIcon}
        value={values.icon}
      />
      {values.icon && (
        <Image
          alt={values.icon.name}
          fallback={<LoadingSkeleton />}
          height={25}
          imagePath={values.icon.url}
          variant={ImageVariant.Contain}
          width={25}
        />
      )}
    </div>
  );
};

export default IconFiled;
