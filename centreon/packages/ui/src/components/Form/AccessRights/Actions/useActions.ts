import { useAtomValue } from 'jotai';
import { equals } from 'ramda';

import { initialValuesAtom, valuesAtom } from '../atoms';
import { AccessRightInitialValues, Labels } from '../models';
import { formatValue, formatValueForSubmition } from '../utils';

interface Props {
  clear: () => void;
  labels: Labels['actions'];
  submit: (values: Array<AccessRightInitialValues>) => Promise<void>;
}

interface UseActionsState {
  dirty: boolean;
  formattedValues: Array<AccessRightInitialValues>;
  save: () => void;
}

export const useActions = ({ submit, clear }: Props): UseActionsState => {
  const values = useAtomValue(valuesAtom);
  const initialValues = useAtomValue(initialValuesAtom);

  const formattedValues = values
    .filter(({ isRemoved }) => !isRemoved)
    .map(formatValue);

  const dirty = !equals(initialValues, formattedValues);

  const save = (): void => {
    submit(
      values.filter(({ isRemoved }) => !isRemoved).map(formatValueForSubmition)
    )?.then((isError) => {
      if (isError) {
        return;
      }
      clear();
    });
  };

  return {
    dirty,
    formattedValues,
    save
  };
};
