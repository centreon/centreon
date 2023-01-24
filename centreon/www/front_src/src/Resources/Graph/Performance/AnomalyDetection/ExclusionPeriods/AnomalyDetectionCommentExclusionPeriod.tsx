import { ChangeEvent, useState } from 'react';

import { makeStyles } from 'tss-react/mui';

import { TextField, Typography } from '@mui/material';
import Checkbox from '@mui/material/Checkbox';
import FormControlLabel from '@mui/material/FormControlLabel';

import { labelFromBeginning } from '../../../../translatedLabels';

const useStyles = makeStyles()((theme) => ({
  container: {
    margin: theme.spacing(0, 0, 1, 0)
  },
  field: {
    width: '100%'
  }
}));

interface Props {
  isExclusionPeriodChecked: boolean;
  onChangeCheckedExclusionPeriod: (
    event: ChangeEvent<HTMLInputElement>
  ) => void;
}

const AnomalyDetectionCommentExclusionPeriod = ({
  onChangeCheckedExclusionPeriod,
  isExclusionPeriodChecked
}: Props): JSX.Element => {
  const { classes } = useStyles();
  const [comment, setComment] = useState(null);

  const changeComment = (event): void => {
    setComment(event.target.value);
  };

  return (
    <>
      <FormControlLabel
        control={
          <Checkbox
            checked={isExclusionPeriodChecked}
            data-testid="checkFromBeginning"
            onChange={onChangeCheckedExclusionPeriod}
          />
        }
        data-testid={labelFromBeginning}
        label={<Typography>{labelFromBeginning}</Typography>}
      />
      <div className={classes.container}>
        <Typography>Comment </Typography>
        <TextField
          multiline
          InputProps={{
            disableUnderline: true
          }}
          className={classes.field}
          rows={3}
          value={comment}
          variant="filled"
          onChange={changeComment}
        />
      </div>
    </>
  );
};

export default AnomalyDetectionCommentExclusionPeriod;
