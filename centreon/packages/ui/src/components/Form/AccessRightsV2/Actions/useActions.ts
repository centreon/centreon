import { useAtomValue } from 'jotai';
import { equals, omit } from 'ramda';

import { initialValuesAtom, valuesAtom } from '../atoms';
import { AccessRight, AccessRightInitialValues, Labels } from '../models';
import { useCopyToClipboard } from '../../../..';

const formatValue = (accessRight: AccessRight): AccessRightInitialValues => {
  return omit(['isAdded', 'isUpdated', 'isRemoved'], accessRight);
};

const formatValueForSubmition = (
  accessRight: AccessRight
): AccessRightInitialValues => {
  return {
    ...formatValue(accessRight),
    id: Number((accessRight.id as string).split('_')[1])
  };
};

interface Props {
  clear: () => void;
  labels: Labels['actions'];
  link?: string;
  submit: (values: Array<AccessRightInitialValues>) => Promise<void>;
}

interface UseActionsState {
  copyLink: () => void;
  dirty: boolean;
  formattedValues: Array<AccessRightInitialValues>;
  save: () => void;
}

export const useActions = ({
  link,
  labels,
  submit,
  clear
}: Props): UseActionsState => {
  const values = useAtomValue(valuesAtom);
  const initialValues = useAtomValue(initialValuesAtom);

  const { copy } = useCopyToClipboard({
    errorMessage: labels.copyError,
    successMessage: labels.copySuccess
  });

  const formattedValues = values
    .filter(({ isRemoved }) => !isRemoved)
    .map(formatValue);

  const dirty = !equals(initialValues, formattedValues);

  const copyLink = (): void => {
    if (!link) {
      return;
    }
    copy(link);
  };

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
    copyLink,
    dirty,
    formattedValues,
    save
  };
};
