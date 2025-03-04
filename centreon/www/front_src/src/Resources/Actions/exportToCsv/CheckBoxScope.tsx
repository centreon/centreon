import { Typography } from '@mui/material';
import { Checkbox } from 'packages/ui/src';
import { equals } from 'ramda';
import { useEffect, useState } from 'react';
import useExportCsvStyles from './exportCsv.styles';
import { CheckedLabel, Label } from './models';

interface Props {
  defaultCheckedLabel: CheckedLabel;
  labels: Label;
  title: string;
  getData: (label: string) => void;
}

const CheckBoxScope = ({
  defaultCheckedLabel,
  labels,
  title,
  getData
}: Props) => {
  const { classes } = useExportCsvStyles();
  const { firstLabel, secondLabel } = labels;
  const [checkedLabel, setCheckedLabel] = useState(defaultCheckedLabel);
  const labelProps = {
    classes: { root: classes.label },
    variant: 'body2' as const
  };

  const onChange = (event) => {
    if (event?.target?.id === checkedLabel.label) {
      return;
    }

    setCheckedLabel({
      label: event?.target?.id,
      isChecked: event?.target?.checked
    });
  };

  const getCheckedValue = (label: string) => equals(checkedLabel.label, label);

  useEffect(() => {
    getData(checkedLabel.label);
  }, [checkedLabel.label]);

  return (
    <>
      <Typography variant="subtitle2" sx={{ paddingBottom: 0.25 }}>
        {title}
      </Typography>
      <Checkbox
        label={firstLabel}
        checked={getCheckedValue(firstLabel)}
        onChange={onChange}
        labelProps={labelProps}
      />
      <Checkbox
        label={secondLabel}
        checked={getCheckedValue(secondLabel)}
        onChange={onChange}
        labelProps={labelProps}
      />
    </>
  );
};

export default CheckBoxScope;
