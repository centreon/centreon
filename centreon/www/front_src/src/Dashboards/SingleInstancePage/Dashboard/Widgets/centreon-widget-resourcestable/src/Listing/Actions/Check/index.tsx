import { PrimitiveAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconCheck from '@mui/icons-material/Sync';

import { Method, useMutationQuery } from '@centreon/ui';

import { Resource } from '../../models';
import { labelCheck, labelForcedCheck } from '../../translatedLabels';
import ResourceActionButton from '../ResourceActionButton';
import useAclQuery from '../aclQuery';
import { checkEndpoint } from '../api/endpoint';
import { Data } from '../model';

import Check from './Check';
import CheckOptionsList from './CheckOptionsList';
import { CheckActionAtom, checkActionAtom } from './checkAtoms';
import { adjustCheckedResources } from './helpers';
import useStateCheckAction from './useStateCheckAction';

interface Props {
  resources: Array<Resource>;
  stateCheckActionAtom?: PrimitiveAtom<CheckActionAtom | null>;
  testId: string;
}

const CheckActionButton = ({
  resources,
  testId,
  stateCheckActionAtom = checkActionAtom,
  ...rest
}: Props & Data): JSX.Element => {
  const { t } = useTranslation();

  const { checkAction, setCheckAction } = useStateCheckAction({
    resources,
    stateCheckActionAtom
  });

  const { onSuccessCheckAction, onSuccessForcedCheckAction } =
    rest.successCallback || {};

  const { mutateAsync: checkResource } = useMutationQuery({
    getEndpoint: () => checkEndpoint,
    method: Method.POST
  });

  const { canForcedCheck, canCheck } = useAclQuery();

  const disableCheck = !canCheck(resources);
  const disableForcedCheck = !canForcedCheck(resources);
  const hasSelectedResources = resources.length > 0;

  const isCheckPermitted = canCheck(resources) || !hasSelectedResources;

  const isForcedCheckPermitted =
    canForcedCheck(resources) || !hasSelectedResources;

  const handleCheckResource = (): void => {
    checkResource({
      payload: {
        check: { is_forced: false },
        resources: adjustCheckedResources({ resources })
      }
    }).then(() => {
      onSuccessCheckAction?.();
    });
  };

  const handleForcedCheckResource = (): void => {
    checkResource({
      payload: {
        check: { is_forced: true },
        resources: adjustCheckedResources({ resources })
      }
    }).then(() => {
      onSuccessForcedCheckAction?.();
    });
  };

  const onClickCheck = (): void => {
    setCheckAction({ checked: true, forcedChecked: false });
  };

  const onClickForcedCheck = (): void => {
    setCheckAction({ checked: false, forcedChecked: true });
  };

  if (checkAction?.forcedChecked) {
    return (
      <Check
        disabledButton={disableForcedCheck}
        renderCheckOptionList={({ anchorEl, isOpen }) => (
          <CheckOptionsList
            anchorEl={anchorEl}
            disabled={{ disableCheck, disableForcedCheck }}
            isDefaultChecked={false}
            open={isOpen}
            onClickCheck={onClickCheck}
            onClickForcedCheck={onClickForcedCheck}
            {...rest.listOptions}
          />
        )}
        renderResourceActionButton={({ onClick }) => (
          <ResourceActionButton
            disabled={disableForcedCheck}
            icon={<IconForcedCheck />}
            label={t(labelForcedCheck)}
            permitted={isCheckPermitted}
            testId={testId}
            onClick={onClick}
          />
        )}
        onClickActionButton={handleForcedCheckResource}
      />
    );
  }

  if (checkAction?.checked) {
    return (
      <Check
        disabledButton={disableCheck}
        renderCheckOptionList={({ anchorEl, isOpen }) => (
          <CheckOptionsList
            isDefaultChecked
            anchorEl={anchorEl}
            disabled={{ disableCheck, disableForcedCheck }}
            open={isOpen}
            onClickCheck={onClickCheck}
            onClickForcedCheck={onClickForcedCheck}
            {...rest.listOptions}
          />
        )}
        renderResourceActionButton={({ onClick }) => (
          <ResourceActionButton
            disabled={disableCheck}
            icon={<IconCheck />}
            label={t(labelCheck)}
            permitted={isCheckPermitted}
            testId={testId}
            onClick={onClick}
          />
        )}
        onClickActionButton={handleCheckResource}
      />
    );
  }

  return (
    <Check
      disabledButton
      renderResourceActionButton={({ onClick }) => (
        <ResourceActionButton
          disabled
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
};

export default CheckActionButton;
