import { SingleAutocompleteField, useResizeObserver } from '@centreon/ui';
import dayjs from 'dayjs';
import { useFormikContext } from 'formik';
import { useAtomValue } from 'jotai';
import { equals } from 'ramda';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { CreateTokenFormValues } from '../../../Listing/models';
import { tokenAtom } from '../../../atoms';
import { labelDuration } from '../../../translatedLabels';
import { dataDuration } from '../../utils';
import { useDurationstyles } from './Duration.styles';
import InputCalendar from './inputCalendar';

const DurationField = (): JSX.Element => {
  const { t } = useTranslation();
  const { classes } = useDurationstyles();

  const token = useAtomValue(tokenAtom);

  const { height = 0 } = useResizeObserver<HTMLElement>({
    ref: document.getElementById('root')
  });

  const [isDisplayingDateTimePicker, setIsDisplayingDateTimePicker] =
    useState(false);

  const { values, setFieldValue } = useFormikContext<CreateTokenFormValues>();

  const options = dataDuration.map(({ id, name }) => ({
    id,
    name: t(name)
  }));

  const selectCustomizeCase = (value): void => {
    setIsDisplayingDateTimePicker(true);

    if (dayjs(values.duration?.name).isValid()) {
      return;
    }
    setFieldValue('duration', value);
  };

  const changeDuration = (_, value): void => {
    if (equals(value.id, 'customize')) {
      selectCustomizeCase(value);

      return;
    }
    setFieldValue('customizeDate', null);

    setFieldValue('duration', value);
  };

  return (
    <div className={classes.container}>
      <SingleAutocompleteField
        dataTestId={labelDuration}
        disabled={Boolean(token)}
        getOptionItemLabel={(option) => option?.name}
        id="duration"
        label={t(labelDuration)}
        options={options}
        required={true}
        value={values.duration}
        onChange={changeDuration}
      />
      {isDisplayingDateTimePicker &&
        equals(values.duration?.id, 'customize') && (
          <InputCalendar
            setIsDisplayingDateTimePicker={setIsDisplayingDateTimePicker}
            windowHeight={height}
          />
        )}
    </div>
  );
};

export default DurationField;
