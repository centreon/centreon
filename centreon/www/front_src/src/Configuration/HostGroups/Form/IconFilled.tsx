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
        fullWidth
        displayOptionThumbnail
        disableClearable={false}
        field="name"
        getEndpoint={getListImagesSearchEndpoint}
        id="icon"
        label={t(labelIcon)}
        value={values.icon}
        onChange={changeIcon}
        disabled={disabled}
      />
      {values.icon && (
        <Image
          alt={values.icon.name}
          fallback={<LoadingSkeleton />}
          height={25}
          imagePath={values.icon.url}
          width={25}
          variant={ImageVariant.Contain}
        />
      )}
    </div>
  );
};

export default IconFiled;
