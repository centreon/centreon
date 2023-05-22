import { SetStateAction } from 'react';

import { useAtom } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconCheck from '@mui/icons-material/Sync';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { Resource } from '../../../models';
import {
  labelCheck,
  labelCheckCommandSent,
  labelForcedCheck,
  labelForcedCheckCommandSent
} from '../../../translatedLabels';
import { checkEndpoint } from '../../api/endpoint';
import useAclQuery from '../aclQuery';

import Check from './Check';
import { checkActionAtom } from './checkAtoms';
import { adjustCheckedResources } from './helpers';

interface Props {
  selectedResources: Array<Resource>;
  setSelectedResources: (update: SetStateAction<Array<Resource>>) => void;
  testId: string;
}

const CheckActionButton = ({
  selectedResources,
  setSelectedResources,
  testId
}: Props): JSX.Element => {
  const { t } = useTranslation();

  const [checkAction, setCheckAction] = useAtom(checkActionAtom);
  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => checkEndpoint,
    method: Method.POST
  });

  const { canForcedCheck, canCheck } = useAclQuery();
  const { showSuccessMessage } = useSnackbar();

  const disableCheck = !canCheck(selectedResources);
  const disableForcedCheck = !canForcedCheck(selectedResources);
  const hasSelectedResources = selectedResources.length > 0;

  const isCheckPermitted = canCheck(selectedResources) || !hasSelectedResources;

  const isForcedCheckPermitted =
    canForcedCheck(selectedResources) || !hasSelectedResources;

  const handleCheckResource = (): void => {
    if (isNil(checkAction)) {
      checkResource({
        check: { is_forced: canForcedCheck(selectedResources) },
        resources: adjustCheckedResources({ resources: selectedResources })
      }).then(() => {
        setSelectedResources([]);
        showSuccessMessage(
          canForcedCheck(selectedResources)
            ? t(labelForcedCheckCommandSent)
            : t(labelCheckCommandSent)
        );
      });

      return;
    }
    checkResource({
      check: { is_forced: checkAction.forcedChecked },
      resources: adjustCheckedResources({ resources: selectedResources })
    }).then(() => {
      setSelectedResources([]);
      showSuccessMessage(
        checkAction?.checked
          ? t(labelCheckCommandSent)
          : t(labelForcedCheckCommandSent)
      );
    });
  };

  if (isNil(checkAction)) {
    if (canForcedCheck(selectedResources)) {
      return (
        <Check
          disabledButton={disableForcedCheck}
          disabledList={{ disableCheck, disableForcedCheck }}
          icon={<IconForcedCheck />}
          isActionPermitted={isForcedCheckPermitted}
          isDefaultChecked={false}
          labelButton={labelForcedCheck}
          testId={testId}
          onClickActionButton={handleCheckResource}
          onClickList={{
            onClickCheck: (): void =>
              setCheckAction({ checked: true, forcedChecked: false }),
            onClickForcedCheck: (): void =>
              setCheckAction({ checked: false, forcedChecked: true })
          }}
        />
      );
    }

    if (canCheck(selectedResources)) {
      return (
        <Check
          isDefaultChecked
          disabledButton={disableCheck}
          disabledList={{ disableCheck, disableForcedCheck }}
          icon={<IconCheck />}
          isActionPermitted={isCheckPermitted}
          labelButton={labelCheck}
          testId={testId}
          onClickActionButton={handleCheckResource}
          onClickList={{
            onClickCheck: (): void =>
              setCheckAction({ checked: true, forcedChecked: false }),
            onClickForcedCheck: (): void =>
              setCheckAction({ checked: false, forcedChecked: true })
          }}
        />
      );
    }

    return (
      <Check
        disabledButton
        icon={<IconForcedCheck />}
        isActionPermitted={isForcedCheckPermitted}
        labelButton={t(labelForcedCheck)}
        testId={testId}
        onClickActionButton={(): void => undefined}
      />
    );
  }

  if (canForcedCheck(selectedResources) && checkAction.forcedChecked) {
    return (
      <Check
        disabledButton={disableForcedCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
        icon={<IconForcedCheck />}
        isActionPermitted={isCheckPermitted}
        isDefaultChecked={false}
        labelButton={labelForcedCheck}
        testId={testId}
        onClickActionButton={handleCheckResource}
        onClickList={{
          onClickCheck: (): void =>
            setCheckAction({ checked: true, forcedChecked: false }),
          onClickForcedCheck: (): void =>
            setCheckAction({ checked: false, forcedChecked: true })
        }}
      />
    );
  }

  if (canCheck(selectedResources) && checkAction.checked) {
    return (
      <Check
        isDefaultChecked
        disabledButton={disableCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
        icon={<IconCheck />}
        isActionPermitted={isCheckPermitted}
        labelButton={labelCheck}
        testId={testId}
        onClickActionButton={handleCheckResource}
        onClickList={{
          onClickCheck: (): void =>
            setCheckAction({ checked: true, forcedChecked: false }),
          onClickForcedCheck: (): void =>
            setCheckAction({ checked: false, forcedChecked: true })
        }}
      />
    );
  }

  return (
    <Check
      disabledButton
      icon={checkAction?.forcedChecked ? <IconForcedCheck /> : <IconCheck />}
      isActionPermitted={
        checkAction?.forcedChecked ? isForcedCheckPermitted : isCheckPermitted
      }
      labelButton={
        checkAction?.forcedChecked ? t(labelForcedCheck) : t(labelCheck)
      }
      testId={testId}
      onClickActionButton={(): void => undefined}
    />
  );
};

export default CheckActionButton;
