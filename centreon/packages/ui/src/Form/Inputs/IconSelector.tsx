import { type FormikValues, useFormikContext } from 'formik';
import { isNil, path, split } from 'ramda';
import { useTranslation } from 'react-i18next';

import Image, { ImageVariant } from '../../Image/Image';
import SingleConnectedAutocompleteField from '../../InputField/Select/Autocomplete/Connected/Single';
import LoadingSkeleton from '../../LoadingSkeleton';
import type { InputPropsWithoutGroup } from './models';

interface Icon {
  id: number;
  name: string;
  url: string;
}

const previewSize = 25;

// A picker of the images listed by `iconSelector.getEndpoint`, each option
// with its thumbnail, and the picked image beside it. The field holds the
// picked `{ id, name, url }`, or null.
const IconSelector = ({
  dataTestId,
  fieldName,
  getDisabled,
  iconSelector,
  label
}: InputPropsWithoutGroup): JSX.Element | null => {
  const { t } = useTranslation();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const icon = path(split('.', fieldName), values) as Icon | null | undefined;

  const changeIcon = (_: unknown, value: unknown): void => {
    setFieldValue(fieldName, value);
  };

  if (isNil(iconSelector)) {
    return null;
  }

  return (
    <div className="flex items-center gap-2">
      <SingleConnectedAutocompleteField
        dataTestId={dataTestId}
        disableClearable={false}
        disabled={getDisabled?.(values) ?? false}
        displayOptionThumbnail
        field="name"
        fullWidth
        getEndpoint={iconSelector.getEndpoint}
        id={fieldName}
        label={t(label)}
        onChange={changeIcon}
        value={icon}
      />
      {icon && (
        <Image
          alt={icon.name}
          fallback={<LoadingSkeleton />}
          height={previewSize}
          imagePath={icon.url}
          variant={ImageVariant.Contain}
          width={previewSize}
        />
      )}
    </div>
  );
};

export default IconSelector;
