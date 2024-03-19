import { useTranslation } from 'react-i18next';
import { useAtom } from 'jotai';

import { ResourceAccessRule } from '../../models';
import { duplicatedRuleAtom, isDuplicateDialogOpenAtom } from '../../atom';
import useDuplicateRequest from '../api/useDuplicateRequest';
import {
  labelFailedToDuplicateRule,
  labelRuleDuplicatedSuccess
} from '../../translatedLabels';

interface UseDuplicateState {
  closeDialog: () => void;
  duplicateItem: ({
    id,
    resourceAccessRule
  }: {
    id: number | null;
    resourceAccessRule: ResourceAccessRule;
  }) => void;
  isDialogOpen: boolean;
  openDialog: () => void;
  submit: (
    values,
    {
      resetForm,
      setSubmitting
    }: {
      resetForm;
      setSubmitting;
    }
  ) => Promise<object>;
}

const useDuplicate = (): UseDuplicateState => {
  const { t } = useTranslation();
  const [isDuplicateDialogOpen, setIsDuplicateDialogOpen] = useAtom(
    isDuplicateDialogOpenAtom
  );
  const [duplicatedRule, setDuplicatedRule] = useAtom(duplicatedRuleAtom);

  const openDialog = (): void => setIsDuplicateDialogOpen(true);
  const closeDialog = (): void => setIsDuplicateDialogOpen(false);

  const duplicateItem = ({ id, resourceAccessRule: data }): void => {
    setDuplicatedRule({ id, rule: data });
    setIsDuplicateDialogOpen(true);
  };

  const onSettled = (): void => {
    closeDialog();
  };

  const { submit } = useDuplicateRequest({
    labelFailure: t(labelFailedToDuplicateRule),
    labelSuccess: t(labelRuleDuplicatedSuccess),
    onSettled,
    ruleId: duplicatedRule?.id
  });

  return {
    closeDialog,
    duplicateItem,
    isDialogOpen: isDuplicateDialogOpen,
    openDialog,
    submit
  };
};

export default useDuplicate;
