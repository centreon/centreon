import CheckIcon from '@mui/icons-material/Check';
import SaveIcon from '@mui/icons-material/Save';
import {
  T,
  always,
  any,
  cond,
  isEmpty,
  isNil,
  not,
  or,
  pipe,
  propEq
} from 'ramda';
import { useMemo } from 'react';
import { useTranslation } from 'react-i18next';
import { Props } from '.';

interface StartIconConfigProps {
  hasLabel: boolean;
  loading: boolean;
  succeeded: boolean;
  enabled: boolean;
}

const isNilOrEmpty = (value): boolean => or(isNil(value), isEmpty(value));
const hasValue = any(pipe(isNilOrEmpty, not));

interface UseSaveState {
  content: string | JSX.Element;
  startIconToDisplay: null | JSX.Element;
  hasLabel: boolean;
}

export const useSave = ({
  labelLoading,
  labelSave,
  labelSucceeded,
  loading,
  succeeded,
  startIcon
}: Pick<
  Props,
  | 'startIcon'
  | 'succeeded'
  | 'loading'
  | 'labelSave'
  | 'labelSucceeded'
  | 'labelLoading'
>): UseSaveState => {
  const { t } = useTranslation();

  const hasLabel = hasValue([labelLoading, labelSave, labelSucceeded]);

  const startIconConfig = {
    hasLabel,
    loading,
    succeeded,
    enabled: startIcon
  } as StartIconConfigProps;

  const content = useMemo(() => {
    if (loading) {
      return t(labelLoading || 'loading');
    }

    if (succeeded) {
      return labelSucceeded ? t(labelSucceeded) : <CheckIcon />;
    }

    return labelSave ? t(labelSave) : <SaveIcon />;
  }, [labelLoading, labelSucceeded, labelSave, loading, succeeded]);

  const startIconToDisplay = useMemo(() => {
    return cond<Array<StartIconConfigProps>, JSX.Element | null>([
      [propEq(true, 'enabled'), always(null)],
      [pipe(propEq(true, 'hasLabel'), not), always(null)],
      [propEq(true, 'succeeded'), always(<CheckIcon />)],
      [propEq(true, 'loading'), always(<SaveIcon />)],
      [T, always(<SaveIcon />)]
    ])(startIconConfig);
  }, [startIconConfig]);

  return {
    content,
    startIconToDisplay,
    hasLabel
  };
};
