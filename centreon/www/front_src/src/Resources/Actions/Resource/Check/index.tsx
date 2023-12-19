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
import ResourceActionButton from '../ResourceActionButton';

import Check from './Check';
import { checkActionAtom } from './checkAtoms';
import { adjustCheckedResources } from './helpers';

interface Props {
  displayCondensed?: boolean;
  initialize: () => void;
  resources: Array<Resource>;
  testId: string;
}

const CheckActionButton = ({
  resources,
  initialize,
  testId,
  displayCondensed
}: Props): JSX.Element => {
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
        initialize();
        showSuccessMessage(
          canForcedCheck(resources)
            ? t(labelForcedCheckCommandSent)
            : t(labelCheckCommandSent)
        );
      });

      return;
    }
    checkResource({
      check: { is_forced: checkAction.forcedChecked },
      resources: adjustCheckedResources({ resources })
    }).then(() => {
      initialize();
      showSuccessMessage(
        checkAction?.checked
          ? t(labelCheckCommandSent)
          : t(labelForcedCheckCommandSent)
      );
    });
  };

  if (isNil(checkAction)) {
    if (canForcedCheck(resources)) {
      return (
        <Check
          disabledButton={disableForcedCheck}
          disabledList={{ disableCheck, disableForcedCheck }}
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
        />
      );
    }

    if (canCheck(resources)) {
      return (
        <Check
          isDefaultChecked
          disabledButton={disableCheck}
          disabledList={{ disableCheck, disableForcedCheck }}
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
        />
      );
    }

    return (
      <Check
        disabledButton
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
      />
    );
  }

  if (canForcedCheck(resources) && checkAction.forcedChecked) {
    return (
      <Check
        disabledButton={disableForcedCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
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
      />
    );
  }

  if (canCheck(resources) && checkAction.checked) {
    return (
      <Check
        isDefaultChecked
        disabledButton={disableCheck}
        disabledList={{ disableCheck, disableForcedCheck }}
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
      />
    );
  }

  return (
    <Check
      disabledButton
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
    />
  );
};

export default CheckActionButton;
