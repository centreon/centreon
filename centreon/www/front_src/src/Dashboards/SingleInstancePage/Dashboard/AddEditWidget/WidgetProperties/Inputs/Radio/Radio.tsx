import { ChangeEvent, useEffect, useMemo, useRef } from 'react';

import { useTranslation } from 'react-i18next';
import { useFormikContext } from 'formik';
import { equals, has, isNil } from 'ramda';

import { RadioGroup, FormControlLabel, Radio } from '@mui/material';

import { SelectEntry } from '@centreon/ui';

import {
  ConditionalOptions,
  Widget,
  WidgetPropertyProps
} from '../../../models';
import { useCanEditProperties } from '../../../../hooks/useCanEditDashboard';
import Subtitle from '../../../../components/Subtitle';
import { getProperty } from '../utils';

const WidgetRadio = ({
  propertyName,
  options,
  label,
  defaultValue
}: WidgetPropertyProps): JSX.Element => {
  const previousDependencyValue = useRef<undefined | unknown>(undefined);

  const { t } = useTranslation();

  const { values, setFieldValue, setFieldTouched } = useFormikContext<Widget>();

  const value = useMemo<string | undefined>(
    () => getProperty({ obj: values, propertyName }),
    [getProperty({ obj: values, propertyName })]
  );

  const { canEditField } = useCanEditProperties();

  const dependencyValue = has('when', options)
    ? values.options[options.when]
    : undefined;

  const getOptions = (): Array<SelectEntry> => {
    if (has('when', options)) {
      return equals(dependencyValue, options.is)
        ? options.then
        : options.otherwise;
    }

    return options || [];
  };

  const optionsToDisplay = getOptions();

  const change = (event: ChangeEvent<HTMLInputElement>): void => {
    setFieldTouched(`options.${propertyName}`, true);
    setFieldValue(`options.${propertyName}`, event.target.value);
  };

  useEffect(() => {
    if (isNil(dependencyValue)) {
      return;
    }

    const canApplyDefaultValue = !!previousDependencyValue.current;

    if (!canApplyDefaultValue) {
      previousDependencyValue.current = dependencyValue;

      return;
    }

    const { is, then, otherwise } = defaultValue as ConditionalOptions<unknown>;

    setFieldValue(
      `options.${propertyName}`,
      equals(is, dependencyValue) ? then : otherwise
    );
  }, [dependencyValue]);

  return (
    <div>
      <Subtitle>{t(label)}</Subtitle>
      <RadioGroup value={value} onChange={change}>
        {optionsToDisplay.map(({ id, name }) => (
          <FormControlLabel
            control={<Radio />}
            disabled={!canEditField}
            key={id}
            label={t(name)}
            value={id}
          />
        ))}
      </RadioGroup>
    </div>
  );
};

export default WidgetRadio;
