import { Checkbox } from '@centreon/ui';
import { Typography } from '@mui/material';
import { equals } from 'ramda';
import { useCallback, useMemo, useState } from 'react';
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
  const labelProps = useMemo(
    () => ({
      classes: { root: classes.label },
      variant: 'body2' as const
    }),
    []
  );

  const onChange = useCallback(
    (event) => {
      if (equals(event?.target?.id, checkedLabel.label)) {
        return;
      }

      setCheckedLabel({
        label: event?.target?.id,
        isChecked: event?.target?.checked
      });

      getData(event?.target?.id);
    },
    [checkedLabel.label]
  );

  const getCheckedValue = useCallback(
    (label: string) => equals(checkedLabel.label, label),
    [checkedLabel.label]
  );

  return (
    <>
      <Typography variant="subtitle2" sx={{ paddingBottom: 0.5 }}>
        {title}
      </Typography>
      <Checkbox
        label={firstLabel}
        checked={getCheckedValue(firstLabel)}
        onChange={onChange}
        labelProps={labelProps}
        dataTestId={firstLabel}
      />
      <Checkbox
        label={secondLabel}
        checked={getCheckedValue(secondLabel)}
        onChange={onChange}
        labelProps={labelProps}
        dataTestId={secondLabel}
      />
    </>
  );
};

export default CheckBoxScope;
