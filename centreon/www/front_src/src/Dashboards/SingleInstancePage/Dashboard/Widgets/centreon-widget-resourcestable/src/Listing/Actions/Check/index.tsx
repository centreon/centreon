import IconForcedCheck from '@mui/icons-material/FlipCameraAndroidOutlined';
import IconCheck from '@mui/icons-material/Sync';

import { Method, useMutationQuery } from '@centreon/ui';

import { PrimitiveAtom } from 'jotai';
import { useTranslation } from 'react-i18next';

import { Resource } from '../../models';
import { labelCheck, labelForcedCheck } from '../../translatedLabels';
import useAclQuery from '../aclQuery';
import { checkEndpoint } from '../api/endpoint';
import { Data } from '../model';
import ResourceActionButton from '../ResourceActionButton';
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
        onClickActionButton={handleForcedCheckResource}
        renderCheckOptionList={({ anchorEl, isOpen }) => (
          <CheckOptionsList
            anchorEl={anchorEl}
            disabled={{ disableCheck, disableForcedCheck }}
            isDefaultChecked={false}
            onClickCheck={onClickCheck}
            onClickForcedCheck={onClickForcedCheck}
            open={isOpen}
            {...rest.listOptions}
          />
        )}
        renderResourceActionButton={({ onClick }) => (
          <ResourceActionButton
            disabled={disableForcedCheck}
            icon={<IconForcedCheck />}
            label={t(labelForcedCheck)}
            onClick={onClick}
            permitted={isCheckPermitted}
            testId={testId}
          />
        )}
      />
    );
  }

  if (checkAction?.checked) {
    return (
      <Check
        disabledButton={disableCheck}
        onClickActionButton={handleCheckResource}
        renderCheckOptionList={({ anchorEl, isOpen }) => (
          <CheckOptionsList
            anchorEl={anchorEl}
            disabled={{ disableCheck, disableForcedCheck }}
            isDefaultChecked
            onClickCheck={onClickCheck}
            onClickForcedCheck={onClickForcedCheck}
            open={isOpen}
            {...rest.listOptions}
          />
        )}
        renderResourceActionButton={({ onClick }) => (
          <ResourceActionButton
            disabled={disableCheck}
            icon={<IconCheck />}
            label={t(labelCheck)}
            onClick={onClick}
            permitted={isCheckPermitted}
            testId={testId}
          />
        )}
      />
    );
  }

  return (
    <Check
      disabledButton
      onClickActionButton={(): void => undefined}
      renderResourceActionButton={({ onClick }) => (
        <ResourceActionButton
          disabled
          icon={<IconForcedCheck />}
          label={t(labelForcedCheck)}
          onClick={onClick}
          permitted={isForcedCheckPermitted}
          testId={testId}
        />
      )}
    />
  );
};

export default CheckActionButton;
