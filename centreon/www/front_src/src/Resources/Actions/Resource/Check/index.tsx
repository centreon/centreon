import { useAtom } from 'jotai';
import { isNil } from 'ramda';
import { useTranslation } from 'react-i18next';

import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconCheck from '@mui/icons-material/Sync';

import { Method, useMutationQuery, useSnackbar } from '@centreon/ui';

import { Resource } from '../../../models';
import { labelCheck, labelForcedCheck } from '../../../translatedLabels';
import { checkEndpoint } from '../../api/endpoint';
import { Data } from '../../model';
import ResourceActionButton from '../ResourceActionButton';
import useAclQuery from '../aclQuery';

import Check from './Check';
import { checkActionAtom } from './checkAtoms';
import { adjustCheckedResources } from './helpers';

interface Props {
  displayCondensed?: boolean;
  resources: Array<Resource>;
  testId: string;
}

const CheckActionButton = ({
  resources,
  testId,
  displayCondensed,
  ...rest
}: Props & Data): JSX.Element => {
  const { t } = useTranslation();

  const [checkAction, setCheckAction] = useAtom(checkActionAtom);
  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => checkEndpoint,
    method: Method.POST
  });

  const { canForcedCheck, canCheck } = useAclQuery();
  const { showSuccessMessage } = useSnackbar();

  const disableCheck = !canCheck(resources);
  const disableForcedCheck = !canForcedCheck(resources);
  const hasSelectedResources = resources.length > 0;

  const isCheckPermitted = canCheck(resources) || !hasSelectedResources;

  const isForcedCheckPermitted =
    canForcedCheck(resources) || !hasSelectedResources;

  const handleCheckResource = (): void => {
    if (isNil(checkAction)) {
      checkResource({
        check: { is_forced: canForcedCheck(resources) },
        resources: adjustCheckedResources({ resources })
      }).then(() => {
        showSuccessMessage(
          canForcedCheck(resources)
            ? t(rest.success.msgForcedCheckCommandSent)
            : t(rest.success.msgLabelCheckCommandSent)
        );
      });

      return;
    }
    checkResource({
      check: { is_forced: checkAction.forcedChecked },
      resources: adjustCheckedResources({ resources })
    }).then(() => {
      showSuccessMessage(
        checkAction?.checked
          ? t(rest.success.msgLabelCheckCommandSent)
          : t(rest.success.msgForcedCheckCommandSent)
      );
    });
  };

  if (isNil(checkAction)) {
    if (canForcedCheck(resources)) {
      return (
        <Check
          disabledButton={disableForcedCheck}
          disabledList={{ disableCheck, disableForcedCheck }}
          displayCondensed={displayCondensed}
          isDefaultChecked={false}
          renderResourceActionButton={({ onClick }) => (
            <ResourceActionButton
              disabled={disableForcedCheck}
              displayCondensed={displayCondensed}
              icon={<IconForcedCheck />}
              label={t(labelForcedCheck)}
              permitted={isForcedCheckPermitted}
              testId={testId}
              onClick={onClick}
            />
          )}
          onClickActionButton={handleCheckResource}
          onClickList={{
            onClickCheck: (): void =>
              setCheckAction({ checked: true, forcedChecked: false }),
            onClickForcedCheck: (): void =>
              setCheckAction({ checked: false, forcedChecked: true })
          }}
          {...rest}
        />
      );
    }

    if (canCheck(resources)) {
      return (
        <Check
          isDefaultChecked
          disabledButton={disableCheck}
          disabledList={{ disableCheck, disableForcedCheck }}
          displayCondensed={displayCondensed}
          renderResourceActionButton={({ onClick }) => (
            <ResourceActionButton
              disabled={disableCheck}
              displayCondensed={displayCondensed}
              icon={<IconCheck />}
              label={t(labelCheck)}
              permitted={isCheckPermitted}
              testId={testId}
              onClick={onClick}
            />
          )}
          onClickActionButton={handleCheckResource}
          onClickList={{
            onClickCheck: (): void =>
              setCheckAction({ checked: true, forcedChecked: false }),
            onClickForcedCheck: (): void =>
              setCheckAction({ checked: false, forcedChecked: true })
          }}
          {...rest}
        />
      );
    }

    return (
      <Check
        disabledButton
        displayCondensed={displayCondensed}
        renderResourceActionButton={({ onClick }) => (
          <ResourceActionButton
            disabled
            displayCondensed={displayCondensed}
            icon={<IconForcedCheck />}
            label={t(labelForcedCheck)}
            permitted={isForcedCheckPermitted}
            testId={testId}
            onClick={onClick}
          />
        )}
        onClickActionButton={(): void => undefined}
        {...rest}
      />
    );
  }

  if (canForcedCheck(resources) && checkAction.forcedChecked) {
    return (
      <Check
        disabledButton={disableForcedCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
        displayCondensed={displayCondensed}
        isDefaultChecked={false}
        renderResourceActionButton={({ onClick }) => (
          <ResourceActionButton
            disabled={disableForcedCheck}
            displayCondensed={displayCondensed}
            icon={<IconForcedCheck />}
            label={t(labelForcedCheck)}
            permitted={isCheckPermitted}
            testId={testId}
            onClick={onClick}
          />
        )}
        onClickActionButton={handleCheckResource}
        onClickList={{
          onClickCheck: (): void =>
            setCheckAction({ checked: true, forcedChecked: false }),
          onClickForcedCheck: (): void =>
            setCheckAction({ checked: false, forcedChecked: true })
        }}
        {...rest}
      />
    );
  }

  if (canCheck(resources) && checkAction.checked) {
    return (
      <Check
        isDefaultChecked
        disabledButton={disableCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
        displayCondensed={displayCondensed}
        renderResourceActionButton={({ onClick }) => (
          <ResourceActionButton
            disabled={disableCheck}
            displayCondensed={displayCondensed}
            icon={<IconCheck />}
            label={t(labelCheck)}
            permitted={isCheckPermitted}
            testId={testId}
            onClick={onClick}
          />
        )}
        onClickActionButton={handleCheckResource}
        onClickList={{
          onClickCheck: (): void =>
            setCheckAction({ checked: true, forcedChecked: false }),
          onClickForcedCheck: (): void =>
            setCheckAction({ checked: false, forcedChecked: true })
        }}
        {...rest}
      />
    );
  }

  return (
    <Check
      disabledButton
      displayCondensed={displayCondensed}
      renderResourceActionButton={({ onClick }) => (
        <ResourceActionButton
          disabled
          displayCondensed={displayCondensed}
          icon={
            checkAction?.forcedChecked ? <IconForcedCheck /> : <IconCheck />
          }
          label={
            checkAction?.forcedChecked ? t(labelForcedCheck) : t(labelCheck)
          }
          permitted={
            checkAction?.forcedChecked
              ? isForcedCheckPermitted
              : isCheckPermitted
          }
          testId={testId}
          onClick={onClick}
        />
      )}
      onClickActionButton={(): void => undefined}
      {...rest}
    />
  );
};

export default CheckActionButton;
