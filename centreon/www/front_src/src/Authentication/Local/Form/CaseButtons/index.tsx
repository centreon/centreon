import { useMemo } from 'react';

import { FormikValues, useFormikContext } from 'formik';
import { useTranslation } from 'react-i18next';
import { makeStyles } from 'tss-react/mui';

import {
  Button,
  ButtonProps,
  Stack,
  Typography,
  useTheme
} from '@mui/material';

import { useMemoComponent } from '@centreon/ui';

import StrengthProgress from '../../StrengthProgress';
import {
  labelChooseLetterCases,
  labelGood,
  labelLowerCase,
  labelNumber,
  labelPasswordExpiresAfter,
  labelPasswordMustContainLowerCase,
  labelPasswordMustContainNumbers,
  labelPasswordMustContainSpecialCharacters,
  labelPasswordMustContainUpperCase,
  labelSpecialCharacters,
  labelStrong,
  labelUpperCase,
  labelWeak
} from '../../translatedLabels';
import { getFields } from '../utils';

import LabelWithTooltip from './LabelWithTooltip';

const activeButtonProps = {
  variant: 'contained'
} as ButtonProps;
const hasLowerCaseName = 'hasLowerCase';
const hasUpperCaseName = 'hasUpperCase';
const hasNumberName = 'hasNumber';
const hasSpecialCharacterName = 'hasSpecialCharacter';

const useStyles = makeStyles()((theme) => ({
  button: {
    minWidth: theme.spacing(4)
  },
  caseButtonsContainer: {
    display: 'flex',
    flexDirection: 'column',
    rowGap: theme.spacing(0.5),
    width: 'fit-content'
  },
  lowerCaseButton: {
    textTransform: 'none'
  }
}));

const CaseButtons = (): JSX.Element => {
  const { classes, cx } = useStyles();
  const { t } = useTranslation();
  const theme = useTheme();

  const { values, setFieldValue } = useFormikContext<FormikValues>();

  const selectCase = (caseName: string) => (): void => {
    setFieldValue(caseName, !values[caseName]);
  };

  const [hasLowerCase, hasUpperCase, hasNumber, hasSpecialCharacter] =
    getFields<boolean>({
      fields: [
        hasLowerCaseName,
        hasUpperCaseName,
        hasNumberName,
        hasSpecialCharacterName
      ],
      object: values
    });

  const thresholds = useMemo(
    () => [
      { color: theme.palette.error.main, label: labelWeak, value: 2 },
      { color: theme.palette.warning.main, label: labelGood, value: 3 },
      { color: theme.palette.success.main, label: labelStrong, value: 4 }
    ],
    []
  );

  const thresholdValue = [
    hasLowerCase,
    hasUpperCase,
    hasNumber,
    hasSpecialCharacter
  ].filter(Boolean).length;

  return useMemoComponent({
    Component: (
      <div className={classes.caseButtonsContainer}>
        <Typography variant="caption">{t(labelChooseLetterCases)}</Typography>
        <Stack
          aria-label={t(labelPasswordExpiresAfter)}
          direction="row"
          spacing={1}
        >
          <Button
            aria-label={t(labelPasswordMustContainLowerCase)}
            className={cx(classes.lowerCaseButton, classes.button)}
            color="primary"
            data-testid={labelPasswordMustContainLowerCase}
            id={labelPasswordMustContainLowerCase?.replace(/ /g, '')}
            size="small"
            variant="outlined"
            onClick={selectCase(hasLowerCaseName)}
            {...(hasLowerCase && activeButtonProps)}
          >
            <LabelWithTooltip
              label={labelLowerCase}
              tooltipLabel={labelPasswordMustContainLowerCase}
            />
          </Button>
          <Button
            aria-label={t(labelPasswordMustContainUpperCase)}
            className={classes.button}
            color="primary"
            data-testid={labelPasswordMustContainUpperCase}
            id={labelPasswordMustContainUpperCase?.replace(/ /g, '')}
            size="small"
            variant="outlined"
            onClick={selectCase(hasUpperCaseName)}
            {...(hasUpperCase && activeButtonProps)}
          >
            <LabelWithTooltip
              label={labelUpperCase}
              tooltipLabel={labelPasswordMustContainUpperCase}
            />
          </Button>
          <Button
            aria-label={t(labelPasswordMustContainNumbers)}
            className={classes.button}
            color="primary"
            data-testid={labelPasswordMustContainNumbers}
            id={labelPasswordMustContainNumbers?.replace(/ /g, '')}
            size="small"
            variant="outlined"
            onClick={selectCase(hasNumberName)}
            {...(hasNumber && activeButtonProps)}
          >
            <LabelWithTooltip
              label={labelNumber}
              tooltipLabel={labelPasswordMustContainNumbers}
            />
          </Button>
          <Button
            aria-label={t(labelPasswordMustContainSpecialCharacters)}
            className={classes.button}
            color="primary"
            data-testid={labelPasswordMustContainSpecialCharacters}
            id={labelPasswordMustContainSpecialCharacters.replace(/ /g, '')}
            size="small"
            variant="outlined"
            onClick={selectCase(hasSpecialCharacterName)}
            {...(hasSpecialCharacter && activeButtonProps)}
          >
            <LabelWithTooltip
              label={labelSpecialCharacters}
              tooltipLabel={labelPasswordMustContainSpecialCharacters}
            />
          </Button>
        </Stack>
        <StrengthProgress
          max={4}
          thresholds={thresholds}
          value={thresholdValue}
        />
      </div>
    ),
    memoProps: [hasLowerCase, hasUpperCase, hasNumber, hasSpecialCharacter]
  });
};

export default CaseButtons;
