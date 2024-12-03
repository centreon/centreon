import { Typography } from '@mui/material';
import { Checkbox } from 'packages/ui/src';
import { equals } from 'ramda';
import { useEffect, useState } from 'react';

const CheckBoxCriter = ({ defaultLabel, labels, title, getData }) => {
  const { firstLabel, secondLabel } = labels;
  const [checkedLabel, setCheckedLabel] = useState({
    label: defaultLabel,
    value: true
  });

  const onChange = (event) => {
    if (event?.target?.id === checkedLabel.label) {
      return;
    }

    setCheckedLabel({
      label: event?.target?.id,
      value: event?.target?.checked
    });
  };

  const getCheckedValue = (label) => {
    if (!equals(checkedLabel.label, label)) {
      return false;
    }
    return checkedLabel.value;
  };

  useEffect(() => {
    getData(checkedLabel);
  }, [checkedLabel.label, checkedLabel.value]);

  return (
    <>
      <Typography variant="subtitle2">{title}</Typography>
      <Checkbox
        label={firstLabel}
        checked={getCheckedValue(firstLabel)}
        onChange={onChange}
      />
      <Checkbox
        label={secondLabel}
        checked={getCheckedValue(secondLabel)}
        onChange={onChange}
      />
    </>
  );
};

export default CheckBoxCriter;
